# Hướng dẫn cài đặt & triển khai trên VPS

Yêu cầu: **Ubuntu 22.04+**, PHP **8.2+**, MariaDB **10.6+**, Nginx, Node.js **20+**, Composer 2.

> Dự án gộp backend + frontend trong **một codebase Laravel duy nhất** — chỉ cần deploy 1 thư mục, 1 domain.

## 1. Cài đặt phần nền

```bash
sudo apt update && sudo apt install -y php8.2-fpm php8.2-cli php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-mysql php8.2-zip php8.2-gd mariadb-server \
  nginx unzip nodejs npm composer
```

## 2. Tạo CSDL MariaDB

```sql
CREATE DATABASE smashrank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smashrank_user'@'localhost' IDENTIFIED BY 'MAT_KHAU_MANH';
GRANT ALL PRIVILEGES ON smashrank.* TO 'smashrank_user'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Cài đặt dự án (backend + frontend cùng lúc)

```bash
cd /var/www/smashrank

# --- Backend (Laravel) ---
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Sửa .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL, FRONTEND_URL, MAIL_*
php artisan migrate --seed
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache

# --- Frontend (SPA React build ra public/build) ---
npm install
npm run build
```

Sau khi `npm run build`, Laravel tự phục vụ SPA qua Blade (`resources/views/app.blade.php` + `@vite`) — không cần cấu hình tách rời frontend.

## 4. Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/smashrank/public;      # entry Laravel
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Assets đã build (Vite)
    location /build {
        try_files $uri =404;
        expires 30d;
    }

    location /storage {
        try_files $uri =404;
    }
}
```

Không cần proxy `/api` riêng — API và SPA cùng chạy trong Laravel.

## 5. Chạy scheduler sao lưu (cron)

```bash
crontab -e
# thêm dòng:
* * * * * cd /var/www/smashrank && php artisan schedule:run >> /dev/null 2>&1
```

Lệnh `backup:run` chạy 02:00 hằng ngày và tự kiểm tra tần suất (daily/weekly/monthly)
đã cấu hình trong bảng `backup_settings` — chỉ dump + upload khi đúng chu kỳ.

## 6. Kiểm tra

1. Mở `https://your-domain.com` → Bảng xếp hạng với dữ liệu mẫu (12 VĐV, 12 tháng lịch sử).
2. Đăng nhập admin `admin@smashrank.local / Admin@123456` → hệ thống buộc cấu hình 2FA.
3. **2FA bằng Google Authenticator**: copy secret hiển thị → thêm vào app → nhập mã 6 số → Bật 2FA.
4. **2FA bằng Email**: chọn phương thức Email. Cần cấu hình SMTP trong `.env` (mặc định `MAIL_MAILER=log` ghi mã vào `storage/logs/laravel.log` để test).
5. Vào `/admin/products` thử CRUD; `/admin/analytics` xem dashboard; `/admin/backups` cấu hình lịch + sao lưu ngay.

## 7. Bảo mật VPS

- Cấp HTTPS bằng `certbot --nginx`.
- Đặt `APP_ENV=production`, `APP_DEBUG=false`.
- Firewall: chỉ mở 80/443/SSH; MariaDB lắng nghe localhost.
- Sao lưu định kỳ: đã có Google Drive; có thể thêm cron `php artisan backup:run --force` tuần.
- Sau mỗi lần deploy frontend mới: `npm run build && php artisan config:cache && php artisan route:cache`.
