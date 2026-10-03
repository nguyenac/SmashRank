# Thiết kế CSDL — SmashRank (MariaDB)

> Charset `utf8mb4` / collation `utf8mb4_unicode_ci` (hỗ trợ full-text tiếng Việt không dấu).
> Toàn bộ schema được thể hiện bằng Laravel Migrations trong `database/migrations/`.

## Sơ đồ tổng quan (ERD rút gọn)

```
users 1───0..1 athletes (user_id)
brands 1───* equipment_items (brand_id)
athletes 1───1 athlete_details (athlete_id PK/FK)
athletes 1───* ranking_histories
athletes 1───* athlete_year_stats
athletes *───* tournaments  (qua tournament_winners)
athletes 1───* records (nullable)
equipment_items 0..1───1 brands
users 1───* personal_access_tokens (Sanctum)
crawl_logs (độc lập — nhật ký crawl/sync)
```

## 1. `users` — Người dùng

| Cột | Kiểu | Ràng buộc / Ghi chú |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| name | VARCHAR(100) | NOT NULL |
| email | VARCHAR(150) | NOT NULL, **UNIQUE** |
| email_verified_at | TIMESTAMP | NULL |
| password | VARCHAR(255) | NOT NULL (bcrypt) |
| role | ENUM('admin','user') | DEFAULT 'user' |
| two_factor_secret | TEXT | NULL — secret TOTP |
| two_factor_enabled | BOOLEAN | DEFAULT false |
| two_factor_method | ENUM('totp','email') | DEFAULT 'totp' |
| remember_token, created_at, updated_at | | |

FK: không (bảng gốc).

## 2. `athletes` — Vận động viên

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| id | BIGINT | PK |
| user_id | BIGINT UNSIGNED | NULL, **FK → users.id** ON DELETE SET NULL |
| full_name | VARCHAR(255) | NOT NULL |
| nickname | VARCHAR(255) | NULL |
| nationality | VARCHAR(255) | NOT NULL — INDEX |
| country_code | VARCHAR(3) | NOT NULL (ISO VNM, DEN...) |
| category | ENUM('MS','WS','MD','WD','XD') | DEFAULT 'MS' |
| ranking_points | INT UNSIGNED | DEFAULT 0 |
| world_rank, career_high_rank | INT UNSIGNED | NULL |
| win_count, loss_count | INT UNSIGNED | DEFAULT 0 |
| dominant_hand | ENUM('right','left') | DEFAULT 'right' |
| skill_level | ENUM('pro','advanced','intermediate','beginner') | DEFAULT 'beginner' — INDEX |
| racket_id | BIGINT UNSIGNED | NULL, **FK → equipment_items.id** |
| shoes_id | BIGINT UNSIGNED | NULL, **FK → equipment_items.id** |
| club, association | VARCHAR | NULL |
| grassroots_rank | VARCHAR | NULL — Newbie/TBY/TB/Khá/Giỏi/Xuất Sắc |
| elo_rating | INT UNSIGNED | DEFAULT 1000 — INDEX |
| verified | BOOLEAN | DEFAULT false |
| skill_agility … skill_mentality | TINYINT UNSIGNED | 0–100 (6 chỉ số radar) |
| created_at, updated_at | | |

Chỉ mục đặc biệt: **FULLTEXT(full_name, nickname, club)** — tìm kiếm toàn văn; INDEX(source_id) — chống trùng khi crawl.

## 3. `athlete_details` — Thông tin chi tiết VĐV (1-1)

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| athlete_id | BIGINT UNSIGNED | **PK + FK → athletes.id** ON DELETE CASCADE |
| birth_date | DATE | NULL — tính tuổi, sinh nhật, kỷ lục VĐ trẻ/lớn tuổi nhất |
| height_cm, weight_kg | SMALLINT UNSIGNED | NULL |
| playing_style, coach, association, team | VARCHAR | NULL |
| titles, finals, total_matches, total_wins | INT UNSIGNED | DEFAULT 0 |
| win_streak_current | INT UNSIGNED | DEFAULT 0 |
| win_streak_career | INT UNSIGNED | DEFAULT 0 — chuỗi thắng bao gồm W.O. |
| win_streak_career_excl_wo | INT UNSIGNED | DEFAULT 0 — chuỗi thắng khi W.O. ngắt chuỗi |
| super_streak | INT UNSIGNED | DEFAULT 0 — chuỗi xuyên giải Super 1000→100 |
| not_played_matches | INT UNSIGNED | DEFAULT 0 — trận không tham gia (giải đồng đội) |
| goat_points | INT UNSIGNED | DEFAULT 0 |
| source, source_id | VARCHAR | NULL — nguồn crawl |

## 4. `brands` — Thương hiệu

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| id | BIGINT | PK |
| name | VARCHAR | NOT NULL **UNIQUE** |
| slug | VARCHAR | NOT NULL **UNIQUE** |
| country, logo_url, description | | NULL |
| source, source_id | VARCHAR | NULL (shopvnb / badmintoncn) |

