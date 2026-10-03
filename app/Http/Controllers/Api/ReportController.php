<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourtUsage;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Tab 'Báo cáo' (Admin) — Recharts:
 *  - Sản phẩm bán chạy (Bar)
 *  - Lượt đăng ký thành viên theo tuần / tháng (Line)
 *  - Tần suất sử dụng sân theo tuần / tháng (Bar)
 */
class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'top_products' => $this->topProducts(),
            'member_registrations' => [
                'weekly' => $this->registrations('week'),
                'monthly' => $this->registrations('month'),
            ],
            'court_usage' => [
                'weekly' => $this->courtUsage('week'),
                'monthly' => $this->courtUsage('month'),
            ],
        ]]);
    }

    private function topProducts(): array
    {
        return ProductSale::query()
            ->join('equipment_items', 'equipment_items.id', '=', 'product_sales.equipment_item_id')
            ->select('equipment_items.name', 'equipment_items.brand')
            ->selectRaw('SUM(product_sales.qty) as sold')
            ->selectRaw('SUM(product_sales.total_price) as revenue')
            ->groupBy('equipment_items.id', 'equipment_items.name', 'equipment_items.brand')
            ->orderByDesc('sold')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'brand' => $r->brand,
                'sold' => (int) $r->sold,
                'revenue' => (float) $r->revenue,
            ]);
    }

    /** Đăng ký thành viên theo tuần (8 tuần) hoặc tháng (6 tháng). */
    private function registrations(string $granularity): array
    {
        if ($granularity === 'week') {
            $start = now()->subWeeks(7)->startOfWeek();

            $rows = User::where('created_at', '>=', $start)
                ->selectRaw("YEARWEEK(created_at, 3) as bucket, COUNT(*) as total")
                ->groupBy('bucket')->pluck('total', 'bucket');
        } else {
            $start = now()->subMonths(5)->startOfMonth();

            $rows = User::where('created_at', '>=', $start)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bucket, COUNT(*) as total")
                ->groupBy('bucket')->pluck('total', 'bucket');
        }

        return $this->fillBuckets($granularity === 'week' ? $start : $start, $granularity, $rows);
    }

    /** Tần suất sử dụng sân: tổng giờ theo tuần/tháng. */
    private function courtUsage(string $granularity): array
    {
        if ($granularity === 'week') {
            $start = now()->subWeeks(7)->startOfWeek();

            $rows = CourtUsage::where('usage_date', '>=', $start)
                ->selectRaw("YEARWEEK(usage_date, 3) as bucket, SUM(hours) as total")
                ->groupBy('bucket')->pluck('total', 'bucket');
        } else {
            $start = now()->subMonths(5)->startOfMonth();

            $rows = CourtUsage::where('usage_date', '>=', $start)
                ->selectRaw("DATE_FORMAT(usage_date, '%Y-%m') as bucket, SUM(hours) as total")
                ->groupBy('bucket')->pluck('total', 'bucket');
        }

        return $this->fillBuckets($start, $granularity, $rows, decimals: true);
    }

    private function fillBuckets($start, string $granularity, $rows, bool $decimals = false): array
    {
        $out = [];
        $cursor = $start->copy();
        $end = now();

        while ($cursor <= $end) {
            $bucket = $granularity === 'week'
                ? $cursor->format('oW') // YEARWEEK mode 3 tương đương ISO
                : $cursor->format('Y-m');
            $label = $granularity === 'week'
                ? 'Tuần '.$cursor->format('W')
                : $cursor->format('m/Y');

            $value = $rows[$bucket] ?? ($decimals ? 0 : 0);
            $out[] = [
                'label' => $label,
                'total' => $decimals ? round((float) $value, 1) : (int) $value,
            ];

            $cursor->add($granularity === 'week' ? '1 week' : '1 month');
        }

        return $out;
    }
}
