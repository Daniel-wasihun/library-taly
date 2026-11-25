const CACHE_NAME = 'unilib-pwa-v4';
const STATIC_ASSETS = [
    '/manifest.json',
    '/favicon.ico',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/icons/wdu-logo.svg'
];

// Install: Cache core static assets and skip waiting immediately
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('[SW] Precache warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate: Delete old cache versions and claim clients immediately
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => {
                    console.log('[SW] Purging old cache:', key);
                    return caches.delete(key);
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch handler
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Bypass non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // 1. API Calls: Network-first, cache fallback, then offline mock
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

    // 2. Navigation / HTML Requests: NETWORK-FIRST to always get fresh Vite script hashes
    const isNavigation = request.mode === 'navigate' || 
                         (request.headers.get('accept') && request.headers.get('accept').includes('text/html'));

    if (isNavigation) {
        event.respondWith(
            fetch(request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            }).catch(async () => {
                // If network fails (completely offline), fall back to cached shell
                const cached = await caches.match(request);
                if (cached) {
                    return cached;
                }
                const fallback = await caches.match('/attendance');
                return fallback || caches.match('/');
            })
        );
        return;
    }

    // 3. Static Assets (/build/assets/, images, icons): Cache-first with strict MIME check
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const contentType = networkResponse.headers.get('content-type') || '';
                    
                    // NEVER cache HTML responses for JS or CSS files (prevent MIME type errors)
                    const isJsOrCss = url.pathname.endsWith('.js') || url.pathname.endsWith('.css');
                    if (isJsOrCss && contentType.includes('text/html')) {
                        return networkResponse;
                    }

                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            }).catch(() => {
                // Return null if offline and not in cache
                return null;
            });
        })
    );
});

// Background sync support
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

// Message listener
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
