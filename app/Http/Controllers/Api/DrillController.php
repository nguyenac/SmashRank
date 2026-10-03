<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drill;
use App\Models\SkillSnapshot;
use App\Models\Athlete;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Thư viện bài tập (Drill Library) cho Lộ trình huấn luyện:
 *  - Lọc theo độ khó / thời gian / kỹ thuật mục tiêu (tấn công, phòng thủ, di chuyển...)
 *  - Người dùng tự tạo bài tập (nhận XP)
 *  - Snapshot kỹ năng cho biểu đồ Radar theo thời gian
 */
class DrillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Drill::query();

        if ($cat = $request->query('category')) { // attack|defense|footwork|technique|stamina
            $query->where('category', $cat);
        }
        if ($diff = $request->query('difficulty')) { // easy|medium|hard
            $query->where('difficulty', $diff);
        }
        // Khoảng thời gian hoàn thành (phút)
        if ($min = $request->query('duration_min')) {
            $query->where('duration_min', '<=', (int) $min);
        }
        if ($q = $request->query('q')) {
            $query->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")->orWhere('detail', 'like', "%{$q}%"));
        }

        return response()->json([
            'data' => $query->orderBy('difficulty')->orderBy('duration_min')
                ->limit(60)->get(),
            'meta' => ['disclaimer' => 'Dữ liệu bài tập chỉ mang tính THAM KHẢO — hãy điều chỉnh theo thể trạng và hướng dẫn của HLV.'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'detail' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'in:attack,defense,footwork,technique,stamina'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'duration_min' => ['required', 'integer', 'between:5,180'],
        ]);

        $drill = Drill::create([...$data, 'creator_id' => $request->user()->id]);

        $newBadges = app(\App\Services\XpService::class)->award($request->user(), 'drill_created');

        return response()->json([
            'data' => $drill,
            'new_badges' => $newBadges,
            'message' => 'Đã tạo bài tập (+'.\App\Services\XpService::AWARDS['drill_created'].' XP).',
        ], 201);
    }

    public function destroy(Request $request, Drill $drill): JsonResponse
    {
        if ($drill->creator_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Không có quyền xóa.'], 403);
        }
        $drill->delete();

        return response()->json(['message' => 'Đã xóa bài tập.']);
    }

    /** Radar phát triển kỹ năng theo thời gian (Power/Speed/Defense/Net/Stamina). */
    public function skillProgress(): JsonResponse
    {
        $athlete = Athlete::where('user_id', Auth::id())->first();
        if (! $athlete) {
            return response()->json(['data' => []]);
        }

        $snapshots = $athlete->skillSnapshots()->get();

        // Nếu chưa có snapshot nào: tạo từ chỉ số hiện tại làm mốc đầu tiên
        if ($snapshots->isEmpty()) {
            SkillSnapshot::create([
                'athlete_id' => $athlete->id,
                'recorded_at' => now()->toDateString(),
                'power' => $athlete->skill_power,
                'speed' => $athlete->skill_agility,
                'defense' => $athlete->skill_defense,
                'net_play' => $athlete->skill_technique,
                'stamina' => $athlete->skill_stamina,
            ]);
            $snapshots = $athlete->skillSnapshots()->get();
        }

        return response()->json([
            'data' => $snapshots->map(fn (SkillSnapshot $s) => [
                'date' => $s->recorded_at->format('Y-m-d'),
                'power' => $s->power,
                'speed' => $s->speed,
                'defense' => $s->defense,
                'net_play' => $s->net_play,
                'stamina' => $s->stamina,
            ]),
        ]);
    }
}
