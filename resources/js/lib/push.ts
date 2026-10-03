import { api } from './api';

/**
 * Đăng ký push notification (PWA Web Push) cho thiết bị hiện tại.
 * Cần VAPID_PUBLIC_KEY cấu hình trong backend .env.
 */
export async function subscribePush(userId: number | null): Promise<void> {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    throw new Error('Trình duyệt không hỗ trợ Web Push.');
  }

  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    throw new Error('Bạn chưa cho phép thông báo.');
  }

  const { data } = await api.get<{ data: { public_key: string | null } }>('/push/vapid-public-key');
  if (!data.public_key) {
    throw new Error('Server chưa cấu hình VAPID keys.');
  }

  const registration = await navigator.serviceWorker.ready;
  const existing = await registration.pushManager.getSubscription();
  const subscription =
    existing ??
    (await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(data.public_key),
    }));

  const json = subscription.toJSON();
  await api.post('/push/subscribe', {
    endpoint: json.endpoint,
    keys: { p256dh: json.keys?.p256dh, auth: json.keys?.auth },
  });
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; ++i) {
    output[i] = raw.charCodeAt(i);
  }
  return output;
}
