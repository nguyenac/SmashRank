<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtUsage;
use App\Models\Drill;
use App\Models\SkillSnapshot;
use App\Models\Athlete;
use Illuminate\Database\Seeder;

/**
 * Seeder sân & bài tập: sân cầu lông, thư viện drills mẫu (có mô tả cụ thể
 * bộ pháp/kỹ thuật/tư duy), dữ liệu đặt sân theo giờ (heatmap), snapshot kỹ năng.
 */
class CoachSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Sân ----
        foreach (['Sân 1', 'Sân 2', 'Sân 3', 'Sân 4'] as $i => $name) {
            Court::create(['name' => $name, 'open_hour' => 6, 'close_hour' => 22]);
        }

        // ---- Dữ liệu đặt sân theo giờ (8 tuần x 7 ngày) cho heatmap + scheduler ----
        foreach (range(0, 7) as $week) {
            foreach (range(0, 6) as $weekday) {
                foreach ([18, 19, 20, 8, 9] as $hour) { // giờ vàng + buổi sáng
                    CourtUsage::create([
                        'court_name' => 'Sân '.rand(1, 4),
                        'usage_date' => now()->subWeeks($week)->startOfWeek()->addDays($weekday)->toDateString(),
                        'hour' => $hour,
                        'hours' => rand(2, 8) + ($hour >= 18 ? 6 : 0), // giờ vàng đông hơn
                        'bookings' => rand(1, 6) + ($hour >= 18 ? 3 : 0),
                    ]);
                }
            }
        }

        // ---- Thư viện bài tập mẫu (mô tả cụ thể bộ pháp, kỹ thuật, tư duy) ----
        $drills = [
            ['Shadow footwork 6 góc', 'Bắt chước di chuyển không cầu đến 6 góc sân (2 góc lưới, 2 góc giữa, 2 góc cuối). Chú ý: bước chậm (split step) trước khi rời, bước cuối gót chạm trước mũi, quay về tâm sân bằng 2 bước nhỏ. Tư duy: luôn dự đoán hướng cầu trước khi đối thủ đánh.', 'footwork', 'easy', 10],
            ['Đa cầu góc lùi trái tay', 'HLV giao 20 cầu liên hoàn vào góc trái tay. Kỹ thuật: ép khuỷu cao, dùng cổ tay bẻ cầu chéo về 2 góc đối diện. Bộ pháp: lùi bước chéo về sau, không quay lưng vào lưới. Tư duy: kéo đối thủ ra cuối sân rồi phản công.', 'defense', 'medium', 20],
            ['Jump smash vào thùng cầu', 'Nhảy đập cầu vào thùng đặt 3/4 sân, 3 hiệp x 15 lần. Kỹ thuật: treo người ở điểm cao nhất, xoay hông → vai → khuỷu → cổ tay (chuỗi động lực). Tư duy: đập vào vị trí đối thủ khó đỡ (thân giữa hoặc vai tay cầm vợt).', 'attack', 'hard', 25],
            ['Net kill phản xạ', 'Đứng sát lưới, HLV ném cầu qua lưới thấp — cắt cầu xuống ngay. 4 set x 15. Kỹ thuật: vợt đưa cao trước ngực, chỉ dùng cổ tay + ngón tay. Tư duy: đứng chệch 1 bước về phía sau để có thời gian phản ứng.', 'technique', 'medium', 15],
            ['Clear chân đế sân (điều cầu)', 'Trao đổi clear từ chân đế sân này sang chân đế kia, 5 phút/liên tục. Kỹ thuật: đánh cao và sâu nhất có thể, điểm chạm trên đầu. Tư duy: dùng clear để kéo đối thủ ra sau rồi giảm nhịp đập bỏ nhỏ.', 'stamina', 'easy', 15],
            ['Bắt cầu đa hướng buồng chân', 'Đứng tâm sân, HLV giao cầu 6 góc ngẫu nhiên — chỉ di chuyển không đánh, chạm khuôn vợt vào cầu. 3 set x 90 giây. Tư duy: đọc tay quay vai của HLV để đoán hướng sớm 0.2 giây.', 'footwork', 'medium', 15],
            ['Deuce 20-20 mô phỏng', 'Thi đấu điểm quyết định từ tỉ số 20-20, 5 set mỗi buổi. Tư duy: chọn phương án đầu tiên nghĩ tới — do dự là mất điểm; hít sâu 3 nhịp trước khi phát.', 'technique', 'hard', 20],
            ['Phản tạt phòng thủ 2 góc', 'HLV đập cầu vào 2 góc thân người, tập phản tạt về 4 góc đối diện. 4 set x 20 cầu. Kỹ thuật: cầm vợt ngắn, đứng thấp, dùng cổ tay linh hoạt. Tư duy: đỡ xong lập tức đẩy cầu về sau đối thủ.', 'defense', 'medium', 20],
            ['Drop shot cắt chéo', 'Từ cuối sân cắt cầu chéo về gần lưới, 3 hiệp x 20. Kỹ thuật: giảm tốc swing ở khoảnh khắc chạm, dùng mặt vợt nghiêng 45°. Tư duy: cắt khi đối thủ đứng sâu hơn 1m so với đường cuối.', 'attack', 'medium', 20],
            ['Core & plank cầu lông', 'Plank 3x60s, side plank 2x45s mỗi bên, bird-dog 2x12. Mục đích: ổn định lõi khi vung vợt và xoay người đập cầu.', 'stamina', 'easy', 12],
        ];

        foreach ($drills as [$title, $detail, $cat, $diff, $dur]) {
            Drill::create([
                'title' => $title, 'detail' => $detail, 'category' => $cat,
                'difficulty' => $diff, 'duration_min' => $dur, 'is_reference' => true,
            ]);
        }

        // ---- Snapshot kỹ năng ban đầu cho các VĐV (radar tiến triển) ----
        foreach (Athlete::take(8)->get() as $index => $athlete) {
            SkillSnapshot::create([
                'athlete_id' => $athlete->id,
                'recorded_at' => now()->subMonths(3)->toDateString(),
                'power' => max(10, $athlete->skill_power - rand(2, 8)),
                'speed' => max(10, $athlete->skill_agility - rand(2, 8)),
                'defense' => max(10, $athlete->skill_defense - rand(2, 8)),
                'net_play' => max(10, $athlete->skill_technique - rand(2, 8)),
                'stamina' => max(10, $athlete->skill_stamina - rand(2, 8)),
            ]);
            SkillSnapshot::create([
                'athlete_id' => $athlete->id,
                'recorded_at' => now()->toDateString(),
                'power' => $athlete->skill_power,
                'speed' => $athlete->skill_agility,
                'defense' => $athlete->skill_defense,
                'net_play' => $athlete->skill_technique,
                'stamina' => $athlete->skill_stamina,
            ]);
        }
    }
}
