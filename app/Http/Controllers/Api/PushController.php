<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\AthleteFollow;
use App\Models\PushSubscription;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push notifications (PWA) + theo dõi VĐV.
 *  - POST /push/subscribe        → lưu subscription của service worker
 *  - POST /push/unsubscribe      → hủy
 *  - GET  /push/vapid-public-key → key để frontend subscribe
 *  - POST /follows/{athlete}     → theo dõi VĐV (nhận thông báo thay đổi thứ hạng)
 *  - DELETE /follows/{athlete}   → bỏ theo dõi
 */
class PushController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function vapidPublicKey(): JsonResponse
    {
        return response()->json(['data' => ['public_key' => $this->notifications->publicKey()]]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()?->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ]
        );

        return response()->json(['message' => 'Đã bật thông báo đẩy.']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:500']]);
        PushSubscription::where('endpoint', $data['endpoint'])->delete();

        return response()->json(['message' => 'Đã tắt thông báo đẩy.']);
    }

    public function follow(Request $request, Athlete $athlete): JsonResponse
    {
        AthleteFollow::firstOrCreate([
            'user_id' => $request->user()->id,
            'athlete_id' => $athlete->id,
        ]);

        return response()->json(['message' => "Đã theo dõi {$athlete->full_name} — bạn sẽ nhận thông báo khi thứ hạng thay đổi."]);
    }

    public function unfollow(Request $request, Athlete $athlete): JsonResponse
    {
        AthleteFollow::where('user_id', $request->user()->id)
            ->where('athlete_id', $athlete->id)->delete();

        return response()->json(['message' => 'Đã bỏ theo dõi.']);
    }

    public function myFollows(Request $request): JsonResponse
    {
        return response()->json([
            'data' => AthleteFollow::with('athlete:id,full_name,country_code,elo_rating,avatar_url')
                ->where('user_id', $request->user()->id)
                ->get()
                ->pluck('athlete'),
        ]);
    }
}
