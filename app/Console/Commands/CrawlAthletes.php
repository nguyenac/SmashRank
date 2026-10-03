<?php

namespace App\Console\Commands;

use App\Services\CrawlerService;
use Illuminate\Console\Command;

class CrawlAthletes extends Command
{
    protected $signature = 'crawl:athletes {--force : Chạy cả khi AUTO_CRAWL chưa bật}';

    protected $description = 'Crawl VĐV từ badmintonranks.com + đồng bộ bwfbadminton.com/rankings';

    public function handle(CrawlerService $service): int
    {
        if (env('AUTO_CRAWL_ENABLED') !== 'true' && ! $this->option('force')) {
            $this->info('Crawl tự động đang tắt (AUTO_CRAWL_ENABLED != true). Dùng --force để chạy thủ công.');

            return self::SUCCESS;
        }

        $this->info('Crawling badmintonranks.com...');
        $r = $service->crawlAthletesFromBadmintonRanks();
        $this->info("badmintonranks: {$r['upserted']}/{$r['found']}");

        $this->info('Syncing bwfbadminton.com/rankings...');
        foreach (['MS', 'WS', 'MD', 'WD', 'XD'] as $cat) {
            $b = $service->syncWithBwfRankings($cat);
            $this->info("BWF {$cat}: {$b['upserted']}/{$b['found']}");
        }

        return self::SUCCESS;
    }
}
