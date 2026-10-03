<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailOtpCode;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private TotpService $totp)
    {
    }

    // ------------------------------------------------------------------
    // Đăng ký / Đăng nhập
    // ------------------------------------------------------------------

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'user',
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'two_factor_enabled']),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email hoặc mật khẩu không chính xác.'], 401);
        }

        // 2FA đã bật: trả về challenge token, chưa cấp token API
        if ($user->two_factor_enabled) {
            return $this->createChallengeResponse($user);
        }

        // Chưa bật 2FA: cấp token ngay nhưng báo frontend yêu cầu cấu hình 2FA
        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'two_factor_enabled']),
            'token' => $user->createToken('auth')->plainTextToken,
            'two_factor_setup_required' => true,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only([
                'id', 'name', 'email', 'role', 'two_factor_enabled', 'two_factor_method',
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    // Xác thực 2 lớp (2FA)
    // ------------------------------------------------------------------

    private function createChallengeResponse(User $user): JsonResponse
    {
        $challengeToken = Str::random(64);
        Cache::put("2fa_challenge:{$challengeToken}", $user->id, now()->addMinutes(10));

        return response()->json([
            'two_factor_required' => true,
            'challenge_token' => $challengeToken,
            'two_factor_method' => $user->two_factor_method,
            'masked_email' => $this->maskEmail($user->email),
        ]);
    }

    public function sendEmailCode(Request $request): JsonResponse
    {
        $data = $request->validate(['challenge_token' => ['required', 'string']]);
        $userId = Cache::get("2fa_challenge:{$data['challenge_token']}");
        if (! $userId) {
            return response()->json(['message' => 'Phiên xác thực đã hết hạn, vui lòng đăng nhập lại.'], 422);
        }

        $user = User::findOrFail($userId);
        $code = (string) random_int(100000, 999999);

        EmailOtpCode::create([
            'email' => $user->email,
            'code' => $code,
            'expires_at' => now()->addMinutes(5),
        ]);

        Mail::raw(
            "Mã xác thực 2 lớp SmashRank của bạn là: {$code}\nMã có hiệu lực trong 5 phút.",
            function ($message) use ($user) {
                $message->to($user->email)->subject('SmashRank - Mã xác thực 2 lớp');
            }
        );

        return response()->json(['message' => 'Đã gửi mã OTP tới email của bạn.']);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $userId = Cache::get("2fa_challenge:{$data['challenge_token']}");
        if (! $userId) {
            return response()->json(['message' => 'Phiên xác thực đã hết hạn, vui lòng đăng nhập lại.'], 422);
        }

        $user = User::findOrFail($userId);
        $valid = false;

        if ($user->two_factor_method === 'email') {
            $valid = EmailOtpCode::where('email', $user->email)
                ->where('code', preg_replace('/\D/', '', $data['code']))
                ->where('consumed', false)
                ->where('expires_at', '>', now())
                ->exists();
            if ($valid) {
                EmailOtpCode::where('email', $user->email)
                    ->where('code', preg_replace('/\D/', '', $data['code']))
                    ->where('consumed', false)
                    ->update(['consumed' => true]);
            }
        } else {
            $valid = $this->totp->verify($user->two_factor_secret ?? '', $data['code']);
        }

        if (! $valid) {
            return response()->json(['message' => 'Mã xác thực không chính xác.'], 422);
        }

        Cache::forget("2fa_challenge:{$data['challenge_token']}");

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'two_factor_enabled']),
            'token' => $user->createToken('auth')->plainTextToken,
        ]);
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->totp->generateSecret();
        $user->update(['two_factor_secret' => $secret, 'two_factor_enabled' => false]);

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $this->totp->otpauthUri($secret, $user->email),
            'instructions' => 'Nhập secret trên vào Google Authenticator (hoặc quét URI otpauth), rồi gọi /2fa/enable với mã 6 số hiện tại.',
        ]);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'method' => ['in:totp,email'],
        ]);

        $user = $request->user();

        if ($user->two_factor_method === 'totp' && ($data['method'] ?? 'totp') === 'totp') {
            if (! $this->totp->verify($user->two_factor_secret ?? '', $data['code'])) {
                return response()->json(['message' => 'Mã TOTP không chính xác, chưa thể bật 2FA.'], 422);
            }
        }

        if (($data['method'] ?? null) === 'email') {
            $user->update(['two_factor_method' => 'email']);
        }

        $user->update(['two_factor_enabled' => true]);

        return response()->json(['message' => 'Đã bật xác thực 2 lớp.']);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        $valid = $user->two_factor_method === 'email'
            ? EmailOtpCode::where('email', $user->email)
                ->where('code', preg_replace('/\D/', '', $data['code']))
                ->where('expires_at', '>', now())->exists()
            : $this->totp->verify($user->two_factor_secret ?? '', $data['code']);

        if (! $valid) {
            return response()->json(['message' => 'Mã xác thực không chính xác.'], 422);
        }

        $user->update(['two_factor_enabled' => false, 'two_factor_secret' => null]);

        return response()->json(['message' => 'Đã tắt xác thực 2 lớp.']);
    }

    // ------------------------------------------------------------------
    // Khôi phục mật khẩu cơ bản
    // ------------------------------------------------------------------

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();

        // Luôn trả về 200 để tránh dò email tồn tại
        if ($user) {
            $token = Str::random(64);
            \DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $resetUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/')
                .'/reset-password?token='.$token.'&email='.urlencode($user->email);

            Mail::raw(
                "Bạn yêu cầu đặt lại mật khẩu SmashRank.\nNhấn liên kết (hiệu lực 60 phút):\n{$resetUrl}\n\nNếu không phải bạn, hãy bỏ qua email này.",
                function ($message) use ($user) {
                    $message->to($user->email)->subject('SmashRank - Đặt lại mật khẩu');
                }
            );
        }

        return response()->json(['message' => 'Nếu email tồn tại, hướng dẫn đặt lại mật khẩu đã được gửi.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = \DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $record
            || ! Hash::check($data['token'], $record->token)
            || now()->diffInMinutes(\Carbon\Carbon::parse($record->created_at)) > 60) {
            return response()->json(['message' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'], 422);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['password' => $data['password']]);
        \DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        $user->tokens()->delete(); // đăng xuất mọi phiên cũ

        return response()->json(['message' => 'Đã đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.']);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email);

        return substr($name, 0, 2).'***@'.$domain;
    }
}
