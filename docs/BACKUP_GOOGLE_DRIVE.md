# Sao lưu dữ liệu lên Google Drive — SmashRank

## 1. Cơ chế hoạt động

```
Cron (mỗi phút) ──► php artisan schedule:run
                        └─ 02:00 hằng ngày: php artisan backup:run
                              └─ Đọc backup_settings:
                                   ├─ enabled = false → bỏ qua
                                   ├─ daily:  chạy nếu lần chạy trước cách ≥ 1 ngày
                                   ├─ weekly: ≥ 7 ngày | monthly: ≥ 28 ngày
                              └─ mysqldump | gzip → storage/app/backups/*.sql.gz
                              └─ Google Drive API v3 (multipart upload)
                              └─ Ghi bản ghi vào bảng backups (file id + trạng thái)
```

## 2. Cấu hình Google Drive (OAuth2, một lần)

1. Truy cập [Google Cloud Console](https://console.cloud.google.com) → tạo Project.
2. **APIs & Services → Library** → bật **Google Drive API**.
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID**:
   - Application type: *Web application*
   - Authorized redirect URI: `https://developers.google.com/oauthplayground`
4. Vào [OAuth Playground](https://developers.google.com/oauthplayground):
   - ⚙️ Settings → tick *Use your own OAuth credentials* → dán Client ID/Secret.
   - Step 1: chọn scope `https://www.googleapis.com/auth/drive.file` → Authorize.
   - Step 2: Exchange token → copy **refresh_token**.
5. (Tùy chọn) Tạo thư mục trên Drive, lấy Folder ID từ URL để upload vào đó.

Điền vào `backend/.env`:

```env
GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxx
GOOGLE_REFRESH_TOKEN=xxxx
GOOGLE_DRIVE_FOLDER_ID=            # bỏ trống = lưu tại root của Drive
```

> Scope `drive.file` chỉ cho phép app thao tác file **do chính app tạo** — an toàn cho dữ liệu Drive còn lại.

## 3. Sử dụng giao diện Admin

`/admin/backups`:

- **Lịch sao lưu**: chọn Hàng ngày / Hàng tuần / Hàng tháng, bật/tắt, chỉ định Folder ID → Lưu.
- **Sao lưu ngay**: dump + upload thủ công bất cứ lúc nào.
- **Phục hồi**: nút "Phục hồi" trên bản ghi có `drive_file_id` → tải file từ Drive về → import ngược vào MariaDB (bắt buộc xác nhận `confirmation=yes`, frontend hiển thị hộp thoại cảnh báo ghi đè).

## 4. Lệnh CLI

```bash
php artisan backup:run           # tôn trọng tần suất đã cấu hình
php artisan backup:run --force   # chạy bất kể tần suất
```

## 5. Lưu ý bảo mật

- `mysqldump`/`mysql` chạy bằng user CSDL trong `.env` — đảm bảo binary có trong PATH hoặc cấu hình `BACKUP_MYSQLDUMP_PATH=/usr/bin/mysqldump`.
- File dump chứa toàn bộ dữ liệu (kể cả hash mật khẩu) — giữ `.env` an toàn, giới hạn quyền đọc `storage/app/backups`.
- Phục hồi sẽ **ghi đè** dữ liệu hiện tại; hệ thống yêu cầu xác nhận kép (confirm modal + tham số `confirmation=yes`).
