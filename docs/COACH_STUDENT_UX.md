# Thiết kế UI/UX — Ứng dụng quản lý học viên & huấn luyện viên thể thao

> Mở rộng SmashRank thành hệ thống quản lý học viên cho HLV — nhất quán trên **Web, iOS, Android** (thiết kế responsive + PWA đã tích hợp sẵn, có thể đóng gói Capacitor/React Native dùng chung API).

## Design System dùng chung

- Dark Mode `#0a0a0b` + emerald/amber/rose; Light mode tùy chọn.
- Card bo góc 12px, viền neutral-800; font số liệu mono; mobile-first, cử chỉ vuốt cho danh sách.
- Component chung: `ToastHost`, `GlobalSearch`, biểu đồ Recharts/D3, avatar tròn + huy hiệu.
- Web = React SPA đã có; iOS/Android = PWA installable (manifest + service worker) hoặc wrapper Capacitor dùng cùng codebase.

## 1. Màn hình HỌC VIÊN

| Màn hình | Thiết kế chính |
|---|---|
| **Đăng nhập** | Form email + mật khẩu, nút 2FA (TOTP/OTP email), liên kết quên mật khẩu; đăng nhập một chạm trên mobile bằng sinh trắc học (WebAuthn — roadmap) |
| **Lịch tập** | Lịch tuần 7 cột (đã có: kéo-thả bài tập); ô ngày hiển thị badge bài tập + trạng thái hoàn thành; đồng bộ Google Calendar |
| **Điểm danh** | Nút "Check-in" lớn trên dashboard; mã QR điểm danh do HLV phát (tái dùng QR component); lịch sử check-in dạng timeline |
| **Theo dõi tiến độ** | User Dashboard: Pie thắng/thua, biểu đồ cân nặng + nhịp tim, radar kỹ năng trước/sau; Skill Timeline D3 click mốc |
| **Thanh toán** | Tab hóa đơn: gói tập, trạng thái (đã trả/còn nợ), lịch sử; xuất PDF; (tích hợp cổng thanh toán VNPay/Momo — roadmap) |
| **Chat** | Chat 1-1 với HLV: danh sách hội thoại, bubble tin nhắn, đính kèm ảnh clip tập luyện; nền tảng WebSocket (Laravel Reverb) — thiết kế UI sẵn, backend roadmap |

## 2. Màn hình HUẤN LUYỆN VIÊN

| Màn hình | Thiết kế chính |
|---|---|
| **Đăng nhập** | Chung hệ thống tài khoản với role `admin`/coach |
| **Quản lý học viên** | Lưới thẻ học viên: avatar + Verified badge + Elo + tiến độ tuần; tìm kiếm toàn cục; gộp hồ sơ trùng |
| **Lên lịch tập** | Kéo-thả bài tập từ thư viện vào lịch tuần từng học viên (`training_schedules`); bộ lọc độ khó/thời lượng/kỹ thuật |
| **Báo cáo** | Analytics Hub + Reports (bán hàng, đăng ký, sử dụng sân) + xuất PDF/CSV/JSON |
| **Chat** | Giống học viên; thêm thông báo đẩy khi học viên check-in/hoàn thành bài tập |

## 3. Nhất quán đa nền tảng

- **Breakpoints**: mobile (<768px menu cuộn ngang, thẻ 1 cột), tablet (2 cột), desktop (sidebar + 3 cột).
- **Touch-first**: nút ≥44px, kéo-thả có fallback nút "+" trên mobile.
- **Offline**: service worker cache danh sách VĐV/sản phẩm — học viên xem lịch & tiến độ kể cả mất mạng.
- **Push**: nhắc lịch tập 24h/1h trước (đã có pipeline VAPID).

## 4. Trạng thái & thông báo

- Dùng Notification Manager (success/warning/error/info) — đã có.
- Loading skeleton cho danh sách; empty state hướng dẫn hành động đầu tiên.
