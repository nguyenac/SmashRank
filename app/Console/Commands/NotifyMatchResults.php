<?php

namespace App\Console\Commands;

use App\Models\MatchGame;
use App\Models\AthleteFollow;
use App\Models\Tournament;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Gửi thông báo đẩy tự động:
 *  1) Kết quả mới của trận đấu có VĐV được theo dõi (chạy 15 phút/lần)
 *  2) Nhắc giải đấu sắp diễn ra trước 24 giờ (chạy hằng giờ)
 */
class NotifyMatchResults extends Command
{
    protected $signature = 'notify:match-results {--tournaments : Nhắc giải đấu trước 24h}';

    protected $description = 'Push kết quả trận mới cho người theo dõi + nhắc giải đấu trước 24h';

    public function handle(NotificationService $notifications): int
    {
        // ---- 1) Kết quả trận mới trong 15 phút qua ----
        $recent = MatchGame::where('created_at', '>=', now()->subMinutes(15))
            ->with(['athlete1:id,full_name', 'athlete2:id,full_name'])
            ->get();

        $sent = 0;
        if ($recent->isNotEmpty()) {
            $athleteIds = $recent->flatMap(fn ($m) => [$m->athlete1_id, $m->athlete2_id])->unique();
            $follows = AthleteFollow::whereIn('athlete_id', $athleteIds)->with('athlete:id,full_name')->get();

            foreach ($follows as $follow) {
                $match = $recent->first(fn ($m) => $m->athlete1_id === $follow->athlete_id || $m->athlete2_id === $follow->athlete_id);
                if (! $match) continue;

                $won = ($match->athlete1_id === $follow->athlete_id) === ($match->score1 > $match->score2);
                $opponent = $match->athlete1_id === $follow->athlete_id ? $match->athlete2?->full_name : $match->athlete1?->full_name;

                $sent += $notifications->push(
                    $won ? '✅ Chiến thắng mới!' : '❌ Kết quả trận đấu',
                    "{$follow->athlete->full_name} ".($won ? 'thắng' : 'thua')." {$opponent} ".($match->walkover ? '(W.O.)' : "{$match->score1}-{$match->score2}"),
                    '/athletes/'.$follow->athlete_id,
                    $follow->user_id
                );
            }
        }
        $this->info("Push kết quả trận: {$sent} thiết bị.");

        // ---- 2) Nhắc giải đấu trước 24 giờ ----
        if ($this->option('tournaments')) {
            $upcoming = Tournament::whereBetween('start_date', [now()->toDateString(), now()->addDay()->toDateString()])->get();
            $tSent = 0;
            foreach ($upcoming as $t) {
                $tSent += $notifications->push(
                    '⏰ Giải đấu bắt đầu trong 24 giờ!',
                    "{$t->name} — kiểm tra checklist trang bị trước khi lên đường.",
                    "/tournaments/{$t->id}"
                );
            }
            $this->info("Nhắc giải 24h: {$tSent} thiết bị.");
        }

        return self::SUCCESS;
    }
}
