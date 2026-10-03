<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\AthleteDetail;
use App\Models\AthleteYearStat;
use App\Models\Brand;
use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Services\RecordsService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder mở rộng: thương hiệu, chi tiết VĐV (sinh nhật/thành tích/chuỗi thắng),
 * giải đấu + kết quả, thống kê theo năm — rồi rebuild Kỷ lục & điểm GOAT.
 */
class ExpansionSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------
        // Thương hiệu
        // ------------------------------------------------------------------
        $brands = collect([
            ['Yonex', 'Nhật Bản'], ['Victor', 'Đài Loan'], ['Li-Ning', 'Trung Quốc'],
            ['Mizuno', 'Nhật Bản'], ['Kawasaki', 'Trung Quốc'],
        ])->mapWithKeys(fn ($b) => [strtoupper($b[0]) => Brand::create([
            'name' => $b[0],
            'slug' => \Illuminate\Support\Str::slug($b[0]),
            'country' => $b[1],
            'description' => 'Thương hiệu trang thiết bị cầu lông '.$b[0].'.',
        ])]);

        // Gán brand_id cho sản phẩm seed cũ
        foreach (['YONEX', 'VICTOR', 'LI-NING'] as $key) {
            if (isset($brands[$key])) {
                \App\Models\EquipmentItem::where('brand', $brands[$key]->name)
                    ->update(['brand_id' => $brands[$key]->id]);
            }
        }
        $otherBrand = Brand::create(['name' => 'Khác', 'slug' => 'khac']);
        \App\Models\EquipmentItem::whereNull('brand_id')->update(['brand_id' => $otherBrand->id]);

        // ------------------------------------------------------------------
        // Chi tiết VĐV: sinh nhật (vài VĐV sinh nhật HÔM NAY để demo),
        // thành tích sự nghiệp, chuỗi trận thắng, điểm GOAT tạm
        // ------------------------------------------------------------------
        $today = now();
        foreach (Athlete::all() as $index => $athlete) {
            // Mỗi VĐV thứ 5 có sinh nhật hôm nay (demo "Sinh nhật hôm nay")
            $birthDate = ($index % 5 === 0)
                ? $today->copy()->subYears(rand(20, 30))->setDate($today->year, $today->month, $today->day)
                : Carbon::create(rand(1990, 2004), rand(1, 12), rand(1, 28));

            AthleteDetail::create([
                'athlete_id' => $athlete->id,
                'birth_date' => $birthDate->toDateString(),
                'height_cm' => rand(165, 195),
                'weight_kg' => rand(60, 88),
                'playing_style' => collect(['Tấn công uy lực', 'Phòng thủ phản tạt', 'Điều cầu bền bỉ'])->random(),
                'coach' => 'HLV '.collect(['Park', 'Kenneth', 'Mulyo', 'Vũ'])->random(),
                'association' => 'Hiệp hội '.collect($athlete->nationality)->first(),
                'titles' => rand(2, 35),
                'finals' => rand(4, 50),
                'total_matches' => rand(150, 600),
                'total_wins' => rand(80, 420),
                'win_streak_current' => rand(0, 12),
                'win_streak_career' => rand(10, 45),
                'win_streak_career_excl_wo' => rand(8, 40),
                'super_streak' => rand(0, 25),
                'not_played_matches' => rand(0, 15),
            ]);

            // Thống kê theo năm (3 năm gần nhất) — cho các kỷ lục "trong năm"
            foreach ([now()->year - 2, now()->year - 1, now()->year] as $year) {
                $matches = rand(30, 95);
                $wins = (int) round($matches * (rand(45, 85) / 100));
                AthleteYearStat::create([
                    'athlete_id' => $athlete->id,
                    'year' => $year,
                    'matches' => $matches,
                    'wins' => $wins,
                    'losses' => $matches - $wins,
                    'finals' => rand(0, 10),
                    'titles' => rand(0, 8),
                ]);
            }
        }

        // ------------------------------------------------------------------
        // Giải đấu mẫu + kết quả (cho kỷ lục VĐ trẻ nhất / lớn tuổi nhất)
        // ------------------------------------------------------------------
        $tournaments = [
            ['All England Open 2024', 'super1000', false],
            ['Malaysia Open 2024', 'super1000', false],
            ['China Open 2024', 'super1000', false],
            ['Denmark Open 2024', 'super750', false],
            ['Asian Games 2022 — Cầu lông', 'other', true], // Asian Games (GOAT = 40)
            ['Vietnam Challenge 2024', 'super300', false],
        ];
        $levels = collect($tournaments)->mapWithKeys(function ($t) {
            $tournament = Tournament::create([
                'name' => $t[0],
                'level' => $t[1],
                'is_asian_games' => $t[2],
                'has_live_scores' => str_contains($t[0], 'All England') || str_contains($t[0], 'Malaysia'),
                'start_date' => now()->subMonths(rand(2, 10))->toDateString(),
                'end_date' => now()->subMonths(rand(1, 2))->toDateString(),
            ]);

            return [$t[0] => $tournament];
        });

        foreach ($levels as $name => $tournament) {
            $champion = Athlete::inRandomOrder()->first();
            $finalist = Athlete::where('id', '!=', $champion->id)->inRandomOrder()->first();
            TournamentWinner::create([
                'tournament_id' => $tournament->id,
                'athlete_id' => $champion->id,
                'category' => $champion->category,
                'placement' => 'champion',
                'achieved_at' => $tournament->start_date,
            ]);
            TournamentWinner::create([
                'tournament_id' => $tournament->id,
                'athlete_id' => $finalist->id,
                'category' => $finalist->category,
                'placement' => 'finalist',
                'achieved_at' => $tournament->start_date,
            ]);
        }

        // ------------------------------------------------------------------
        // Rebuild Kỷ lục + điểm GOAT theo quy tắc mới
        // ------------------------------------------------------------------
        $this->command?->info('Đang rebuild Kỷ lục & điểm GOAT...');
        app(RecordsService::class)->rebuild();
    }
}
