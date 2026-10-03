<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrawlLog;
use App\Services\CrawlerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Khu vực Admin: crawl & đồng bộ dữ liệu.
 *  - VĐV: badmintonranks.com + đồng bộ bwfbadminton.com/rankings
 *  - Thương hiệu/sản phẩm: shopvnb.com + đồng bộ badmintoncn.com/cbo_eq/ (dịch EN→ZH)
 */
class CrawlController extends Controller
{
    public function __construct(private CrawlerService $crawler)
    {
    }

    public function crawlAthletes(): JsonResponse
    {
        $result = $this->crawler->crawlAthletesFromBadmintonRanks();
        $bwf = $this->crawler->syncWithBwfRankings();

        return response()->json([
            'data' => ['badmintonranks' => $result, 'bwf_sync' => $bwf],
            'message' => 'Đã crawl badmintonranks.com và đồng bộ bwfbadminton.com/rankings.',
        ]);
    }

    public function crawlProducts(): JsonResponse
    {
        $shopvnb = $this->crawler->crawlProductsFromShopVnb();
        $badmintoncn = $this->crawler->syncWithBadmintonCn();

        return response()->json([
            'data' => ['shopvnb' => $shopvnb, 'badmintoncn_sync' => $badmintoncn],
            'message' => 'Đã crawl shopvnb.com và đồng bộ badmintoncn.com (dịch EN→ZH).',
        ]);
    }

    public function logs(): JsonResponse
    {
        return response()->json([
            'data' => CrawlLog::latest()->limit(30)
                ->get(['id', 'target', 'source', 'items_found', 'items_upserted', 'status', 'message', 'created_at']),
        ]);
    }
}