## 5. `equipment_items` — Sản phẩm (Product)

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| id | BIGINT | PK |
| brand_id | BIGINT UNSIGNED | NULL, **FK → brands.id** ON DELETE SET NULL — INDEX(brand_id, type) |
| name | VARCHAR(150) | NOT NULL |
| type | ENUM('racket','shoes') | NOT NULL |
| brand | VARCHAR(80) | NOT NULL (tên hiển thị, đồng bộ với brand_id) |
| model | VARCHAR(120) | NOT NULL |
| description | TEXT | NULL |
| price | DECIMAL(12,2) | NULL — tùy chọn |
| image_url | VARCHAR | NULL — tùy chọn |
| specifications | JSON | NULL (weight, balance_point, max_tension...) |
| source, source_id | VARCHAR | NULL — nguồn crawl |

## 6. `tournaments` — Giải đấu

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| id | BIGINT | PK |
| name | VARCHAR | NOT NULL |
| level | VARCHAR | super1000/super750/super500/super300/super100/other — INDEX(level, start_date) |
| is_asian_games, is_team_event, has_live_scores | BOOLEAN | DEFAULT false |
| association | VARCHAR | NULL — INDEX (tìm theo hiệp hội) |
| host_country, start_date, end_date | | NULL |
| source, source_id | VARCHAR | NULL |

## 7. `tournament_winners` — Kết quả giải đấu

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| id | BIGINT | PK |
| tournament_id | BIGINT UNSIGNED | **FK → tournaments.id** CASCADE |
| athlete_id | BIGINT UNSIGNED | **FK → athletes.id** CASCADE |
| category | VARCHAR(2) | MS/WS/MD/WD/XD |
| placement | VARCHAR | champion/finalist/semifinal |
| achieved_at | DATE | NULL |
| **UNIQUE** | | (tournament_id, athlete_id, category, placement) |

## 8. `athlete_year_stats` — Thống kê theo năm

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| id | BIGINT | PK |
| athlete_id | BIGINT UNSIGNED | **FK → athletes.id** CASCADE |
| year | SMALLINT UNSIGNED | NOT NULL — **UNIQUE(athlete_id, year)** |
| matches, wins, losses, finals, titles | INT UNSIGNED | DEFAULT 0 |

## 9. `ranking_histories` — Lịch sử điểm (biểu đồ đường)

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| id | BIGINT | PK |
| athlete_id | BIGINT UNSIGNED | **FK → athletes.id** CASCADE |
| recorded_month | DATE | NOT NULL — **UNIQUE(athlete_id, recorded_month)** |
| points, elo_rating | INT UNSIGNED | NOT NULL |
| win_rate | DECIMAL(5,2) | DEFAULT 0 |

## 10. `records` — Bảng Kỷ lục

| Cột | Kiểu | Ghi chú |
|---|---|---|
| id | BIGINT | PK |
| type | VARCHAR — INDEX | win_rate_year, streak_career, streak_super, youngest_champion, oldest_champion, titles_year, finals_year, matches_year, wins_year |
| title | VARCHAR | Tên kỷ lục hiển thị |
| athlete_id | BIGINT UNSIGNED NULL | **FK → athletes.id** CASCADE |
| value | VARCHAR | "87.3%", "31 trận", "17 tuổi" |
| period | VARCHAR NULL | "2023", "Sự nghiệp" |

## 11. Bảng phụ trợ

| Bảng | Mục đích | Khóa chính |
|---|---|---|
| `password_reset_tokens` | Khôi phục mật khẩu | email (PK) |
| `email_otp_codes` | OTP 2FA qua email (expires_at, consumed) | id |
| `backup_settings` | Cấu hình sao lưu (frequency daily/weekly/monthly) | id |
| `backups` | Lịch sử sao lưu (drive_file_id, status) | id |
| `crawl_logs` | Nhật ký crawl/sync (target, source, items_found, items_upserted, status) | id |
| `personal_access_tokens` | Sanctum API tokens | id |

## Ràng buộc toàn vẹn tổng hợp

- Mọi khóa ngoại đều khai báo rõ ràng; `ON DELETE CASCADE` cho dữ liệu thuộc về 1 thực thể (histories, year_stats, winners), `ON DELETE SET NULL` cho tham chiếu tùy chọn (user_id, racket_id, shoes_id, brand_id).
- UNIQUE chống trùng: `(athlete_id, recorded_month)`, `(athlete_id, year)`, `(tournament_id, athlete_id, category, placement)`, `brands.name/slug`, `users.email`.
- Chống trùng hồ sơ khi crawl: kết hợp `source` + `source_id` (+ lệnh `php artisan athletes:merge keep dup` gộp thủ công).
