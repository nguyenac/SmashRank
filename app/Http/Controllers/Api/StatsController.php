<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\EquipmentItem;
use Illuminate\Http\JsonResponse;

/**
 * Platform Statistics (Admin):
 *  - Scatter: loại vợt (trọng lượng/độ căng) ↔ hiệu suất thi đấu (tỷ lệ thắng)
 *  - Heatmap: mật độ hoạt động VĐV theo ngày trong tuần x giờ
 */
class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        // --- Scatter: từng VĐV có vợt → (max_tension hoặc weight, win_rate) ---
        $scatter = Athlete::query()
            ->whereNotNull('racket_id')
            ->with('racket:id,name,specifications')
            ->get()
            ->filter(fn (Athlete $a) => $a->racket?->specifications)
            ->map(fn (Athlete $a) => [
                'athlete' => $a->full_name,
                'racket' => $a->racket->name,
                'weight' => $a->racket->specifications['weight'] ?? null,
                'max_tension' => $a->racket->specifications['max_tension'] ?? null,
                'win_rate' => $a->win_rate,
            ])
            ->values();

        // --- Heatmap: ngày trong tuần x giờ (từ dữ liệu đặt sân) ---
        $heatmap = AthleteExtrasController::activityHeatmap();

        // Thống kê phân bố trọng lượng vợt ↔ tỷ lệ thắng trung bình
        $byWeight = $scatter->groupBy('weight')->map(fn ($g, $w) => [
            'weight' => $w,
            'avg_win_rate' => round($g->avg('win_rate'), 1),
            'count' => $g->count(),
        ])->values();

        $equipmentTypes = EquipmentItem::selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type');

        return response()->json([
            'data' => [
                'scatter' => $scatter,
                'by_weight' => $byWeight,
                'activity_heatmap' => $heatmap,
                'equipment_types' => $equipmentTypes,
            ],
        ]);
    }
}
