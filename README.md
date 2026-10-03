# SmashRank 🏸

**Trình quản lý thứ hạng vận động viên cầu lông trực tuyến** — nền tảng quản lý bảng xếp hạng BWF World Tour & Elo phong trào, trang thiết bị (vợt/giày), dashboard phân tích, sao lưu Google Drive.

> Toàn bộ nằm trong **một dự án Laravel duy nhất**: API PHP + SPA React/TypeScript cùng 1 codebase, Laravel render SPA qua Blade và phục vụ API tại `/api`.

## Cấu trúc dự án

```
smashrank/                        # Laravel 11 root (PHP 8.2) + React 18 + TypeScript
├── app/
│   ├── Http/Controllers/Api/     # Auth, Athlete, Equipment, Analytics, Backup, Profile
│   ├── Http/Middleware/          # IsAdmin, EnsureTwoFactor (2FA bắt buộc)
│   ├── Services/                 # TotpService (2FA), GoogleDriveBackupService
│   ├── Console/Commands/         # backup:run (lịch daily/weekly/monthly)
│   └── Models/                   # User, Athlete, RankingHistory, EquipmentItem, Backup...
├── config/                       # cors.php, services.php (Google Drive credentials)
├── database/
│   ├── migrations/               # users, athletes, ranking_histories, equipment_items, backups...
│   └── seeders/                  # Dữ liệu mẫu: 12 VĐV, 12 tháng lịch sử, vợt/giày
├── resources/
│   ├── js/                       # SPA React + TypeScript (dark mode Tailwind + Recharts)
│   │   ├── components/           # RankTrendChart (Line), RadarSkillChart, CountryPieChart
│   │   └── pages/                # Login (2FA), Rankings (Zen Mode), Admin*, Profile...
│   └── views/app.blade.php       # Khung HTML render SPA (@vite)
├── routes/
│   ├── api.php                   # REST API
│   ├── web.php                   # Fallback trả về SPA (React Router xử lý)
│   └── console.php               # Schedule backup:run 02:00 hằng ngày
├── public/                       # entry Laravel + thư mục build Vite (public/build)
├── package.json                  # Frontend deps (React, Recharts, Tailwind, Vite)
├── vite.config.ts                # laravel-vite-plugin + proxy dev
├── composer.json                 # Backend deps (Laravel, Sanctum)
└── docs/
    ├── SETUP_VPS.md              # Hướng dẫn cài đặt & triển khai VPS
    ├── API_ATHLETE_SEARCH.md     # Tài liệu API tìm kiếm vận động viên
    ├── UI_UX_DESIGN.md           # Bản thiết kế UI/UX
    └── BACKUP_GOOGLE_DRIVE.md    # Hướng dẫn sao lưu Google Drive
```

## Tính năng chính

| Nhóm | Chi tiết |
|---|---|
| 🔐 Xác thực | Đăng ký/đăng nhập (Sanctum token), khôi phục mật khẩu qua email, **2FA bắt buộc** (Google Authenticator TOTP hoặc OTP email) |
| 🏆 Bảng xếp hạng | Danh sách VĐV theo Elo/BWF, lọc nội dung MS/WS/MD/WD/XD, Zen Mode cho mobile (lưu localStorage) |
| 🔍 Tìm kiếm VĐV | Full-text (có dấu/không dấu) + lọc theo họ tên, quốc tịch, trình độ — xem [API doc](docs/API_ATHLETE_SEARCH.md) |
| 📈 Biểu đồ | Line Chart xu hướng điểm 12 tháng, Radar Chart kỹ năng (nhanh nhẹn/sức mạnh/sức bền/kỹ thuật/phòng thủ/tâm lý), Pie Chart phân bổ quốc gia |
| 🛍️ Sản phẩm | CRUD vợt/giày cho Admin: tên, thương hiệu, loại, mô tả, giá (tùy chọn), ảnh (tùy chọn) + liên kết 2 chiều VĐV↔trang bị |
| 📊 Analytics | VĐV mới trong tuần/tháng, phân bổ quốc gia (Pie), top Elo tăng trưởng |
| 💾 Sao lưu | Dump MariaDB + upload Google Drive, lịch daily/weekly/monthly tự động, phục hồi 1-click từ giao diện Admin |
| 🏷️ Thương hiệu | CRUD thương hiệu (Admin); sản phẩm vợt/giày thuộc về một thương hiệu; biểu đồ cột thiết bị theo thương hiệu |
| 📅 Sinh nhật | Widget "Sinh nhật hôm nay" trên Trang chủ + tra cứu sinh nhật tay vợt theo ngày bất kỳ |
| 🏅 Kỷ lục | Bảng Kỷ lục: tỷ lệ thắng năm (≥50 trận), chuỗi thắng sự nghiệp (tùy chọn W.O.), chuỗi Super 1000→100, VĐ trẻ nhất/lớn tuổi nhất, nhiều danh hiệu/chung kết/trận/thắng trong năm |
| 🎾 Giải đấu | Danh sách giải, chi tiết + tìm theo hiệp hội; nút Live Score chỉ hiện khi có real-time, trang live tự redirect về chi tiết |
| 🧮 Điểm số | ELO điều chỉnh Covid (giảm chậm khi vắng mặt 2020–2021, tăng nhẹ sau 2021) — `php artisan elo:recalc`; GOAT points bỏ H2H/Win%, Asian Games = 40 — `php artisan records:rebuild` |
| 📊 Phân hạng | Việt Vũ 8 tiêu chí → 6 bậc: Newbie, TBY, TB, Khá, Giỏi, Xuất Sắc |
| 🕷️ Crawl | Admin crawl VĐV (badmintonranks.com + sync BWF rankings) và thương hiệu/sản phẩm (shopvnb.com + sync badmintoncn.com, dịch EN→ZH), nhật ký đầy đủ; gộp hồ sơ trùng `php artisan athletes:merge` |

## Tài khoản demo (sau khi seed)

- **Admin**: `admin@smashrank.local` / `Admin@123456`
- **Người chơi**: `player@smashrank.local` / `Player@123456`

> Sau lần đăng nhập đầu, hệ thống bắt buộc cấu hình 2FA (xem `docs/SETUP_VPS.md`).

## Chạy nhanh (máy phát triển)

```bash
# 1. Backend + CSDL
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed

# 2. Frontend + SPA (terminal khác, cùng dự án)
npm install
npm run dev          # Vite :5173 (proxy /api → Laravel :8000)
php artisan serve    # Laravel :8000
# → Mở http://localhost:5173

# Hoặc bản production: npm run build rồi chỉ cần php artisan serve / Nginx
```

## Tài liệu

- [Cài đặt & triển khai VPS](docs/SETUP_VPS.md)
- [API tìm kiếm vận động viên](docs/API_ATHLETE_SEARCH.md)
- [Bản thiết kế UI/UX](docs/UI_UX_DESIGN.md)
- [Thiết kế CSDL đầy đủ](docs/DATABASE_DESIGN.md)
- [Sao lưu Google Drive](docs/BACKUP_GOOGLE_DRIVE.md)
- [Crawler & đồng bộ dữ liệu](docs/CRAWLER.md)
