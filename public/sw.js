/* SmashRank Service Worker
 * 1) Offline Cache Storage nâng cao: cache-first cho danh sách VĐV & sản phẩm,
 *    network-first cho phần còn lại, cache tĩnh cho assets đã build.
 * 2) Push notifications: tin tức cầu lông mới / lịch ghép cặp trận đấu /
 *    thay đổi thứ hạng VĐV đang theo dõi.
 */

const STATIC_CACHE = 'smashrank-static-v1';
const API_ATHLETES_CACHE = 'smashrank-athletes-v1';
const API_PRODUCTS_CACHE = 'smashrank-products-v1';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(['/', '/manifest.json'])));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => ![STATIC_CACHE, API_ATHLETES_CACHE, API_PRODUCTS_CACHE].includes(k)).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET') return;

  // --- Danh sách VĐV: cache-first với cập nhật nền (stale-while-revalidate) ---
  if (url.pathname.startsWith('/api/athletes') || url.pathname.startsWith('/api/records') || url.pathname.startsWith('/api/birthdays')) {
    event.respondWith(staleWhileRevalidate(event.request, API_ATHLETES_CACHE));
    return;
  }
  // --- Sản phẩm & thương hiệu: cache-first ---
  if (url.pathname.startsWith('/api/products') || url.pathname.startsWith('/api/brands')) {
    event.respondWith(cacheFirst(event.request, API_PRODUCTS_CACHE));
    return;
  }
  // --- Assets build: cache-first ---
  if (url.pathname.startsWith('/build/') || /\.(js|css|png|jpg|svg|woff2)$/.test(url.pathname)) {
    event.respondWith(cacheFirst(event.request, STATIC_CACHE));
    return;
  }
  // --- API còn lại: network-first, fallback cache khi offline ---
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(event.request, API_ATHLETES_CACHE));
  }
});

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response.ok) {
    const cache = await caches.open(cacheName);
    cache.put(request, response.clone());
  }
  return response;
}

async function staleWhileRevalidate(request, cacheName) {
  const cached = await caches.match(request);
  const fetchPromise = fetch(request).then((response) => {
    if (response.ok) {
      caches.open(cacheName).then((cache) => cache.put(request, response.clone()));
    }
    return response;
  }).catch(() => cached);
  return cached || fetchPromise;
}

async function networkFirst(request, cacheName) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    if (cached) return cached;
    return new Response(JSON.stringify({ offline: true, message: 'Bạn đang ngoại tuyến — hiển thị dữ liệu gần nhất.' }), {
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

/* ---------------- Push notifications ---------------- */

self.addEventListener('push', (event) => {
  let data = { title: 'SmashRank', body: 'Có cập nhật mới!', url: '/' };
  try {
    data = { ...data, ...event.data.json() };
  } catch { /* payload text thuần */ }

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/icon-192.png',
      badge: '/icon-192.png',
      data: { url: data.url },
      vibrate: [100, 50, 100],
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      return self.clients.openWindow(url);
    })
  );
});
