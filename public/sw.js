const CACHE_NAME = 'unilib-pwa-v2';
const STATIC_ASSETS = [
    '/',
    '/attendance',
    '/manifest.json',
    '/favicon.ico',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png'
];

// Install: Cache critical static assets and application shell
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

// Fetch: Stale-while-revalidate for assets/pages, Network-first for API with offline fallback
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Bypass non-GET requests (e.g. POST /api/physical/attendance/sync handled by IndexedDB queue)
    if (request.method !== 'GET') {
        return;
    }

    // API calls: Network first, cache-fallback, then graceful offline mock
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(async () => {
                // Try to serve previously cached API response
                const cached = await caches.match(request);
                if (cached) {
                    return cached;
                }

                return new Response(
                    JSON.stringify({
                        offline: true,
                        success: true,
                        data: [],
                        message: 'Device is offline. Offline queue active.',
                    }),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    }
                );
            })
        );
        return;
    }

    // Navigation and static assets: Cache first / Stale-while-revalidate
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                // Background update
                fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, networkResponse);
                        });
                    }
                }).catch(() => {});
                return cachedResponse;
            }

            return fetch(request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            }).catch(() => {
                // If navigation request and completely offline, serve the cached SPA shell
                if (request.mode === 'navigate') {
                    return caches.match('/attendance').then((attRes) => {
                        return attRes || caches.match('/');
                    });
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
