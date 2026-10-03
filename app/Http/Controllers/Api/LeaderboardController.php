<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /leaderboards?period=all|monthly|weekly
 *  - all    : bảng xếp hạng tổng (Elo)
 *  - monthly: điểm thi đấu trong tháng hiện tại
 *  - weekly : điểm tuần hiện tại + thay đổi thứ hạng so với tuần trước
 */
class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $service): JsonResponse
    {
        $request->validate(['period' => ['nullable', 'in:all,monthly,weekly']]);

        $period = $request->query('period', 'all');

        return response()->json([
            'data' => $service->get($period),
            'meta' => ['period' => $period],
        ]);
    }
}
