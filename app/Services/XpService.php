<?php

namespace App\Services;

use App\Models\User;

/**
 * Hệ thống 'Điểm kinh nghiệm' (XP) và 'Danh hiệu' (Badge).
 * Ghi trực tiếp vào users.xp / users.badges.
 *
 * Cách nhận XP: bình luận (+5), ghi kết quả trận (+15), hoàn thành mục tiêu
 * tập luyện (+20), đóng góp bài tập vào thư viện (+10)...
 */
class XpService
{
    public const AWARDS = [
        'comment' => 5,
        'match_result' => 15,
        'goal_completed' => 20,
        'drill_created' => 10,
    ];

    private const BADGES = [
        50 => ['Tân binh Nhiệt huyết', '🥉'],
        150 => ['Người chơi Tích cực', '🥈'],
        300 => ['Cầu thủ Tinh hoa', '🥇'],
        600 => ['Chuyên gia Cầu lông', '🏆'],
        1000 => ['Người dẫn đầu', '👑'],
    ];

    /** Cộng XP và trả về danh sách badge MỚI mở khóa (nếu có). */
    public function award(User $user, string $reason): array
    {
        $amount = self::AWARDS[$reason] ?? 0;
        if ($amount === 0) {
            return [];
        }

        $before = $user->xp;
        $user->xp = $before + $amount;
        $this->syncBadges($user);
        $user->save();

        $newBadges = [];
        foreach (self::BADGES as $threshold => [$name, $icon]) {
            if ($before < $threshold && $user->xp >= $threshold) {
                $newBadges[] = ['name' => $name, 'icon' => $icon];
            }
        }

        return $newBadges;
    }

    private function syncBadges(User $user): void
    {
        $unlocked = [];
        foreach (self::BADGES as $threshold => [$name, $icon]) {
            if ($user->xp >= $threshold) {
                $unlocked[] = ['name' => $name, 'icon' => $icon, 'at_xp' => $threshold];
            }
        }
        $user->badges = $unlocked;
    }
}
