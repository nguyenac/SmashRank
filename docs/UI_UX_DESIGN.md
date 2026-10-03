# Bản Thiết Kế UI/UX — SmashRank

> Trình quản lý Thứ hạng Vận động viên Cầu lông Trực tuyến

## 1. Nguyên tắc thiết kế

- **Dark Mode cao cấp**: nền `#0a0a0b` (neutral-950), điểm nhấn **xanh ngọc** `#34d399` (emerald-400), **vàng kim** `#fbbf24` (amber-400), **đỏ hồng** `#fb7185` (rose-500). Chống mỏi mắt khi dùng ở sân thi đấu ban đêm.
- **Mobile-first**: mọi màn hình hoạt động trơn tru trên điện thoại; Zen Mode cho bảng xếp hạng tại sân.
- **Micro-interactions**: nút có trạng thái loading/disabled; thông báo toast khi lưu, chia sẻ, sao lưu; tooltip trên mọi biểu đồ.
- **Phân cấp thông tin**: số liệu quan trọng (Elo, thứ hạng) dùng font mono + màu nhấn; meta info dùng màu neutral-400.

## 2. Design System

| Thành phần | Quy ước |
|---|---|
| Container | max-w-6xl, padding 16px |
| Card | bo góc `rounded-xl`, viền `neutral-800`, nền `#131315` |
| Nút chính | nền emerald-500, chữ đen, hover emerald-400 |
| Nút phụ | viền neutral-700, hover viền emerald |
| Input | nền `#1d1d20`, focus viền emerald-500 |
| Bảng | header uppercase 12px neutral-400, dòng hover nền surface-800 |
| Huy chương | 🥇🥈🥉 cho top 3, còn lại `#n` |

## 3. Sơ đồ luồng người dùng (User Flows)

### 3.1. Đăng ký
```
Trang chủ ──► [Đăng ký] ──► Form (Họ tên, Email, Mật khẩu, Xác nhận)
                              │ lỗi → hiện thông báo đỏ dưới field
                              ▼ thành công
                            TRANG CẤU HÌNH 2FA (bắt buộc)
                              ├── Chọn Google Authenticator → nhập secret → nhập mã 6 số → Bật
                              └── Chọn Email → nhập mã OTP nhận được → Bật
                              ▼
                            Trang chủ (đã đăng nhập)
```

### 3.2. Đăng nhập (có 2FA)
```
[Đăng nhập] ──► Form Email + Mật khẩu
                   │ Sai → 401 "Email hoặc mật khẩu không chính xác"
                   ▼ Đúng + 2FA bật
                 Màn hình nhập mã 6 số (TOTP hoặc OTP email)
                   ├── Nút "Gửi lại mã OTP" (phương thức email)
                   ▼ Mã đúng
                 Token cấp → chuyển trang chủ
```
> Tài khoản chưa bật 2FA: đăng nhập xong bị điều hướng thẳng tới trang cấu hình 2FA (middleware `TWO_FACTOR_REQUIRED` chặn mọi API được bảo vệ).

### 3.3. Khôi phục mật khẩu
```
[Quên mật khẩu?] ──► Nhập email ──► Email chứa liên kết (hết hạn 60 phút)
                    ──► Trang Reset: mật khẩu mới + xác nhận ──► Đăng nhập lại
```
Luôn hiển thị thông báo trung lập (không tiết lộ email có tồn tại hay không).

### 3.4. Cập nhật hồ sơ vận động viên
```
[Hồ sơ của tôi] ──► Tab thông tin: Họ tên, Quốc tịch, CLB, Năm sinh, Tay thuận, Trình độ
                    Tab trang bị: chọn Vợt / Giày từ dropdown (liên kết kho thiết bị)
                    Tab tự đánh giá Việt Vũ: 5 slider 0–100 (Phát cầu, Đập cầu,
                        Phòng thủ, Bộ chân, Tâm lý) → tự phân hạng
                          Newbie / TB / Khá / Giỏi / Xuất sắc
                    ▼ [💾 Lưu hồ sơ] → toast "Đã lưu hồ sơ thành công!"
                    Dưới cùng: biểu đồ đường xu hướng Elo cá nhân
```

### 3.5. Tìm kiếm vận động viên
```
[Bảng xếp hạng] ──► Thanh tìm kiếm "🔍 Tìm VĐV, CLB…"
                    Bộ lọc: Nội dung (MS–XD) | Trình độ | Quốc tịch
                    ▼ Kết quả dạng bảng (desktop) hoặc thẻ gọn (Zen Mode)
                    Click VĐV ──► Trang chi tiết (3.6)
```

