<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\MatchGame;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboard:
 *  - all    : bảng xếp hạng tổng theo Elo hiện tại
 *  - monthly: điểm thi đấu trong THÁNG hiện tại (tổng rating_change từ các trận)
 *  - weekly : điểm trong TUẦN hiện tại + thay đổi thứ hạng so với tuần trước
 */
class LeaderboardService
{
    public function get(string $period = 'all', int $limit = 50): array
    {
        return match ($period) {
            'monthly' => $this->periodBoard(now()->startOfMonth(), now()->endOfMonth()),
            'weekly' => $this->weeklyBoard(),
            default => $this->allBoard($limit),
        };
    }

    private function allBoard(int $limit): array
    {
        return Athlete::query()
            ->orderByDesc('elo_rating')
            ->limit($limit)
            ->get()
            ->values()
            ->map(fn (Athlete $a, $i) => [
                'rank' => $i + 1,
                'rank_change' => 0,
                'period_points' => null,
                'athlete' => $this->brief($a),
            ])
            ->all();
    }

    /** Bảng điểm theo khoảng thời gian: tổng +/- Elo từ các trận trong kỳ. */
    private function periodBoard($start, $end): array
    {
        $deltas = MatchGame::query()
            ->whereBetween('played_at', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('rating_change')
            ->selectRaw('athlete1_id as athlete_id, SUM(CASE WHEN score1 > score2 THEN rating_change ELSE -rating_change END) as points')
            ->groupBy('athlete1_id')
            ->unionAll(
                MatchGame::query()
                    ->whereBetween('played_at', [$start->toDateString(), $end->toDateString()])
                    ->whereNotNull('rating_change')
                    ->selectRaw('athlete2_id as athlete_id, SUM(CASE WHEN score2 > score1 THEN rating_change ELSE -rating_change END) as points')
                    ->groupBy('athlete2_id')
            );

        $rows = DB::table(DB::raw("({$deltas->toSql()}) as d"))
            ->mergeBindings($deltas->getQuery())
            ->selectRaw('athlete_id, SUM(points) as period_points')
            ->groupBy('athlete_id')
            ->orderByDesc('period_points')
            ->limit(50)
            ->get();

        $athletes = Athlete::whereIn('id', $rows->pluck('athlete_id'))->get()->keyBy('id');

        return $rows->values()->map(fn ($row, $i) => [
            'rank' => $i + 1,
            'rank_change' => 0,
            'period_points' => (int) $row->period_points,
            'athlete' => isset($athletes[$row->athlete_id]) ? $this->brief($athletes[$row->athlete_id]) : null,
        ])->all();
    }

    /** Bảng tuần: điểm tuần này + thứ hạng tuần trước để so thay đổi. */
    private function weeklyBoard(): array
    {
        $thisWeek = $this->periodBoard(now()->startOfWeek(), now()->endOfWeek());
        $lastWeek = collect($this->periodBoard(now()->startOfWeek()->subWeek(), now()->endOfWeek()->subWeek()));

        // Thứ hạng tuần trước (theo điểm tuần trước)
        $lastRanks = $lastWeek->mapWithKeys(fn ($row, $i) => [$row['athlete']['id'] ?? 0 => $i + 1]);

        // VĐV có thi đấu tuần này nhưng không có ở tuần trước → coi như hạng cuối tuần trước
        $lastCount = $lastWeek->count();

        return array_map(function ($row) use ($lastRanks, $lastCount) {
            $prev = $lastRanks[$row['athlete']['id'] ?? 0] ?? null;
            $row['rank_change'] = $prev !== null
                ? $prev - $row['rank'] // >0 = tiến bộ
                : ($lastCount > 0 ? $lastCount - $row['rank'] + 1 : 0); // mới vào bảng
            return $row;
        }, $thisWeek);
    }

    private function brief(Athlete $a): array
    {
        return [
            'id' => $a->id,
            'full_name' => $a->full_name,
            'country_code' => $a->country_code,
            'category' => $a->category,
            'elo_rating' => $a->elo_rating,
            'avatar_url' => $a->avatar_url,
        ];
    }
}
