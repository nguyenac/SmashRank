<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Notification Manager phía server: gửi Web Push (PWA service worker)
 * khi có: tin tức mới, lịch ghép cặp trận đấu thay đổi, VĐV theo dõi
 * thay đổi thứ hạng.
 *
 * Cần cấu hình VAPID trong .env:
 *   php artisan vendor:publish --provider="Minishlink\WebPush\WebPushServiceProvider" (không bắt buộc)
 *   VAPID_PUBLIC_KEY= / VAPID_PRIVATE_KEY= / VAPID_SUBJECT=mailto:admin@your-domain.com
 *   (sinh key: npx web-push generate-vapid-keys)
 */
class NotificationService
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.vapid.public_key'))
            && ! empty(config('services.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('services.vapid.public_key');
    }

    /** Gửi cho tất cả subscription (hoặc của 1 user). */
    public function push(string $title, string $body, string $url = '/', ?int $userId = null): int
    {
        if (! $this->isConfigured()) {
            Log::info("Push (chưa cấu hình VAPID, chỉ log): {$title} — {$body}");
            return 0;
        }

        $subs = PushSubscription::when($userId, fn ($q) => $q->where('user_id', $userId))->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.vapid.subject', 'mailto:admin@your-domain.com'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);
        $sent = 0;

        foreach ($subs as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }

        return $sent;
    }

    /** Thông báo tin tức mới cho mọi người dùng đăng ký. */
    public function notifyNews($news): int
    {
        return $this->push('📰 Tin tức cầu lông mới', $news->title, '/news');
    }

    /** Thông báo lịch ghép cặp trận đấu được cập nhật cho giải. */
    public function notifyPairingUpdated($tournament, string $detail = ''): int
    {
        return $this->push('🎾 Lịch ghép cặp cập nhật', "{$tournament->name}{$detail}", "/tournaments/{$tournament->id}/live");
    }

    /** Thông báo VĐV theo dõi có thay đổi thứ hạng. */
    public function notifyRankChange($follow, string $changeText): int
    {
        $a = $follow->athlete;

        return $this->push(
            '📈 Thay đổi thứ hạng',
            "{$a->full_name}: {$changeText}",
            "/athletes/{$a->id}",
            $follow->user_id
        );
    }
}
