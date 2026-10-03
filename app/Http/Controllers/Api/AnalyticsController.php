<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\RankingHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Dashboard thống kê nhanh trang Admin:
     *  - Pie: phân bổ cấp độ kỹ năng (Beginner/Intermediate/Advanced/Pro)
     *  - Bar: số lượng thiết bị (vợt + giày) theo thương hiệu
     */
    public function quick(): JsonResponse
    {
        $skillDistribution = DB::table('athletes')
            ->select('skill_level', DB::raw('COUNT(*) as total'))
            ->groupBy('skill_level')->pluck('total', 'skill_level');

        // Pie phân nhóm trình độ phong trào (Newbie/TBY/TB/Khá/Giỏi/Xuất Sắc)
        $grassrootsDistribution = DB::table('athletes')
            ->whereNotNull('grassroots_rank')
            ->select('grassroots_rank', DB::raw('COUNT(*) as total'))
            ->groupBy('grassroots_rank')->pluck('total', 'grassroots_rank');

        $equipmentByBrand = DB::table('equipment_items')
            ->leftJoin('brands', 'brands.id', '=', 'equipment_items.brand_id')
            ->selectRaw("COALESCE(brands.name, equipment_items.brand, 'Khác') as brand")
            ->selectRaw("SUM(CASE WHEN equipment_items.type = 'racket' THEN 1 ELSE 0 END) as rackets")
            ->selectRaw("SUM(CASE WHEN equipment_items.type = 'shoes' THEN 1 ELSE 0 END) as shoes")
            ->selectRaw('COUNT(*) as total')
            ->groupBy(DB::raw("COALESCE(brands.name, equipment_items.brand, 'Khác')"))
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => [
                'skill_distribution' => $skillDistribution,
                'grassroots_distribution' => $grassrootsDistribution,
                'equipment_by_brand' => $equipmentByBrand,
            ],
        ]);
    }
    /**
     * Dashboard thống kê tổng hợp:
     *  - Số VĐV mới trong tuần / tháng
     *  - Phân bổ VĐV theo quốc gia (Pie Chart)
     *  - Top VĐV có Elo tăng trưởng mạnh nhất
     */
    public function summary(): JsonResponse
    {
        $newThisWeek = Athlete::where('created_at', '>=', now()->startOfWeek())->count();
        $newThisMonth = Athlete::where('created_at', '>=', now()->startOfMonth())->count();
        $totalAthletes = Athlete::count();
        $totalUsers = DB::table('users')->count();

        $countryDistribution = Athlete::query()
            ->select('nationality', 'country_code', DB::raw('COUNT(*) as total'))
            ->groupBy('nationality', 'country_code')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'country' => $row->nationality,
                'country_code' => $row->country_code,
                'total' => (int) $row->total,
            ]);

        // Elo tăng trưởng: so sánh elo tháng gần nhất với elo của tháng trước đó
        $growers = DB::table('ranking_histories as h1')
            ->join('athletes as a', 'a.id', '=', 'h1.athlete_id')
            ->join('ranking_histories as h0', function ($join) {
                $join->on('h0.athlete_id', '=', 'h1.athlete_id')
                    ->where('h0.recorded_month', '=', DB::raw('DATE_SUB(h1.recorded_month, INTERVAL 1 MONTH)'));
            })
            ->select(
                'a.id', 'a.full_name', 'a.country_code', 'a.elo_rating', 'a.avatar_url',
                'h1.elo_rating as elo_now',
                'h0.elo_rating as elo_prev',
                DB::raw('(h1.elo_rating - h0.elo_rating) as elo_growth')
            )
            ->orderByDesc('elo_growth')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'full_name' => $row->full_name,
                'country_code' => $row->country_code,
                'avatar_url' => $row->avatar_url,
                'elo_now' => (int) $row->elo_now,
                'elo_prev' => (int) $row->elo_prev,
                'elo_growth' => (int) $row->elo_growth,
            ]);

        $skillLevels = Athlete::query()
            ->select('skill_level', DB::raw('COUNT(*) as total'))
            ->groupBy('skill_level')
            ->pluck('total', 'skill_level');

        return response()->json([
            'data' => [
                'total_athletes' => $totalAthletes,
                'total_users' => $totalUsers,
                'new_this_week' => $newThisWeek,
                'new_this_month' => $newThisMonth,
                'country_distribution' => $countryDistribution,
                'top_elo_growers' => $growers,
                'skill_levels' => $skillLevels,
            ],
        ]);
    }
}
