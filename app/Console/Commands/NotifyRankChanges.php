<?php

namespace App\Console\Commands;

use App\Models\AthleteFollow;
use App\Models\MatchGame;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Thông báo cho người dùng khi VĐV HỌ THEO DÕI có thay đổi thứ hạng
 * (có trận đấu trong 7 ngày qua làm đổi Elo).
 */
class NotifyRankChanges extends Command
{
    protected $signature = 'notify:rank-changes';

    protected $description = 'Gửi push thông báo thay đổi thứ hạng của các VĐV đang được theo dõi';

    public function handle(NotificationService $notifications): int
    {
        $recent = MatchGame::where('played_at', '>=', now()->subDays(7))
            ->whereNotNull('rating_change')
            ->get(['athlete1_id', 'athlete2_id', 'score1', 'score2', 'rating_change']);

        if ($recent->isEmpty()) {
            $this->info('Không có thay đổi thứ hạng nào trong 7 ngày qua.');

            return self::SUCCESS;
        }

        $sent = 0;
        AthleteFollow::with(['athlete:id,full_name', 'user:id,name'])->chunkById(100, function ($follows) use ($recent, $notifications, &$sent) {
            foreach ($follows as $follow) {
                $match = $recent->first(
                    fn ($m) => $m->athlete1_id === $follow->athlete_id || $m->athlete2_id === $follow->athlete_id
                );
                if (! $match) {
                    continue;
                }

                $isWinner = ($match->athlete1_id === $follow->athlete_id) === ($match->score1 > $match->score2);
                $text = $isWinner
                    ? "+{$match->rating_change} Elo — chuỗi trận đang thăng hoa!"
                    : "-{$match->rating_change} Elo — cần cải thiện phong độ.";

                $sent += $notifications->notifyRankChange($follow, $text);
            }
        });

        $this->info("Đã gửi {$sent} thông báo thay đổi thứ hạng.");

        return self::SUCCESS;
    }
}
