<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\MatchGame;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ghi nhận kết quả trận đấu và cập nhật Elo (K-factor 32) + thắng/thua.
 *
 * 2 chế độ:
 *  - record()          : ghi trực tiếp (admin/trọng tài) — áp Elo ngay
 *  - createPending()   : tạo trận "chờ xác nhận" kèm MATCH CODE — chống gian lận
 *  - confirmByCode()   : đối thủ nhập mã → áp Elo. Trận giao lưu nên dùng luồng này.
 */
class MatchResultService
{
    /** Ghi trực tiếp + áp Elo ngay (dùng cho admin/trọng tài/giải đấu). */
    public function record(array $data): MatchGame
    {
        return DB::transaction(function () use ($data) {
            $match = $this->createMatch($data, status: 'confirmed');
            $this->validateScore($match);
            $this->applyEffects($match);

            return $match->fresh(['athlete1', 'athlete2']);
        });
    }

    /** Tạo trận chờ xác nhận với MATCH CODE — chưa áp Elo. */
    public function createPending(array $data, int $creatorUserId): MatchGame
    {
        return DB::transaction(function () use ($data, $creatorUserId) {
            $match = $this->createMatch($data, status: 'pending', creatorUserId: $creatorUserId);
            $this->validateScore($match);

            return $match->fresh(['athlete1', 'athlete2']);
        });
    }

    /** Đối thủ nhập mã xác nhận → áp Elo + thắng/thua. */
    public function confirmByCode(string $code, int $confirmingUserId): MatchGame
    {
        return DB::transaction(function () use ($code, $confirmingUserId) {
            $match = MatchGame::where('match_code', strtoupper(trim($code)))
                ->where('status', 'pending')
                ->firstOrFail();

            // Chỉ VĐV tham gia trận mới được xác nhận; người tạo không được tự
            // xác nhận trận của chính mình (chống tự công nhận điểm) — admin ngoại lệ
            $athleteUserIds = [$match->athlete1->user_id, $match->athlete2?->user_id];
            $isParticipant = in_array($confirmingUserId, array_filter($athleteUserIds), true);
            $isCreator = $match->created_by_user_id === $confirmingUserId;

            if (! $isParticipant) {
                throw new \RuntimeException('Bạn không tham gia trận đấu này.');
            }

            if ($isCreator && ! \App\Models\User::find($confirmingUserId)->isAdmin()) {
                throw new \RuntimeException('Bạn là người tạo trận — chờ đối thủ nhập mã xác nhận.');
            }

            $match->update([
                'status' => 'confirmed',
                'confirmed_by_user_id' => $confirmingUserId,
            ]);
            $this->applyEffects($match);

            return $match->fresh(['athlete1', 'athlete2']);
        });
    }

    // ------------------------------------------------------------------

    private function createMatch(array $data, string $status, ?int $creatorUserId = null): MatchGame
    {
        return MatchGame::create([
            'athlete1_id' => $data['athlete1_id'],
            'athlete2_id' => $data['athlete2_id'],
            'score1' => $data['score1'] ?? 0,
            'score2' => $data['score2'] ?? 0,
            'walkover' => $data['walkover'] ?? false,
            'category' => $data['category'] ?? 'MS',
            'tournament_id' => $data['tournament_id'] ?? null,
            'venue' => $data['venue'] ?? null,
            'played_at' => $data['played_at'] ?? now()->toDateString(),
            'status' => $status,
            'match_code' => $status === 'pending' ? strtoupper(Str::random(6)) : null,
            'created_by_user_id' => $creatorUserId,
        ]);
    }

    private function validateScore(MatchGame $match): void
    {
        if (! $match->walkover && $match->score1 === $match->score2) {
            throw new \RuntimeException('Tỷ số hòa không hợp lệ ở nội dung đơn.');
        }
        if (! $match->walkover && max($match->score1, $match->score2) < 2) {
            throw new \RuntimeException('Cần thắng ít nhất 2 game (bo3).');
        }
    }

    /** Áp hiệu quả trận: Elo + thắng/thua + chuỗi thắng + thống kê năm. */
    private function applyEffects(MatchGame $match): void
    {
        if ($match->rating_change !== null) {
            return; // đã áp trước đó
        }

        $a1 = Athlete::lockForUpdate()->findOrFail($match->athlete1_id);
        $a2 = Athlete::lockForUpdate()->findOrFail($match->athlete2_id);

        $rating = 32; // config('elo.k_factor')

        if (! $match->walkover) {
            $expected1 = 1 / (1 + 10 ** (($a2->elo_rating - $a1->elo_rating) / 400));
            $actual1 = $match->score1 > $match->score2 ? 1 : 0;
            $delta = (int) round($rating * ($actual1 - $expected1));

            $a1->elo_rating = max(100, $a1->elo_rating + $delta);
            $a2->elo_rating = max(100, $a2->elo_rating - $delta);
            $match->rating_change = abs($delta);
        } else {
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

        $winner = $match->winnerId() === $a1->id ? $a1 : $a2;
        $loser = $winner->id === $a1->id ? $a2 : $a1;
        $winner->win_count++;
        $loser->loss_count++;

        $winnerDetails = $winner->details()->firstOrCreate([]);
        $loserDetails = $loser->details()->firstOrCreate([]);
        $winnerDetails->win_streak_current = $winnerDetails->win_streak_current + 1;
        $winnerDetails->save();
        $loserDetails->win_streak_current = 0;
        $loserDetails->save();

        $a1->save();
        $a2->save();
        $match->save();

        $this->bumpYearStats($winner, $match->played_at->year, wins: 1, matches: 1);
        $this->bumpYearStats($loser, $match->played_at->year, wins: 0, matches: 1);
    }

    private function bumpYearStats(Athlete $athlete, int $year, int $wins, int $matches): void
    {
        $stat = $athlete->yearStats()->firstOrNew(['year' => $year]);
        $stat->matches = ($stat->matches ?? 0) + $matches;
        $stat->wins = ($stat->wins ?? 0) + $wins;
        $stat->save();
    }
}
