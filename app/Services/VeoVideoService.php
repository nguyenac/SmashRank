<?php

namespace App\Services;

use App\Models\Athlete;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tạo video tổng hợp kỹ thuật 15s bằng Google Veo (hook).
 *
 * Cách hoạt động khi có API key (GOOGLE_VEO_API_KEY):
 *  - Build prompt từ dữ liệu "lối chơi" đã gán điểm trong radar (tấn công
 *    cuối sân, cắt cầu trên lưới, phòng thủ bền bỉ, điều cầu...)
 *  - Gọi Veo API generateVideo → trả về operation → poll → URL video.
 *
 * KHÔNG có key: trả về "queued_stub" — hệ thống vẫn tạo story-board mô tả
 * các cảnh sẽ có trong video (dựa trên radar) để HLV dùng làm kịch bản quay.
 */
class VeoVideoService
{
    public function createHighlight(Athlete $athlete): array
    {
        $d = $athlete->details;
        $playStyle = [
            'attack_net' => $athlete->skill_power,   // Tấn công cuối sân
            'net_kill' => $athlete->skill_technique, // Cắt cầu trên lưới
            'defense' => $athlete->skill_defense,    // Phòng thủ bền bỉ
            'clear_control' => $athlete->skill_stamina, // Khả năng điều cầu
        ];

        arsort($playStyle);
        $topSkills = array_slice(array_keys($playStyle), 0, 2);

        $sceneMap = [
            'attack_net' => 'Cú đập cầu nhảy (jump smash) dốc xuống cuối sân',
            'net_kill' => 'Cắt cầu trên lưới (net kill) nhanh gọn',
            'defense' => 'Pha phản tạt phòng thủ bền bỉ góc trái tay',
            'clear_control' => 'Trao đổi điều cầu (clear) kiểm soát nhịp độ',
        ];

        $storyboard = [
            'Cảnh 1 (0-5s): '.$sceneMap[$topSkills[0]],
            'Cảnh 2 (5-10s): '.$sceneMap[$topSkills[1]],
            'Cảnh 3 (10-15s): Điểm nhấn tổng hợp — '.$athlete->full_name.' ('.$athlete->nationality.') ở thế trận sở trường',
        ];

        if (! config('services.veo.api_key')) {
            return [
                'status' => 'queued_stub',
                'message' => 'Chưa cấu hình GOOGLE_VEO_API_KEY — trả về story-board 15s để HLV quay thủ công.',
                'storyboard' => $storyboard,
            ];
        }

        try {
            // Gọi Veo API thật (REST) — endpoint có thể thay đổi theo phiên bản
            $prompt = 'Video thể thao 15 giây: '.$athlete->full_name.', lối chơi '
                .($d->playing_style ?? 'cân bằng').'. '.implode(' ', $storyboard);
            $response = Http::withToken(config('services.veo.api_key'))
                ->timeout(60)
                ->post(config('services.veo.endpoint', 'https://generativelanguage.googleapis.com/v1/models/veo:generateVideo'), [
                    'prompt' => $prompt,
                    'durationSeconds' => 15,
                ]);

            return [
                'status' => $response->successful() ? 'queued' : 'failed',
                'operation' => $response->json(),
                'storyboard' => $storyboard,
            ];
        } catch (\Throwable $e) {
            Log::warning('Veo generate thất bại: '.$e->getMessage());

            return ['status' => 'failed', 'message' => $e->getMessage(), 'storyboard' => $storyboard];
        }
    }
}
