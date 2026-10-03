# Thiết kế Crawler & Đồng bộ dữ liệu — SmashRank

## 1. Nguyên tắc & tuân thủ

- Crawler chỉ chạy **thủ công từ Admin** hoặc theo lịch do Admin tự bật; mặc định tắt.
- Trước khi bật lịch, cần kiểm tra `robots.txt` và điều khoản sử dụng của từng nguồn; hệ thống đặt User-Agent công khai `SmashRankBot/1.0 (+https://your-domain.com; admin contact)` và giới hạn tần suất.
- Cấu trúc HTML của nguồn có thể thay đổi bất cứ lúc nào — các regex parser trong `CrawlerService` cần được rà soát định kỳ; mọi lần chạy đều ghi `crawl_logs` (found/upserted/status/message) để giám sát.
- Nguồn dữ liệu chính thức về thứ hạng là BWF; crawl chỉ mang tính tham khảo/bổ sung.

## 2. Kiến trúc

```
Admin UI (/admin/crawl)
   │  POST /api/admin/crawl/athletes
   │  POST /api/admin/crawl/products
   ▼
CrawlerService
   ├── crawlAthletesFromBadmintonRanks()  → https://badmintonranks.com
   ├── syncWithBwfRankings(category)      → https://bwfbadminton.com/rankings
   ├── crawlProductsFromShopVnb()         → https://shopvnb.com
   └── syncWithBadmintonCn()              → https://www.badmintoncn.com/cbo_eq/
                                             └── TranslationService (EN → ZH)
   ▼
Upsert theo (source, source_id)  →  athletes / brands / equipment_items
Ghi nhật ký                      →  crawl_logs
```

## 3. Chi tiết từng nguồn

### 3.1. VĐV — badmintonranks.com
- Parse trang xếp hạng ELO (đơn nam): link profile (`/player/{slug}`), tên, quốc kỳ, điểm.
- Upsert vào `athletes` theo `source_id = slug` → **không tạo hồ sơ trùng**; `athlete_details` được khởi tạo rỗng.
- Có thể mở rộng sang WS/MD/WD/XD bằng cách gọi thêm các URL nội dung tương ứng.

### 3.2. Đồng bộ bwfbadminton.com/rankings
- Parse bảng BWF World Rankings: hạng (`rank`), tên, điểm.
- Ghép với VĐV nội bộ theo tên (so sánh có/không khoảng trắng) → cập nhật `world_rank`, `ranking_points`.
- Chạy theo từng nội dung: `MS`, `WS`, `MD`, `WD`, `XD`.

### 3.3. Sản phẩm — shopvnb.com
- Parse danh mục vợt/giày: URL sản phẩm, tên, giá (₫).
- Suy luận thương hiệu từ tên (Yonex/Victor/Li-Ning/Mizuno/Kawasaki) → tự tạo `brands` nếu chưa có, gán `brand_id`.
- Upsert `equipment_items` theo `md5(url)`.

### 3.4. Đồng bộ badmintoncn.com/cbo_eq/ + dịch EN→ZH
- Parse danh sách thiết bị, lọc link điều hướng.
- **Tên tiếng Anh được dịch sang tiếng Trung** trước khi lưu qua `TranslationService`:
  - Ưu tiên Google Cloud Translation API nếu có `GOOGLE_TRANSLATE_API_KEY` trong `.env`.
  - Không có key → dùng bảng thuật ngữ cầu lông tích hợp sẵn (Yonex→尤尼克斯, Victor→胜利, racket→球拍, head heavy→进攻型...); tên model (AX100ZZ...) giữ nguyên.

## 4. Gộp hồ sơ trùng lặp

- Tự động: upsert theo `source_id` khi crawl.
- Thủ công: `php artisan athletes:merge {keep} {duplicate}` — chuyển `ranking_histories`, `athlete_year_stats`, `tournament_winners`, gộp thống kê/chi tiết rồi xóa hồ sơ trùng (transaction an toàn).
- Khuyến nghị chạy `php artisan records:rebuild` sau mỗi đợt gộp để cập nhật kỷ lục & GOAT.

## 5. Mở rộng

- Muốn crawl định kỳ: thêm `Schedule::command('crawl:athletes')->weekly()` trong `routes/console.php` (sau khi rà soát parser + robots.txt).
- Thêm nguồn mới: triển khai thêm method parse trong `CrawlerService` + nguồn mới trong `crawl_logs.source`.