### 3.6. Xem thông tin vận động viên (Athlete Detail)
```
Header: Avatar tròn + Tên ✔ verified + quốc tịch/nội dung/trình độ + [🔗 Chia sẻ]
  (Web Share API trên mobile; fallback copy clipboard)
4 thẻ số liệu: Thứ hạng TG | Điểm BWF | Điểm Elo | Tỷ lệ thắng
2 biểu đồ cạnh nhau:
  ├─ Radar Chart 6 trục: Nhanh nhẹn, Sức mạnh, Sức bền, Kỹ thuật, Phòng thủ, Tâm lý
  └─ Line Chart 12 tháng: Elo / BWF / Tỷ lệ thắng (chọn qua dropdown)
Card trang bị: Vợt, Giày (click → trang chi tiết sản phẩm — liên kết 2 chiều)
```

### 3.7. Xem thông tin sản phẩm (vợt, giày)
```
[Vợt & Giày] ──► Lọc: 🔍 tìm + select Vợt/Giày
                 Grid 3 cột: ảnh, badge loại (🏸/👟), tên, thương hiệu·model,
                 mô tả, giá (nếu có), chips thông số kỹ thuật
                 ──► Chi tiết sản phẩm: full specifications + "Các VĐV đang sử dụng"
```

### 3.8. Khu vực quản lý sản phẩm (Admin)
```
Nav "Quản lý sản phẩm" (chỉ hiện với admin) ──►
  Form trên cùng: Tên*, Loại* (Vợt/Giày), Thương hiệu*, Model*,
                  Mô tả, Giá (tùy chọn), Thông số JSON (tùy chọn)
  ▼ [Thêm mới] / [Cập nhật] / [Hủy]
  Bảng dưới: danh sách + nút Sửa (đổ dữ liệu lên form, scroll top) / Xóa (confirm)
```
Route được bảo vệ 3 lớp: `auth:sanctum` → `admin` → `twofactor`.

### 3.9. Analytics Dashboard (Admin)
```
4 thẻ KPI: VĐV mới tuần | VĐV mới tháng | Tổng VĐV | Tổng users
2 khối cạnh nhau:
  ├─ Pie Chart phân bổ VĐV theo quốc gia (donut, 12 màu phân biệt)
  └─ Top 10 VĐV tăng trưởng Elo (xếp hạng + elo_prev → elo_now + delta xanh)
Dải phân bố trình độ: pro/advanced/intermediate/beginner
```

### 3.10. Sao lưu Google Drive (Admin)
```
Card cấu hình: Tần suất (ngày/tuần/tháng) | Bật/Tắt | Drive Folder ID
  Badge trạng thái kết nối (xanh = đã cấu hình .env, vàng = chưa)
  [💾 Lưu cấu hình] [▶️ Sao lưu ngay]
Bảng lịch sử: file, kích thước, badge trạng thái (uploaded xanh /
  local_only vàng / failed đỏ), loại (Thủ công/Tự động), nút [Phục hồi]
  └─ Confirm modal cảnh báo ghi đè CSDL → gọi API với confirmation=yes
```

## 4. Zen Mode (màn hình nhỏ)

- Toggle ở góc phải bảng xếp hạng; trạng thái lưu `localStorage` (`zen_mode`).
- Ẩn bảng chi tiết → chuyển thành **thẻ siêu gọn 2 cột**: `#hạng · quốc kỳ · tên · vợt · Elo`.
- Giữ nguyên thanh tìm kiếm + bộ lọc nội dung/trình độ/quốc tịch.
- Menu điều hướng mobile: hàng cuộn ngang dưới header.

## 5. Trạng thái hệ thống

| Trạng thái | Hiển thị |
|---|---|
| Loading | "Đang tải…" trung tâm, nút disabled + nhãn "Đang xử lý…" |
| Lỗi form | chữ đỏ rose-400 ngay trên nút submit |
| Thành công | chữ xanh emerald-400 / alert cho hành động nhạy cảm |
| Trống | "Chưa có dữ liệu." / "Không có sản phẩm nào." |
| Bị chặn 2FA | tự động redirect `/2fa/setup` |

---

# Phần bổ sung: Trang mới (bản mở rộng)

## 6. Trang chủ (Homepage)

```
Hero: logo SmashRank + slogan + 4 nút nhanh (Bảng xếp hạng / Kỷ lục / Giải đấu / Vợt & Giày)
Top 3 Elo: 3 thẻ podium 🥇🥈🥉 với quốc kỳ, tên, nội dung, điểm Elo
Card "🎂 Sinh nhật hôm nay":
  - Danh sách VĐV sinh nhật đúng hôm nay (badge emerald, tuổi năm nay)
  - Date-picker cho phép tra sinh nhật tay vợt vào BẤT KỲ ngày nào
```

