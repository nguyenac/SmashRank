<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\CourtUsage;
use App\Models\EquipmentItem;
use App\Models\MatchGame;
use App\Models\News;
use App\Models\ProductSale;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Database\Seeder;

/**
 * Seeder cộng đồng/giải đấu: tin tức, trận đấu mẫu (cho leaderboard
 * tuần/tháng), nhánh đấu live-score, dữ liệu báo cáo (bán hàng, sử dụng sân).
 */
class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        // ---- Tin tức (nguồn thông báo đẩy) ----
        News::create([
            'title' => 'All England Open 2024: Axelsen bảo vệ chức vô địch',
            'body' => 'Viktor Axelsen đánh bại Kunlavut Vitidsarn 2-0 (21-15, 21-18) trong trận chung kết All England Open 2024. Đây là danh hiệu Super 1000 thứ ba trong năm của tay vợt Đan Mạch.',
            'source' => 'SmashRank',
            'published_at' => now()->subDays(2),
        ]);
        News::create([
            'title' => 'Cập nhật bảng xếp hạng Elo phong trào tháng này',
            'body' => 'Hệ thống đã áp dụng điều chỉnh giảm điểm chậm cho giai đoạn Covid và tăng nhẹ điểm cho VĐV thi đấu trở lại sau 2021. Chạy "php artisan elo:recalc" để cập nhật.',
            'source' => 'SmashRank',
            'published_at' => now()->subDays(1),
        ]);

        // ---- Trận đấu gần đây (leaderboard tuần/tháng) ----
        $athletes = Athlete::inRandomOrder()->limit(12)->get();
        $pairs = [];
        for ($i = 0; $i < $athletes->count() - 1; $i += 2) {
            $pairs[] = [$athletes[$i], $athletes[$i + 1]];
        }

        foreach ($pairs as $index => [$a1, $a2]) {
            // Xen kẽ: trận trong tuần này, tuần trước, tháng trước
            $playedAt = match ($index % 3) {
                0 => now()->subDays(rand(0, 5)),
                1 => now()->subDays(rand(8, 12)),
                default => now()->subDays(rand(25, 35)),
            };

            $score1 = rand(2, 2); // bo3: người thắng luôn 2
            $score2 = rand(0, 1);
            if ($index % 4 === 3) {
                $score1 = 2; $score2 = 0; // W.O. demo
            }

            app(\App\Services\MatchResultService::class)->record([
                'athlete1_id' => $a1->id,
                'athlete2_id' => $a2->id,
                'score1' => $score1,
                'score2' => $score2,
                'walkover' => $index % 4 === 3,
                'played_at' => $playedAt->toDateString(),
            ]);
        }

        // ---- Nhánh đấu live-score cho giải All England (đã seed) ----
        $allEngland = Tournament::where('name', 'like', 'All England%')->first();
        if ($allEngland && $allEngland->has_live_scores && $allEngland->matches()->count() === 0) {
            $top8 = Athlete::orderByDesc('elo_rating')->limit(8)->get();
            for ($i = 0; $i < 4; $i++) {
                TournamentMatch::create([
                    'tournament_id' => $allEngland->id,
                    'round' => 1,
                    'slot' => $i + 1,
                    'athlete1_id' => $top8[$i]->id,
                    'athlete2_id' => $top8[7 - $i]->id,
                    'score1' => $i === 0 ? 1 : 0, // trận đầu đang LIVE
                    'score2' => $i === 0 ? 1 : rand(0, 1),
                    'status' => $i === 0 ? 'live' : 'completed',
                    'starts_at' => now()->addMinutes(30 * $i),
                ]);
            }
        }

        // ---- Báo cáo: sản phẩm bán chạy ----
        $items = EquipmentItem::all();
        foreach ($items as $item) {
            for ($i = 0; $i < rand(2, 6); $i++) {
                $qty = rand(1, 5);
                ProductSale::create([
                    'equipment_item_id' => $item->id,
                    'qty' => $qty,
                    'total_price' => $qty * ($item->price ?? 1000000),
                    'sold_at' => now()->subDays(rand(0, 60))->toDateString(),
                ]);
            }
        }

        // ---- Báo cáo: tần suất sử dụng sân (8 tuần) ----
        foreach (['Sân 1', 'Sân 2', 'Sân 3', 'Sân 4'] as $courtIndex => $court) {
            for ($w = 0; $w < 8; $w++) {
                CourtUsage::create([
                    'court_name' => $court,
                    'usage_date' => now()->subWeeks($w)->setWeekday(3)->toDateString(),
                    'hours' => rand(8, 30),
                    'bookings' => rand(4, 20),
                ]);
            }
        }
    }
}
