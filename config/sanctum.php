<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    'guard' => ['web'],

    // #5 Giảm rủi ro token bị đánh cắp: token tự hết hạn sau 7 ngày,
    // người dùng đăng nhập lại để lấy token mới.
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 60 * 24 * 7),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => \App\Http\Middleware\EncryptCookies::class,
        'validate_csrf_token' => \App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
