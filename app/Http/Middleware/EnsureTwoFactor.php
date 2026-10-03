<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bắt buộc xác thực 2 lớp (2FA) cho toàn bộ người dùng.
 * Nếu tài khoản chưa bật 2FA, mọi endpoint được bảo vệ sẽ trả về 403
 * kèm mã TWO_FACTOR_REQUIRED để frontend chuyển hướng sang trang cấu hình.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->two_factor_enabled) {
            return response()->json([
                'message' => 'Tài khoản của bạn chưa bật xác thực 2 lớp (2FA). Vui lòng cấu hình để tiếp tục.',
                'code' => 'TWO_FACTOR_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
