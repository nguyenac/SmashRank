# API Tìm Kiếm Vận Động Viên — SmashRank

Base URL: `https://your-domain.com/api`

## 1. `GET /athletes` — Tìm kiếm & lọc danh sách VĐV

Công khai, không cần xác thực. Phân trang, sắp xếp.

### Tham số truy vấn (query string)

| Tham số | Kiểu | Mô tả |
|---|---|---|
| `q` | string | **Tìm kiếm toàn văn** trên họ tên, nickname, câu lạc bộ. Có dấu hoặc không dấu đều khớp (MariaDB FULLTEXT, utf8mb4_unicode_ci). 1 từ → khớp một phần (prefix); nhiều từ → full-text natural language |
| `name` | string | Lọc chính xác *một phần* theo **Họ Tên** (`LIKE %name%`) |
| `nationality` | string | Lọc theo **Quốc Tịch** (VD: `Việt Nam`, `Denmark`) |
| `skill_level` | enum | `pro` \| `advanced` \| `intermediate` \| `beginner` — **trình độ** |
| `category` | enum | `MS` \| `WS` \| `MD` \| `WD` \| `XD` (nội dung đơn/đôi) |
| `dominant_hand` | enum | `right` \| `left` |
| `racket` | int | ID sản phẩm vợt đang sử dụng |
| `shoes` | int | ID sản phẩm giày đang sử dụng |
| `sort` | enum | `elo` (mặc định) \| `points` \| `world_rank` \| `name` |
| `per_page` | int | 1–100, mặc định 20 |
| `page` | int | Trang hiện tại |

### Ví dụ gọi API

```bash
# Tìm kiếm toàn văn "tien minh" (không dấu vẫn khớp "Tiến Minh")
curl "https://your-domain.com/api/athletes?q=tien%20minh"

# Lọc VĐV Việt Nam, trình độ advanced, nội dung đơn nam
curl "https://your-domain.com/api/athletes?nationality=Vi%E1%BB%87t%20Nam&skill_level=advanced&category=MS"

# Tất cả tay vợt trái tay, sắp theo thứ hạng thế giới
curl "https://your-domain.com/api/athletes?dominant_hand=left&sort=world_rank"
```

### Định dạng trả về (200 OK)

```json
{
  "data": [
    {
      "id": 10,
      "full_name": "Nguyễn Tiến Minh",
      "nickname": "NTM",
      "nationality": "Việt Nam",
      "country_code": "VNM",
      "category": "MS",
      "world_rank": 55,
      "ranking_points": 42300,
      "elo_rating": 1105,
      "skill_level": "advanced",
      "win_rate": 53.8,
      "dominant_hand": "right",
      "avatar_url": null,
      "racket": "DriveX 9900",
      "shoes": "Power Cushion 65 Z3"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 2 }
}
```

## 2. `GET /athletes/{id}` — Chi tiết VĐV

Trả về đầy đủ: chỉ số kỹ năng (cho Radar Chart), lịch sử xếp hạng 12 tháng (cho Line Chart), trang bị:

```json
{
  "data": {
    "id": 10,
    "full_name": "Nguyễn Tiến Minh",
    "win_rate": 53.8,
    "skills": {
      "agility": 84, "power": 82, "stamina": 88,
      "technique": 90, "defense": 86, "mentality": 93
    },
    "racket": { "id": 5, "name": "DriveX 9900", "brand": "Victor", "model": "DX-9900" },
    "shoes": { "id": 6, "name": "Power Cushion 65 Z3", "brand": "Yonex", "model": "SHB65Z3" },
    "ranking_history": [
      { "month": "2025-10", "points": 25300, "elo_rating": 1020, "win_rate": 48.0 },
      { "month": "2025-11", "points": 26100, "elo_rating": 1031, "win_rate": 55.0 }
    ]
  }
}
```

## Ghi chú triển khai

- Chỉ mục FULLTEXT được tạo sẵn trong migration: `ALTER TABLE athletes ADD FULLTEXT(full_name, nickname, club)` (utf8mb4, hoạt động trên MariaDB 10.0.5+).
- Laravel helper `whereFullText()` tự sinh `MATCH ... AGAINST (... IN NATURAL LANGUAGE MODE)` trên MariaDB/MySQL.
- Tìm kiếm "không dấu" hoạt động nhờ collation `utf8mb4_unicode_ci` (không phân biệt dấu tiếng Việt).
