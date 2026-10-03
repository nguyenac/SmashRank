<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
    ],

    'gemini' => [
        // Coach Bot nâng cấp AI (tùy chọn)
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'veo' => [
        // Tạo video tổng hợp kỹ thuật 15s bằng Google Veo (tùy chọn)
        'api_key' => env('GOOGLE_VEO_API_KEY'),
        'endpoint' => env('GOOGLE_VEO_ENDPOINT', 'https://generativelanguage.googleapis.com/v1/models/veo:generateVideo'),
    ],

    'vapid' => [
        // Web Push (PWA). Sinh key: npx web-push generate-vapid-keys
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@your-domain.com'),
    ],

    'translate' => [
        // Tùy chọn: key Google Cloud Translation để dịch tên sản phẩm EN→ZH.
        // Không có key thì dùng bảng thuật ngữ tích hợp sẵn (TranslationService).
        'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
    ],

    'backup' => [
        'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql_path' => env('BACKUP_MYSQL_PATH', 'mysql'),
    ],
];
