<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\AthleteDetail;
use App\Models\MatchGame;
use App\Services\SuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Insights cho hồ sơ VĐV:
 *  - career-milestones : huy hiệu cột mốc sự nghiệp
 *  - peak-heatmap      : khung giờ/ngày đạt phong độ cao nhất (từ lịch sử trận)
 *  - zones             : heatmap vùng sân mạnh/yếu (phân tích video)
 *  - equipment-fit     : gợi ý vợt + giày theo chiến thuật & thể chất
 *  - match-notes       : thẻ yếu tố thắng/thua cho từng trận
 */
class AthleteInsightsController extends Controller
{
    public function milestones(Athlete $athlete): JsonResponse
    {
        $wins = $athlete->win_count;
        $titles = $athlete->details?->titles ?? 0;
        $rank = $athlete->world_rank;

        $milestones = [
            ['icon' => '💯', 'label' => '100 trận thắng', 'achieved' => $wins >= 100, 'progress' => min(100, round($wins / 100 * 100))],
            ['icon' => '🔥', 'label' => '300 trận thắng', 'achieved' => $wins >= 300, 'progress' => min(100, round($wins / 300 * 100))],
            ['icon' => '🌍', 'label' => 'Top 100 thế giới', 'achieved' => $rank !== null && $rank <= 100, 'progress' => $rank ? max(0, min(100, round((200 - $rank) / 2))) : 0],
            ['icon' => '🏅', 'label' => 'Top 10 thế giới', 'achieved' => $rank !== null && $rank <= 10, 'progress' => $rank ? max(0, min(100, round((50 - $rank)))) : 0],
            ['icon' => '🏆', 'label' => '10 danh hiệu', 'achieved' => $titles >= 10, 'progress' => min(100, round($titles / 10 * 100))],
            ['icon' => '👑', 'label' => '25 danh hiệu', 'achieved' => $titles >= 25, 'progress' => min(100, round($titles / 25 * 100))],
            ['icon' => '📈', 'label' => 'Elo vượt 1500', 'achieved' => $athlete->elo_rating >= 1500, 'progress' => min(100, round(($athlete->elo_rating - 1000) / 5))],
        ];

        return response()->json(['data' => $milestones]);
    }

    /** Heatmap phong độ: mật độ trận theo ngày trong tuần (từ lịch sử thi đấu). */
    public function peakHeatmap(Athlete $athlete): JsonResponse
    {
        $rows = MatchGame::query()
            ->where('athlete1_id', $athlete->id)->orWhere('athlete2_id', $athlete->id)
            ->selectRaw('WEEKDAY(played_at) as weekday, COUNT(*) as matches')
            ->groupBy('weekday')
            ->pluck('matches', 'weekday');

        $max = max(1, $rows->max());
        $heatmap = collect(range(0, 6))->map(fn ($d) => [
            'day' => ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'][$d],
            'matches' => (int) ($rows[$d] ?? 0),
            'intensity' => round((($rows[$d] ?? 0) / $max) * 100),
        ]);

        return response()->json(['data' => $heatmap, 'meta' => ['note' => 'Dựa trên lịch sử trận đã ghi nhận — dữ liệu tham khảo.']]);
    }

    /** Vùng sân mạnh/yếu (6 ô): HLV/admin click đánh dấu qua phân tích video. */
    public function zones(Athlete $athlete, Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            if (! $request->user()?->isAdmin()) {
                return response()->json(['message' => 'Chỉ HLV/Quản trị viên được đánh dấu vùng sân.'], 403);
            }
            $data = $request->validate([
                'zones' => ['required', 'array', 'size:6'],
                'zones.*' => ['integer', 'between:-100,100'], // dương = ưu thế, âm = sơ hở
            ]);
            $detail = AthleteDetail::firstOrCreate(['athlete_id' => $athlete->id]);
            $detail->update(['zone_marks' => $data['zones']]);

            return response()->json(['data' => $data['zones'], 'message' => 'Đã lưu bản đồ vùng sân.']);
        }

        $detail = AthleteDetail::find($athlete->id);

