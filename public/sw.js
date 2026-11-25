const CACHE_NAME = 'unilib-pwa-v1';
const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/favicon.ico',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png'
];

// Install: Cache critical static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('[SW] Cache addAll warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate: Clean up older cache versions
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Network-first for API, Stale-while-revalidate for static scripts/styles/HTML
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Bypass non-GET requests (e.g. POST /api/physical/attendance/sync handled by IndexedDB queue)
    if (request.method !== 'GET') {
        return;
    }

    // API calls: Network first with graceful offline response
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request).catch(() => {
                return new Response(
                    JSON.stringify({
                        offline: true,
                        message: 'Device is currently offline. Cached data or offline queue is active.',
                    }),
                    {
                        status: 503,
                        headers: { 'Content-Type': 'application/json' },
                    }
                );
            })
        );
        return;
    }

    // Navigation and static assets: Cache first with network fallback
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                // Fetch fresh copy in background to update cache
                fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, networkResponse);
                        });
                    }
                }).catch(() => {
                    // Offline - cachedResponse already served
                });
                return cachedResponse;
            }

            return fetch(request).then((networkResponse) => {
                if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
                    return networkResponse;
                }

                const responseToCache = networkResponse.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, responseToCache);
                });

                return networkResponse;
            }).catch(() => {
                // If navigation request and offline, serve root page
                if (request.mode === 'navigate') {
                    return caches.match('/');
                }
            });
        })
    );
});

// Background sync support (if supported by browser)
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-attendance-queue') {
        event.waitUntil(
            self.clients.matchAll().then((clients) => {
                clients.forEach((client) => {
                    client.postMessage({ type: 'TRIGGER_ATTENDANCE_SYNC' });
                });
            })
        );
    }
});
