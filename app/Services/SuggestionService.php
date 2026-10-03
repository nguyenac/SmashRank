<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\EquipmentItem;
use Illuminate\Support\Facades\DB;

/**
 * Gợi ý thông minh:
 *  1) Vợt/giày phù hợp dựa trên lối đánh + trình độ của VĐV
 *  2) Bài tập gợi ý theo chỉ số yếu nhất (dữ liệu THAM KHẢO — không thay thế HLV)
 */
class SuggestionService
{
    /**
     * Gợi ý vợt theo lối đánh & trình độ:
     *  - Tấn công → nặng đầu vợt (head_heavy), thân cứng
     *  - Phòng thủ / điều cầu → nhẹ đầu (head_light), linh hoạt
     *  - Toàn diện → cân bằng
     *  - Pro/Advanced → max_tension cao; Intermediate/BEGINNER → dễ dùng hơn
     */
    public function suggestRackets(Athlete $athlete, int $limit = 4): array
    {
        $style = $athlete->details?->playing_style ?? '';
        $isAttack = str_contains($style, 'tấn công') || str_contains($style, 'Tấn công');
        $isDefense = str_contains($style, 'phòng thủ') || str_contains($style, 'Phòng thủ') || str_contains($style, 'điều cầu');

        $balance = $isAttack ? 'head_heavy' : ($isDefense ? 'head_light' : 'even');
        $flex = $isAttack ? ['extra_stiff', 'stiff'] : ($isDefense ? ['flexible', 'medium'] : ['medium', 'stiff']);
        $proLevel = in_array($athlete->skill_level, ['pro', 'advanced']);

        $items = EquipmentItem::query()->where('type', 'racket')->get();

        return $items
            ->map(function (EquipmentItem $item) use ($balance, $flex, $proLevel) {
                $spec = $item->specifications ?? [];
                $score = 0;
                if (($spec['balance_point'] ?? null) === $balance) $score += 40;
                if (in_array($spec['shaft_flexibility'] ?? null, $flex)) $score += 25;
                if ($proLevel && ($spec['max_tension'] ?? 0) >= 28) $score += 20;
                if (! $proLevel && ($spec['max_tension'] ?? 0) <= 28) $score += 10;
                if (! $proLevel && in_array($spec['weight'] ?? '', ['4U', '5U'])) $score += 10;

                return ['item' => $item, 'score' => $score, 'reason' => $this->reason($item, $balance, $proLevel)];
            })
            ->filter(fn ($s) => $s['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(fn ($s) => [
                'id' => $s['item']->id,
                'name' => $s['item']->name,
                'brand' => $s['item']->brand,
                'model' => $s['item']->model,
                'price' => $s['item']->price,
                'specifications' => $s['item']->specifications,
                'match_reason' => $s['reason'],
            ]);
    }

    private function reason(EquipmentItem $item, string $balance, bool $proLevel): string
    {
        $spec = $item->specifications ?? [];
        $parts = [];
        $balanceVi = ['head_heavy' => 'nặng đầu — uy lực đập cầu', 'head_light' => 'nhẹ đầu — nhanh phản tạt', 'even' => 'cân bằng — toàn diện'][$balance] ?? '';
        if (($spec['balance_point'] ?? null) === $balance) $parts[] = "điểm cân bằng {$balanceVi}";
        if ($proLevel && ($spec['max_tension'] ?? 0) >= 28) $parts[] = "chịu căng tới {$spec['max_tension']} lbs phù hợp trình độ cao";
        if (! $proLevel && in_array($spec['weight'] ?? '', ['4U', '5U'])) $parts[] = 'trọng lượng nhẹ, dễ điều khiển';

        return implode(' · ', $parts) ?: 'Phù hợp tổng quát với hồ sơ của bạn';
    }

    /**
     * Gợi ý giày theo thể chất + lối đánh: người cao/nặng → đệm tốt;
     * nhẹ/nhanh → giày nhẹ, bám sân cao.
     */
    public function suggestShoes(Athlete $athlete, int $limit = 3): array
    {
        $detail = $athlete->details;
        $height = $detail?->height_cm ?? 170;
        $weight = $detail?->weight_kg ?? 65;
        $heavy = $weight >= 75 || $height >= 180;

        return EquipmentItem::query()->where('type', 'shoes')->get()
            ->map(function ($item) use ($heavy) {
                $spec = $item->specifications ?? [];
                $score = 0;
                $cushion = strtolower((string) ($spec['cushion'] ?? ''));
                $grip = strtolower((string) ($spec['grip'] ?? ''));
                if ($heavy && (str_contains($cushion, 'power') || str_contains($cushion, 'max'))) $score += 40;
                if (! $heavy && (str_contains($grip, 'high') || str_contains($grip, 'rubber'))) $score += 35;
                if ($spec !== []) $score += 15;

                return ['item' => $item, 'score' => $score];
            })
            ->filter(fn ($s) => $s['score'] > 0)
            ->sortByDesc('score')->take($limit)->values()
            ->map(fn ($s) => [
                'id' => $s['item']->id, 'name' => $s['item']->name,
                'brand' => $s['item']->brand, 'price' => $s['item']->price,
                'match_reason' => $heavy ? 'Đệm giảm chấn tốt — phù hợp thể hình cao/to, giảm tải khớp gối' : 'Nhẹ & bám sân cao — phù hợp lối chơi nhanh nhẹn',
            ]);
    }

    /**
     * Gợi ý bài tập dựa trên chỉ số yếu nhất + dữ liệu tập thể (thư viện drills).
     * ⚠️ Luôn kèm ghi chú: đây là DỮ LIỆU THAM KHẢO.
     */
    public function suggestDrills(Athlete $athlete, int $limit = 6): array
    {
        $categoryMap = [
            'skill_power' => 'attack',
            'skill_defense' => 'defense',
            'skill_agility' => 'footwork',
            'skill_technique' => 'technique',
            'skill_stamina' => 'stamina',
        ];

        $weakest = collect($categoryMap)
            ->mapWithKeys(fn ($cat, $col) => [$cat => $athlete->$col ?? 50])
            ->sort();

        $priorityCategories = $weakest->keys()->take(2)->all();
        $levelHint = match ($athlete->skill_level) {
            'pro' => 'hard',
            'advanced' => 'medium',
            'intermediate' => 'medium',
            default => 'easy',
        };

        return DB::table('drills')
            ->whereIn('category', $priorityCategories)
            ->orderByRaw("CASE WHEN difficulty = ? THEN 0 WHEN difficulty = 'medium' THEN 1 ELSE 2 END", [$levelHint])
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'detail' => $d->detail,
                'category' => $d->category,
                'difficulty' => $d->difficulty,
                'duration_min' => $d->duration_min,
            ])
            ->all();
    }
}