        return response()->json(['data' => $detail?->zone_marks ?? [0, 0, 0, 0, 0, 0]]);
    }

    /** Gợi ý thiết bị (vợt + giày) theo chiến thuật & thể chất (chiều cao/cân nặng). */
    public function equipmentFit(Athlete $athlete, SuggestionService $service): JsonResponse
    {
        $detail = $athlete->details;

        return response()->json(['data' => [
            'rackets' => $service->suggestRackets($athlete),
            'shoes' => $service->suggestShoes($athlete),
            'physical_profile' => [
                'height_cm' => $detail?->height_cm,
                'weight_kg' => $detail?->weight_kg,
                'playing_style' => $detail?->playing_style,
            ],
        ]]);
    }

    /**
     * Head-to-Head đối đầu: ?a=&b= — lịch sử trận giữa 2 VĐV + tổng kết.
     */
    public function headToHead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'a' => ['required', 'integer', 'exists:athletes,id', 'different:b'],
            'b' => ['required', 'integer', 'exists:athletes,id'],
        ]);

        $matches = \App\Models\MatchGame::query()
            ->where(function ($q) use ($data) {
                $q->where(fn ($x) => $x->where('athlete1_id', $data['a'])->where('athlete2_id', $data['b']))
                  ->orWhere(fn ($x) => $x->where('athlete1_id', $data['b'])->where('athlete2_id', $data['a']));
            })
            ->where('status', 'confirmed')
            ->with(['athlete1:id,full_name', 'athlete2:id,full_name'])
            ->orderByDesc('played_at')
            ->limit(30)
            ->get();

        $aWins = $matches->filter(fn ($m) => $m->winnerId() === $data['a'])->count();
        $bWins = $matches->filter(fn ($m) => $m->winnerId() === $data['b'])->count();

        $athleteA = Athlete::find($data['a']);
        $athleteB = Athlete::find($data['b']);

        return response()->json(['data' => [
            'athletes' => [
                'a' => $athleteA->only(['id', 'full_name', 'country_code', 'elo_rating']),
                'b' => $athleteB->only(['id', 'full_name', 'country_code', 'elo_rating']),
            ],
            'summary' => [
                'total' => $matches->count(),
                'a_wins' => $aWins,
                'b_wins' => $bWins,
                'a_win_rate' => $matches->count() ? round($aWins / $matches->count() * 100, 1) : 0,
            ],
            'matches' => $matches->map(fn ($m) => [
                'date' => $m->played_at->format('Y-m-d'),
                'venue' => $m->venue,
                'winner_id' => $m->winnerId(),
                'score' => "{$m->athlete1?->full_name} {$m->score1}-{$m->score2} {$m->athlete2?->full_name}",
                'walkover' => $m->walkover,
            ]),
        ]]);
    }

    /** Ghi chú thi đấu: thẻ yếu tố quyết định thắng/thua cho từng trận. */
    public function matchNote(Request $request, MatchGame $match): JsonResponse
    {
        $data = $request->validate([
            'result_tags' => ['nullable', 'array'],
            'result_tags.*' => ['in:tactic,mental,technique_error,stamina,opponent_strength,luck'],
            'result_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $match->update($data);

        return response()->json(['data' => $match->only(['id', 'result_tags', 'result_note']), 'message' => 'Đã lưu ghi chú thi đấu.']);
    }

    /** Tổng hợp báo cáo phân tích định kỳ từ thẻ thắng/thua của VĐV. */
    public function matchNotesSummary(Athlete $athlete): JsonResponse
    {
        $matches = MatchGame::query()
            ->where(fn ($q) => $q->where('athlete1_id', $athlete->id)->orWhere('athlete2_id', $athlete->id))
            ->whereNotNull('result_tags')
            ->get();

        $tagCounts = [];
        foreach ($matches as $m) {
            foreach ($m->result_tags ?? [] as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        arsort($tagCounts);

        return response()->json(['data' => [
            'analyzed_matches' => $matches->count(),
            'tag_counts' => $tagCounts,
            'insight' => collect($tagCounts)->keys()->first()
                ? 'Yếu tố xuất hiện nhiều nhất: '.collect($tagCounts)->keys()->first()
                : 'Chưa đủ dữ liệu ghi chú.',
        ]]);
    }
}
