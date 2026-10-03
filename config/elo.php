<?php

/*
|--------------------------------------------------------------------------
| Tham số tính điểm ELO — SmashRank
|--------------------------------------------------------------------------
| Bao gồm điều chỉnh giai đoạn Covid: VĐV vắng mặt tại các giải đấu trong
| 2020-03 → 2021-12 bị giảm điểm chậm hơn (decay), VĐV thi đấu trở lại
| sau năm 2021 được tăng nhẹ điểm.
*/

return [
    'k_factor' => 32,
    'start_rating' => 1000,

    // Giai đoạn Covid: tính vắng mặt
    'covid' => [
        'start' => '2020-03',
        'end' => '2021-12',
        // Số tháng nghỉ không bị tính là vắng mặt
        'grace_months' => 3,
        // % điểm giảm tối đa mỗi tháng vắng mặt (0.5%/tháng)
        'decay_per_month' => 0.005,
        // Trần giảm tối đa tuyệt đối trong suốt giai đoạn (% của điểm hiện tại)
        'max_total_decay' => 0.10,
    ],

    // VĐV thi đấu trở lại sau năm 2021: tăng nhẹ điểm mỗi tháng thi đấu
    'post_covid' => [
        'from' => '2022-01',
        // % tăng mỗi tháng có thi đấu
        'boost_per_month' => 0.003,
        // Trần tăng tối đa tuyệt đối
        'max_total_boost' => 0.05,
    ],
];
