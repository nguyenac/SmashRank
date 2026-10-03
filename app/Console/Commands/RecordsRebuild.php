<?php

namespace App\Console\Commands;

use App\Services\RecordsService;
use Illuminate\Console\Command;

class RecordsRebuild extends Command
{
    protected $signature = 'records:rebuild';

    protected $description = 'Tính lại toàn bộ bảng [Kỷ lục] và điểm GOAT (theo quy tắc mới: bỏ H2H/Win% points, Asian Games = 40)';

    public function handle(RecordsService $service): int
    {
        $this->info('Đang rebuild bảng Kỷ lục...');

        $stats = $service->rebuild();

        $this->info("Hoàn tất: {$stats['records']} kỷ lục đã được cập nhật + điểm GOAT đã tính lại.");

        return self::SUCCESS;
    }
}
