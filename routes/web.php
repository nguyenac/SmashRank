<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - SmashRank SPA
|--------------------------------------------------------------------------
| Toàn bộ ứng dụng là một SPA (React + TypeScript) được Vite build ra
| public/build. Laravel Blade (resources/views/app.blade.php) render khung
| HTML và fallback mọi đường dẫn về SPA để React Router xử lý điều hướng.
*/

Route::fallback(function () {
    return response()
        ->view('app')
        ->header('Cache-Control', 'no-cache');
});
