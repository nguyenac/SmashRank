<?php

namespace App\Console\Commands;

use App\Models\Athlete;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Giữ chân người chơi: push thông báo khi VĐV/người dùng không hoạt động ≥ 3 ngày
 * (không có trận, không ghi cân nặng, không check-in lịch tập).
 */
class NotifyInactive extends Command
{
    protected $signature = 'notify:inactive {--days=3 : Số ngày không hoạt động}';

    protected $description = 'Nhắc người dùng quay lại khi ngừng hoạt động nhiều ngày (streak断了)';

    public function handle(NotificationService $notifications): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days)->toDateString();

        // Người dùng có hồ sơ VĐV nhưng không có hoạt động nào gần đây
        $inactive = DB::table('users as u')
            ->join('athletes as a', 'a.user_id', '=', 'u.id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('matches as m')
                ->where(function ($w) {
                    $w->whereColumn('m.athlete1_id', 'a.id')->orWhereColumn('m.athlete2_id', 'a.id');
                })
                ->where('m.played_at', '>=', $cutoff))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('weight_entries as w')
                ->whereColumn('w.athlete_id', 'a.id')
                ->where('w.measured_at', '>=', $cutoff))
            ->get(['u.id', 'u.name', 'a.id as athlete_id', 'a.full_name', 'a.elo_rating']);

        $sent = 0;
        foreach ($inactive as $row) {
            $sent += $notifications->push(
                '🔥 Chuỗi của bạn đang chờ!',
                "{$row->full_name}, đã {$days} ngày không hoạt động. Quay lại sân để giữ chuỗi và Elo {$row->elo_rating}!",
                '/dashboard',
                $row->id
            );
        }

        $this->info("Đã gửi {$sent} thông báo cho ".count($inactive)." người dùng không hoạt động (≥ {$days} ngày).");

        return self::SUCCESS;
    }
}