## 7. Trang Danh sách Kỷ lục (/records)

```
Mô tả các nhóm kỷ lục ngay dưới tiêu đề
Mỗi kỷ lục 1 dòng: Tên kỷ lục + kỳ (năm/sự nghiệp) ── giá trị (vàng kim, font mono) ── VĐV giữ kỷ lục (link)
Các nhóm kỷ lục:
  - Tỷ lệ thắng trong năm dương lịch (tối thiểu 50 trận)
  - Chuỗi trận thắng sự nghiệp (có tùy chọn W.O.)
  - Chuỗi thắng xuyên giải Super 1000 → Super 100
  - VĐ vô địch trẻ nhất / lớn tuổi nhất (Super 1000 → Super 300)
  - Danh hiệu / chung kết / trận đấu / trận thắng nhiều nhất trong 1 năm
```

## 8. Trang Giải đấu (/tournaments)

```
Bộ lọc: 🔍 tên giải/hiệp hội | select cấp độ (Super 1000→100) | input hiệp hội
Thẻ giải: tên + cấp độ + quốc gia + badges (Asian Games / Đồng đội)
  Nút "🔴 Live Score" màu đỏ CHỈ hiện khi giải có cập nhật tỷ số real-time
Trang chi tiết: bảng kết quả (VĐV, nội dung, 🏆 VĐ / 🥈 á quân, ngày)
  + ô "Tìm theo hiệp hội tay vợt" lọc trực tiếp danh sách trận/kết quả
/tournaments/:id/live: nếu giải KHÔNG có real-time → TỰ ĐỘNG CHUYỂN HƯỚNG về trang chi tiết
```

## 9. Thẻ [Chuỗi trận thắng] trên hồ sơ VĐV — tùy chọn W.O.

```
Checkbox: "Thắng W.O. làm ngắt quãng chuỗi"
  ├─ Bật  → hiển thị win_streak_career_excl_wo (W.O. ngắt chuỗi)
  └─ Tắt  → hiển thị win_streak_career (W.O. vẫn tính là thắng)
4 ô số liệu: Chuỗi hiện tại | Kỷ lục sự nghiệp | Chuỗi Super 1000→100 | Điểm GOAT
Dòng phụ: danh hiệu · chung kết · tổng trận · tổng thắng · trận không tham gia (đồng đội)
```

## 10. Quản lý Thương hiệu (/admin/brands)

```
Form trên: Tên* | Quốc gia | Mô tả → [Thêm mới]/[Cập nhật]/[Hủy]
Bảng dưới: Thương hiệu | Quốc gia | số Vợt | số Giày | Sửa/Xóa
Xóa bị chặn (422) khi thương hiệu còn sản phẩm — thông báo hướng dẫn chuyển sản phẩm
```

## 11. Trang Crawl & Sync (/admin/crawl)

```
2 thẻ hành động:
  - VĐV: [⬇️ Crawl VĐV + Sync BWF] (badmintonranks.com + bwfbadminton.com/rankings)
  - Sản phẩm: [⬇️ Crawl Sản phẩm + Sync CN] (shopvnb.com + badmintoncn.com, dịch EN→ZH)
Bảng nhật ký: Loại | Nguồn | Tìm thấy | Cập nhật | badge Trạng thái | Thời điểm
Cảnh báo robots.txt/ToS hiển thị ngay trên bảng
```

## 12. Dashboard nhanh Admin (nằm trong /admin/analytics)

```
Hàng KPI 4 thẻ → 2 biểu đồ cạnh nhau:
  ├─ Pie: phân bổ cấp độ kỹ năng (Mới bắt đầu / Trung bình khá / Nâng cao / Chuyên nghiệp)
  └─ Bar (cột chồng): số Vợt + Giày theo từng thương hiệu (xanh = vợt, vàng = giày)
Tiếp theo: Pie quốc gia | Top Elo tăng trưởng | Phân bố trình độ (bản tổng hợp)
```

## 13. Phân hạng trình độ phong trào (Việt Vũ, 8 tiêu chí)

```
Profile → 8 slider 0-100: Phát cầu, Đập cầu, Cầu gần lưới, Trái tay,
          Phòng thủ, Bước di chuyển, Sức bền, Tâm lý thi đấu
Điểm TB → phân hạng tự động:
  <40 Newbie (người mới) | <50 TBY (Trung bình yếu) | <60 TB (Trung bình)
  <75 Khá | <85 Giỏi | ≥85 Xuất Sắc
Hạng hiển thị ở hồ sơ VĐV (grassroots_rank)
```
