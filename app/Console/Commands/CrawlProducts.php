<?php

namespace App\Console\Commands;

use App\Services\CrawlerService;
use Illuminate\Console\Command;

class CrawlProducts extends Command
{
    protected $signature = 'crawl:products {--force : Chạy cả khi AUTO_CRAWL chưa bật}';

    protected $description = 'Crawl sản phẩm từ shopvnb.com + đồng bộ badmintoncn.com/cbo_eq/ (dịch EN→ZH)';

    public function handle(CrawlerService $service): int
    {
        if (env('AUTO_CRAWL_ENABLED') !== 'true' && ! $this->option('force')) {
            $this->info('Crawl tự động đang tắt (AUTO_CRAWL_ENABLED != true). Dùng --force để chạy thủ công.');

            return self::SUCCESS;
        }

        $this->info('Crawling shopvnb.com...');
        $s = $service->crawlProductsFromShopVnb();
        $this->info("shopvnb: {$s['upserted']}/{$s['found']}");

        $this->info('Syncing badmintoncn.com/cbo_eq/ (EN→ZH)...');
        $b = $service->syncWithBadmintonCn();
        $this->info("badmintoncn: {$b['upserted']}/{$b['found']}");

        return self::SUCCESS;
    }
}
