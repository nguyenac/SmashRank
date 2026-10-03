<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Chỉ quản trị viên mới có quyền truy cập khu vực này.',
            ], 403);
        }

        return $next($request);
    }
}
