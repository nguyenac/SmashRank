<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Schedule
|--------------------------------------------------------------------------
| Chạy cron trên VPS:
| * * * * * cd /var/www/smashrank/backend && php artisan schedule:run >> /dev/null 2>&1
|
| Lệnh backup:run tự đọc cấu hình tần suất (daily/weekly/monthly) trong
| bảng backup_settings, chỉ thực thi sao lưu khi đúng chu kỳ đã cấu hình.
*/

Schedule::command('backup:run')->dailyAt('02:00');

// Kết quả trận mới (15 phút/lần) + nhắc giải đấu trước 24h (hằng giờ)
Schedule::command('notify:match-results')->everyFifteenMinutes();
Schedule::command('notify:match-results --tournaments')->hourly();

// Giữ chân người chơi: nhắc người ngừng hoạt động ≥ 3 ngày — 09:00 hằng ngày
Schedule::command('notify:inactive --days=3')->dailyAt('09:00');
