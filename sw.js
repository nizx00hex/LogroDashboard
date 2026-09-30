// LOGRO AMS - Service Worker for Complete Offline Attendance Scanner
const CACHE_NAME = 'logro-ams-scanner-v1';
const STATIC_ASSETS = [
    './scanner.html',
    './assets/css/style.css',
    './assets/js/html5-qrcode.min.js',
    './manifest.json',
    './uploads/staff/default.png',
    './assets/icons/icon-192.png',
    './assets/icons/icon-512.png'
];

// Install: Cache all essential static shell assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Network-first for dynamic requests, Cache-fallback for offline reliability
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle static assets and scanner.html; bypass everything else (pages, php scripts, ajax APIs)
    if (url.pathname.endsWith('.php') || url.pathname.includes('/pages/') || url.pathname.includes('/ajax/') || request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((networkResponse) => {
                // If successful network response for static asset or page, update cache
                if (networkResponse && networkResponse.status === 200 && request.method === 'GET') {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                // Offline fallback from cache
                return caches.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    if (request.mode === 'navigate') {
                        return caches.match('./scanner.html');
                    }
                    return new Response('Network offline and asset not cached', {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: { 'Content-Type': 'text/plain' }
                    });
                });
            })
    );
});
