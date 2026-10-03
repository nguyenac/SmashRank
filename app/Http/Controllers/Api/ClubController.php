<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Bảng xếp hạng CLB: tổng hợp theo câu lạc bộ — số VĐV, Elo trung bình,
 * tổng trận thắng, VĐV dẫn đầu. Khai thác dữ liệu `club` sẵn có.
 */
class ClubController extends Controller
{
    public function leaderboard(): JsonResponse
    {
        // #6 Cache 5 phút
        $clubs = Cache::remember('clubs:leaderboard', 300, fn () => $this->compute());

        return response()->json(['data' => $clubs]);
    }

    private function compute()
    {
        $clubs = Athlete::query()
            ->whereNotNull('club')
            ->where('club', '!=', '')
            ->selectRaw('club, COUNT(*) as members, ROUND(AVG(elo_rating)) as avg_elo, SUM(win_count) as total_wins, MAX(elo_rating) as top_elo')
            ->groupBy('club')
            ->orderByDesc('avg_elo')
            ->limit(30)
            ->get();

        return $clubs->map(function ($club, $i) {
            $topPlayer = Athlete::where('club', $club->club)->orderByDesc('elo_rating')->first(['id', 'full_name', 'elo_rating']);

            return [
                'rank' => $i + 1,
                'club' => $club->club,
                'members' => (int) $club->members,
                'avg_elo' => (int) $club->avg_elo,
                'total_wins' => (int) $club->total_wins,
                'top_player' => $topPlayer ? [
                    'id' => $topPlayer->id,
                    'full_name' => $topPlayer->full_name,
                    'elo_rating' => $topPlayer->elo_rating,
                ] : null,
            ];
        });
    }
}
