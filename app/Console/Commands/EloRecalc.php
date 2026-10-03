<?php

namespace App\Console\Commands;

use App\Services\EloService;
use Illuminate\Console\Command;

class EloRecalc extends Command
{
    protected $signature = 'elo:recalc';

    protected $description = 'Tính lại chuỗi điểm ELO với điều chỉnh Covid (giảm điểm chậm khi vắng mặt 2020-2021, tăng nhẹ sau 2021)';

    public function handle(EloService $service): int
    {
        $this->info('Đang tính lại điểm ELO cho toàn bộ vận động viên...');

        $stats = $service->recalcAll();

        $this->table(['Chỉ số', 'Giá trị'], [
            ['VĐV đã xử lý', $stats['athletes']],
            ['Tháng áp dụng Covid decay', $stats['decayed']],
            ['Tháng áp dụng post-2021 boost', $stats['boosted']],
        ]);

        return self::SUCCESS;
    }
}
