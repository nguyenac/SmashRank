<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tab 'Lộ trình huấn luyện' (Training Path):
 *  - VĐV thiết lập mục tiêu cá nhân, theo dõi bài tập chuyên môn
 *  - Gợi ý tập luyện giả lập từ HLV theo chỉ số kỹ năng
 */
class TrainingGoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goals = TrainingGoal::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->get();

        $athlete = $request->user()->athlete;

        return response()->json([
            'data' => $goals,
            'coach_suggestion' => $this->suggest($athlete),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'target' => ['nullable', 'string', 'max:500'],
            'drill' => ['nullable', 'string', 'max:200'],
            'frequency' => ['nullable', 'string', 'max:80'],
            'deadline' => ['nullable', 'date'],
            // Mục tiêu thông minh (Smart Goals)
            'metric' => ['nullable', 'in:elo,win_rate'],
            'target_value' => ['nullable', 'numeric'],
        ]);

        // Ghi baseline hiện tại để tự tính tiến độ
        if (! empty($data['metric'])) {
            $athlete = Athlete::where('user_id', $request->user()->id)->first();
            $data['baseline_value'] = $data['metric'] === 'elo'
                ? ($athlete->elo_rating ?? 1000)
                : ($athlete->win_rate ?? 0);
        }

        $goal = TrainingGoal::create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['data' => $goal, 'message' => 'Đã thêm mục tiêu.'], 201);
    }

    public function update(Request $request, TrainingGoal $goal): JsonResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không có quyền.'], 403);
        }

        $data = $request->validate([
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', 'in:active,done,archived'],
            'title' => ['sometimes', 'string', 'max:150'],
            'target' => ['nullable', 'string'],
            'drill' => ['nullable', 'string', 'max:200'],
            'frequency' => ['nullable', 'string', 'max:80'],
            'deadline' => ['nullable', 'date'],
        ]);

        $goal->update($data);
        if (($data['progress'] ?? 0) >= 100) {
            $goal->update(['status' => 'done']);
        }

        return response()->json(['data' => $goal->fresh(), 'message' => 'Đã cập nhật.']);
    }

    /** Tiến độ tự động của Smart Goals (so Elo/tỷ lệ thắng hiện tại với baseline). */
    public function smartProgress(Request $request): JsonResponse
    {
        $athlete = Athlete::where('user_id', $request->user()->id)->first();
        $goals = TrainingGoal::where('user_id', $request->user()->id)
            ->whereNotNull('metric')->get();

        return response()->json(['data' => $goals->map(function ($goal) use ($athlete) {
            $current = $goal->metric === 'elo' ? ($athlete->elo_rating ?? 1000) : ($athlete->win_rate ?? 0);
            $baseline = (float) $goal->baseline_value;
            $target = (float) $goal->target_value;
            $progress = $target != $baseline
                ? max(0, min(100, round(($current - $baseline) / ($target - $baseline) * 100)))
                : 0;

            return [
                'id' => $goal->id,
                'title' => $goal->title,
                'metric' => $goal->metric,
                'baseline' => $baseline,
                'current' => $current,
                'target' => $target,
                'progress' => $progress,
            ];
        })]);
    }

    public function destroy(Request $request, TrainingGoal $goal): JsonResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không có quyền.'], 403);
        }
        $goal->delete();

        return response()->json(['message' => 'Đã xóa mục tiêu.']);
    }

    /** Gợi ý tập luyện "giả lập" dựa trên chỉ số kỹ năng yếu nhất của VĐV. */
    private function suggest($athlete): array
    {
        if (! $athlete) {
            return ['Lập hồ sơ vận động viên để nhận gợi ý cá nhân hóa.'];
        }

        $skills = [
            'skill_agility' => ['nhanh nhẹn', 'Bài tập bộ chân góc lùi + bắt cầu đa hướng, 15 phút/trước buổi tập.'],
            'skill_power' => ['sức mạnh', 'Jump smash vào thùng cầu 3 hiệp x 20 lần; squat jump 3x12.'],
            'skill_stamina' => ['sức bền', 'Chạy interval 400m x 6 lượt; đa cầu phòng thủ liên hoàn 5 phút/set.'],
            'skill_technique' => ['kỹ thuật', 'Shadow practice clear/drop/smash 10 phút đầu giờ; quay video đối chiếu.'],
            'skill_defense' => ['phòng thủ', 'Đa cầu phản tạt góc trái tay 4 set x 20 cầu; đứng vững thế thủ 10s.'],
            'skill_mentality' => ['tâm lý', 'Mô phỏng deuce 20-20 trong tập: thi đấu điểm quyết định 5 set.'],
        ];

        arsort($weakest = collect($skills)->mapWithKeys(
            fn ($v, $k) => [$k => $athlete->$k ?? 50]
        )->all());

        $lowest = array_key_first($weakest);

        return [
            "Chỉ số yếu nhất của bạn là {$skills[$lowest][0]} ({$weakest[$lowest]}/100).",
            'Gợi ý từ HLV: '.$skills[$lowest][1],
            'Duy trì tối thiểu 3 buổi/tuần và ghi lại tiến độ trong Lộ trình để nhận điều chỉnh.',
        ];
    }
}
