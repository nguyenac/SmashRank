<?php

namespace App\Services;

use App\Models\Athlete;

/**
 * 'Coach Bot' — trợ lý huấn luyện viên nhúng trong ứng dụng.
 *
 * Phiên bản rule-based: phân tích radar chỉ số + trang bị + phong độ
 * để đưa ra tư vấn chiến thuật & gợi ý thay đổi thiết bị.
 * Có thể nâng cấp lên Gemini/OpenAI bằng cách cắm API key vào
 * generateWithAi() (để sẵn hook, mặc định trả về null → dùng rules).
 */
class CoachBotService
{
    public function advise(Athlete $athlete): array
    {
        // Hook AI (nếu có API key) — hiện trả về null, fallback rule-based
        $ai = $this->generateWithAi($athlete);
        if ($ai) {
            return $ai;
        }

        $d = $athlete->details;
        $skills = [
            'Sức mạnh' => $athlete->skill_power,
            'Nhanh nhẹn' => $athlete->skill_agility,
            'Sức bền' => $athlete->skill_stamina,
            'Kỹ thuật' => $athlete->skill_technique,
            'Phòng thủ' => $athlete->skill_defense,
            'Tâm lý' => $athlete->skill_mentality,
        ];
        asort($skills);
        $weakest = array_key_first($skills); // thấp nhất
        arsort($skills);
        $strongest = array_key_first($skills); // cao nhất

        $winRate = $athlete->win_rate;
        $advice = [];

        // 1) Đánh giá hình mẫu lối chơi
        $advice[] = "🔎 Hình mẫu lối chơi: " . ($d?->playing_style ?? 'chưa xác định')
            . " — điểm mạnh nhất là {$strongest} ({$skills[$strongest]}/100), điểm cần cải thiện là {$weakest} ({$skills[$weakest]}/100).";

        // 2) Chiến thuật dựa trên tỷ lệ thắng
        if ($winRate >= 60) {
            $advice[] = "⚔️ Với tỷ lệ thắng {$winRate}%: chủ động áp đặt thế trận, dùng điểm mạnh {$strongest} để tạo cơ hội sớm trong set 1.";
        } elseif ($winRate >= 45) {
            $advice[] = "⚖️ Tỷ lệ thắng {$winRate}%: chơi an toàn giai đoạn đầu game (till 11 điểm), tranh thủ điểm số ở loạt cầu dài.";
        } else {
            $advice[] = "🛡️ Tỷ lệ thắng {$winRate}%: ưu tiên giảm lỗi tự đánh hỏng, tập trung {$weakest} trong 4 tuần tới.";
        }

        // 3) Mổ xẻ theo chỉ số yếu
        $advice = array_merge($advice, match ($weakest) {
            'Sức mạnh' => ['💪 Lộ trình sức mạnh: jump smash 3x15 lần/buổi, tăng cường core & cổ tay với dumbbell nhẹ.'],
            'Nhanh nhẹn' => ['👟 Lộ trình bộ pháp: shadow footwork 6 góc 10 phút đầu giờ, bài chéo sân bắt cầu 2 set x 20.'],
            'Sức bền' => ['🫀 Lộ trình thể lực: interval 400m x 6, đa cầu phòng thủ 5 phút/set — đặc biệt cho set 3.'],
            'Kỹ thuật' => ['🎯 Lộ trình kỹ thuật: quay video đối chiếu động tác, luyện clear/drop/smash shadow 15 phút/buổi.'],
            'Phòng thủ' => ['🧱 Lộ trình phòng thủ: phản tạt góc trái tay 4x20 cầu, đứng vững thế thủ 10 giây/lượt.'],
            default => ['🧠 Lộ trình tâm lý: mô phỏng deuce 20-20 trong tập, thi đấu điểm quyết định 5 set/buổi.'],
        });

        // 4) Tư vấn trang bị
        $racket = $athlete->racket;
        $tension = $racket?->specifications['max_tension'] ?? null;
        $advice[] = $tension && $tension >= 29
            ? "🏸 Trang bị: {$racket->brand} {$racket->name} căng tối đa {$tension} lbs — nếu có vấn đề chấn thương cổ tay, cân nhắc hạ xuống 27 lbs."
            : '🏸 Trang bị hiện tại phù hợp; có thể xem gợi ý vợt tối ưu ở thẻ "Gợi ý vợt" bên dưới.';

        // 5) Dự báo cải thiện
        $gain = min(14, 5 + intdiv(100 - $skills[$weakest], 10));
        $advice[] = "📈 Nếu tuân thủ lộ trình trên, tỷ lệ thắng dự kiến cải thiện +{$gain}% trong 6-8 tuần.";

        $advice[] = '⚠️ Đây là gợi ý dựa trên dữ liệu hồ sơ — chỉ mang tính THAM KHẢO, hãy tham khảo HLV chuyên môn.';

        return $advice;
    }

    /** Hook AI: trả về null khi chưa cấu hình API key. */
    private function generateWithAi(Athlete $athlete): ?array
    {
        if (! config('services.gemini.api_key')) {
            return null;
        }
        // Tích hợp Gemini: POST generateContent với prompt hồ sơ VĐV.
        // Để trống vững bản rule-based an toàn; bổ sung sau khi có key.
        return null;
    }
}
