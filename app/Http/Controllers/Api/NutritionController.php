<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\NutritionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tab 'Dinh dưỡng VĐV' — nhật ký ăn uống/bổ sung năng lượng theo ngày. */
class NutritionController extends Controller
{
    public function index(Athlete $athlete): JsonResponse
    {
        $logs = NutritionLog::where('athlete_id', $athlete->id)
            ->orderByDesc('log_date')->orderBy('meal')
            ->limit(120)->get();

        // Tổng hợp theo ngày cho biểu đồ calo
        $byDay = $logs->groupBy('log_date')->map(fn ($group, $date) => [
            'date' => $date,
            'calories' => (int) $group->sum('calories'),
            'protein_g' => (int) $group->sum('protein_g'),
            'water_ml' => (int) $group->sum('water_ml'),
        ])->values();

        return response()->json(['data' => $logs, 'summary' => $byDay]);
    }

    public function store(Request $request, Athlete $athlete): JsonResponse
    {
        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'meal' => ['required', 'in:breakfast,lunch,dinner,snack,pre_match'],
            'description' => ['nullable', 'string', 'max:500'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'protein_g' => ['nullable', 'integer', 'min:0', 'max:500'],
            'carbs_g' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'water_ml' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $log = $athlete->nutritionLogs()->create($data);

        return response()->json(['data' => $log, 'message' => 'Đã ghi nhật ký dinh dưỡng.'], 201);
    }

    public function destroy(NutritionLog $log): JsonResponse
    {
        $log->delete();

        return response()->json(['message' => 'Đã xóa mục dinh dưỡng.']);
    }
}
