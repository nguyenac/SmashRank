<?php

/*
|--------------------------------------------------------------------------
| Quy tắc tính điểm GOAT — SmashRank
|--------------------------------------------------------------------------
| Điểm GOAT = tổng điểm danh hiệu theo cấp độ giải + điểm kỷ lục.
| Lưu ý: mục "Điểm GOAT đối đầu (Head-to-Head Goat Points)" và
| "Điểm tỷ lệ thắng (Winning Percent Points)" ĐÃ BỊ LOẠI BỎ khỏi quy tắc
| (chỉ còn danh hiệu + kỷ lục).
*/

return [
    // Điểm vô địch theo cấp độ giải
    'champion_points' => [
        'super1000' => 120,
        'super750' => 90,
        'super500' => 65,
        'super300' => 40,
        'super100' => 25,
        'other' => 15,
    ],

    // Điểm á quân (finalist) — hệ số của điểm vô địch
    'finalist_factor' => 0.5,

    // Asian Games: vô địch giảm từ 45 xuống 40 điểm
    'asian_games_champion_points' => 40,

    // Điểm kỷ lục (khi giữ kỷ lục tương ứng)
    'record_bonus' => [
        'win_rate_year' => 30,
        'streak_career' => 40,
        'streak_super' => 35,
        'titles_year' => 25,
        'finals_year' => 15,
        'wins_year' => 20,
        'matches_year' => 10,
        'youngest_champion' => 15,
        'oldest_champion' => 15,
    ],
];
