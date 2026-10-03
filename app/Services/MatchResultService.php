<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\MatchGame;
use Illuminate\Support\Facades\DB;

/**
 * Ghi nhận kết quả trận đấu và cập nhật Elo (K-factor 32) + thắng/thua.
 * Dùng cho AddMatchResultModal và live scoring khi trận hoàn tất.
 */
class MatchResultService
{
    public function __construct(private EloService $elo)
    {
    }

    public function record(array $data): MatchGame
    {
        return DB::transaction(function () use ($data) {
            $a1 = Athlete::lockForUpdate()->findOrFail($data['athlete1_id']);
            $a2 = Athlete::lockForUpdate()->findOrFail($data['athlete2_id']);

            $match = MatchGame::create([
                ...$data,
                'played_at' => $data['played_at'] ?? now()->toDateString(),
            ]);

            if (!$match->walkover && $match->score1 === $match->score2) {
                throw new \RuntimeException('Tỷ số hòa không hợp lệ ở nội dung đơn.');
            }

            $rating = 32; // config('elo.k_factor')

            // Elo chuẩn: winner nhận rating_change, loser mất cùng số điểm
            if (!$match->walkover) {
                $expected1 = 1 / (1 + 10 ** (($a2->elo_rating - $a1->elo_rating) / 400));
                $actual1 = $match->score1 > $match->score2 ? 1 : 0;
                $delta = (int) round($rating * ($actual1 - $expected1)); // >0 nếu a1 thắng

                $a1->elo_rating = max(100, $a1->elo_rating + $delta);
                $a2->elo_rating = max(100, $a2->elo_rating - $delta);
                $match->rating_change = abs($delta);
            } else {
                // W.O.: người bỏ cuộc thua, chuyển điểm cố định nhỏ
                $delta = (int) round($rating * 0.4);
                $winnerIs1 = $match->score1 > $match->score2;
                if ($winnerIs1) {
                    $a1->elo_rating += $delta;
                    $a2->elo_rating = max(100, $a2->elo_rating - $delta);
                } else {
                    $a2->elo_rating += $delta;
                    $a1->elo_rating = max(100, $a1->elo_rating - $delta);
                }
                $match->rating_change = $delta;
            }

            // Cập nhật thắng/thua
            $winner = $match->winnerId() === $a1->id ? $a1 : $a2;
            $loser = $winner->id === $a1->id ? $a2 : $a1;
            $winner->win_count++;
            $loser->loss_count++;

            // Cập nhật chuỗi thắng hiện tại
            $winnerDetails = $winner->details()->firstOrCreate([]);
            $loserDetails = $loser->details()->firstOrCreate([]);
            $winnerDetails->win_streak_current = $winnerDetails->win_streak_current + 1;
            $winnerDetails->save();
            $loserDetails->win_streak_current = 0;
            $loserDetails->save();

            $a1->save();
            $a2->save();
            $match->save();

            // Cập nhật thống kê theo năm (cho leaderboard & kỷ lục)
            $this->bumpYearStats($winner, $match->played_at->year, wins: 1, matches: 1);
            $this->bumpYearStats($loser, $match->played_at->year, wins: 0, matches: 1);

            return $match->fresh(['athlete1', 'athlete2']);
        });
    }

    private function bumpYearStats(Athlete $athlete, int $year, int $wins, int $matches): void
    {
        $stat = $athlete->yearStats()->firstOrNew(['year' => $year]);
        $stat->matches = ($stat->matches ?? 0) + $matches;
        $stat->wins = ($stat->wins ?? 0) + $wins;
        $stat->save();
    }
}
