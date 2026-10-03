<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\BackupSetting;
use App\Models\EquipmentItem;
use App\Models\RankingHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------
        // Tài khoản
        // ------------------------------------------------------------------
        $admin = User::create([
            'name' => 'Admin SmashRank',
            'email' => 'admin@smashrank.local',
            'password' => Hash::make('Admin@123456'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Người chơi demo',
            'email' => 'player@smashrank.local',
            'password' => Hash::make('Player@123456'),
            'role' => 'user',
        ]);

        // ------------------------------------------------------------------
        // Trang thiết bị (vợt & giày)
        // ------------------------------------------------------------------
        $rackets = collect([
            ['name' => 'Astrox 100ZZ', 'brand' => 'Yonex', 'model' => 'AX100ZZ', 'specs' => ['weight' => '4U', 'balance_point' => 'head_heavy', 'shaft_flexibility' => 'extra_stiff', 'max_tension' => 29]],
            ['name' => 'Thruster Ryuga II', 'brand' => 'Victor', 'model' => 'TK-RYUGA II', 'specs' => ['weight' => '4U', 'balance_point' => 'head_heavy', 'shaft_flexibility' => 'stiff', 'max_tension' => 32]],
            ['name' => 'Aeronaut 9000C', 'brand' => 'Li-Ning', 'model' => 'AYPQ403', 'specs' => ['weight' => '3U', 'balance_point' => 'head_heavy', 'shaft_flexibility' => 'stiff', 'max_tension' => 30]],
            ['name' => 'Nanoflare 800 Pro', 'brand' => 'Yonex', 'model' => 'NF800PRO', 'specs' => ['weight' => '5U', 'balance_point' => 'head_light', 'shaft_flexibility' => 'medium', 'max_tension' => 28]],
            ['name' => 'DriveX 9900', 'brand' => 'Victor', 'model' => 'DX-9900', 'specs' => ['weight' => '4U', 'balance_point' => 'even', 'shaft_flexibility' => 'flexible', 'max_tension' => 27]],
        ])->mapWithKeys(fn ($r) => [$r['name'] => EquipmentItem::create([
            'name' => $r['name'], 'type' => 'racket', 'brand' => $r['brand'],
            'model' => $r['model'], 'specifications' => $r['specs'],
            'description' => 'Vợt cầu lông chuyên nghiệp '.$r['brand'].' '.$r['name'].'.',
            'price' => rand(1800000, 4200000),
        ])]);

        $shoes = collect([
            ['name' => 'Power Cushion 65 Z3', 'brand' => 'Yonex', 'model' => 'SHB65Z3', 'specs' => ['cushion' => 'Power Cushion+', 'grip' => 'High']],
            ['name' => 'P9500TD', 'brand' => 'Victor', 'model' => 'SH-P9500TD', 'specs' => ['cushion' => 'EnergyMax 3.0', 'grip' => 'RubberEra']],
            ['name' => 'Blade Pro', 'brand' => 'Li-Ning', 'model' => 'AYTQ041', 'specs' => ['cushion' => 'Cloud Foam', 'grip' => 'TPU']],
        ])->mapWithKeys(fn ($s) => [$s['name'] => EquipmentItem::create([
            'name' => $s['name'], 'type' => 'shoes', 'brand' => $s['brand'],
            'model' => $s['model'], 'specifications' => $s['specs'],
            'description' => 'Giày cầu lông '.$s['brand'].' '.$s['name'].'.',
            'price' => rand(900000, 2500000),
        ])]);

        // ------------------------------------------------------------------
        // Vận động viên mẫu (dữ liệu có căn cứ theo BWF World Tour)
        // ------------------------------------------------------------------
        $athletes = [
            ['Viktor Axelsen', 'VAX', 'Denmark', 'DEN', 'MS', 116236, 1, 1, 420, 120, 'pro', 'right', 'Astrox 100ZZ', 'Power Cushion 65 Z3', 96, 97, 88, 95, 90, 92],
            ['Kunlavut Vitidsarn', 'KUN', 'Thailand', 'THA', 'MS', 98450, 4, 3, 310, 150, 'pro', 'right', 'Thruster Ryuga II', 'P9500TD', 90, 82, 95, 93, 96, 90],
            ['Anders Antonsen', 'AND', 'Denmark', 'DEN', 'MS', 95120, 5, 2, 300, 140, 'pro', 'right', 'Nanoflare 800 Pro', 'Power Cushion 65 Z3', 89, 90, 84, 92, 85, 88],
            ['Loh Kean Yew', 'LOH', 'Singapore', 'SGP', 'MS', 88900, 8, 1, 280, 160, 'pro', 'right', 'Astrox 100ZZ', 'P9500TD', 92, 93, 86, 88, 84, 85],
            ['An Se-young', 'ASY', 'South Korea', 'KOR', 'WS', 108320, 1, 1, 400, 110, 'pro', 'right', 'Aeronaut 9000C', 'Blade Pro', 94, 85, 96, 94, 95, 93],
            ['Chen Yu Fei', 'CYF', 'China', 'CHN', 'WS', 101250, 2, 1, 380, 120, 'pro', 'right', 'Aeronaut 9000C', 'Blade Pro', 91, 84, 93, 92, 93, 91],
            ['Tai Tzu-ying', 'TTY', 'Chinese Taipei', 'TPE', 'WS', 96780, 4, 1, 350, 130, 'pro', 'left', 'Nanoflare 800 Pro', 'Power Cushion 65 Z3', 88, 80, 88, 97, 87, 94],
            ['Carolina Marin', 'CRM', 'Spain', 'ESP', 'WS', 93100, 6, 1, 330, 145, 'pro', 'right', 'Thruster Ryuga II', 'P9500TD', 90, 92, 89, 90, 88, 96],
            ['Kevin Sanjaya Sukamuljo', 'KEV', 'Indonesia', 'IDN', 'MD', 91500, 3, 1, 320, 135, 'pro', 'right', 'Astrox 100ZZ', 'P9500TD', 97, 90, 85, 94, 82, 87],
            ['Nguyễn Tiến Minh', 'NTM', 'Việt Nam', 'VNM', 'MS', 42300, 55, 22, 210, 180, 'advanced', 'right', 'DriveX 9900', 'Power Cushion 65 Z3', 84, 82, 88, 90, 86, 93],
            ['Nguyễn Thùy Linh', 'NTL', 'Việt Nam', 'VNM', 'WS', 45800, 38, 30, 220, 165, 'advanced', 'right', 'DriveX 9900', 'P9500TD', 82, 78, 86, 87, 84, 89],
            ['Lê Đức Phát', 'LDP', 'Việt Nam', 'VNM', 'MS', 38700, 72, 60, 190, 150, 'intermediate', 'right', 'Nanoflare 800 Pro', 'Blade Pro', 76, 74, 80, 78, 76, 80],
        ];

        foreach ($athletes as $row) {
            [$name, $nick, $nation, $cc, $cat, $points, $rank, $high, $wins, $losses, $skill, $hand, $racket, $shoe, $ag, $pw, $st, $te, $de, $me] = $row;

            $athlete = Athlete::create([
                'full_name' => $name,
                'nickname' => $nick,
                'nationality' => $nation,
                'country_code' => $cc,
                'category' => $cat,
                'ranking_points' => $points,
                'world_rank' => $rank,
                'career_high_rank' => $high,
                'win_count' => $wins,
                'loss_count' => $losses,
                'dominant_hand' => $hand,
                'skill_level' => $skill,
                'racket_id' => $rackets[$racket]?->id,
                'shoes_id' => $shoes[$shoe]?->id,
                'club' => 'SmashRank Demo Club',
                'birth_year' => rand(1994, 2002),
                'elo_rating' => 1000 + $points / 400,
                'verified' => $skill === 'pro',
                'skill_agility' => $ag, 'skill_power' => $pw, 'skill_stamina' => $st,
                'skill_technique' => $te, 'skill_defense' => $de, 'skill_mentality' => $me,
            ]);

            // Lịch sử 12 tháng cho biểu đồ đường (dữ liệu mẫu mô phỏng xu hướng)
            $elo = 1000 + $points / 400;
            $pts = max(1000, $points * 0.6);
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i)->startOfMonth();
                $pts += ($points - $pts) * 0.12 + rand(-2000, 2500);
                $elo += rand(-25, 45);
                RankingHistory::create([
                    'athlete_id' => $athlete->id,
                    'recorded_month' => $month->toDateString(),
                    'points' => (int) max(1000, $pts),
                    'elo_rating' => (int) max(800, $elo),
                    'win_rate' => rand(45, 75),
                ]);
            }
            $athlete->update(['elo_rating' => (int) max(800, $elo)]);
        }

        // Người chơi demo gắn với hồ sơ phong trào
        Athlete::create([
            'user_id' => $admin->id,
            'full_name' => 'Admin SmashRank',
            'nationality' => 'Việt Nam',
            'country_code' => 'VNM',
            'category' => 'MS',
            'skill_level' => 'intermediate',
            'elo_rating' => 1280,
            'grassroots_rank' => 'Khá',
            'club' => 'SmashRank Demo Club',
        ]);

        BackupSetting::create(['frequency' => 'daily', 'enabled' => true]);

        // Dữ liệu mở rộng: thương hiệu, chi tiết VĐV, giải đấu, kỷ lục, GOAT
        $this->call(ExpansionSeeder::class);

        // Cộng đồng: tin tức, trận đấu (leaderboard), live-score, báo cáo
        $this->call(CommunitySeeder::class);

        // Sân, thư viện bài tập, heatmap, snapshot kỹ năng
        $this->call(CoachSeeder::class);
    }
}
