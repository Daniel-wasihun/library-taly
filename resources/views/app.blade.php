<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Woldia University Library</title>

    <!-- Progressive Web App (PWA) Meta & Manifest -->
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#1d4ed8" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="WDU Library" />
    
    <!-- Icons -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png" />
    <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512x512.png" />

    <style>
        @keyframes wdu-pulse {
            0%, 100% { opacity: 0.8; transform: scale(0.98); }
            50% { opacity: 1; transform: scale(1.02); }
        }
        @keyframes wdu-bar {
            0% { left: -40%; width: 30%; }
            50% { left: 30%; width: 50%; }
            100% { left: 100%; width: 30%; }
        }
        #wdu-splash {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #0b1329;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            z-index: 999999;
            padding: 24px;
            text-align: center;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="antialiased bg-[#0b1329]">
    <div id="app">
        <!-- Initial Clean Woldia University Library Splash Screen -->
        <div id="wdu-splash">
            <!-- Clean Logo Icon -->
            <div style="width: 104px; height: 104px; margin-bottom: 24px; animation: wdu-pulse 2.4s ease-in-out infinite;">
                <svg viewBox="0 0 512 512" width="100%" height="100%" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="splashGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#1e3a8a"/>
                            <stop offset="50%" stop-color="#1d4ed8"/>
                            <stop offset="100%" stop-color="#2563eb"/>
                        </linearGradient>
                        <linearGradient id="splashGold" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#fbbf24"/>
                            <stop offset="100%" stop-color="#d97706"/>
                        </linearGradient>
                    </defs>
                    <rect x="24" y="24" width="464" height="464" rx="104" fill="url(#splashGrad)" stroke="url(#splashGold)" stroke-width="8"/>
                    <rect x="36" y="36" width="440" height="440" rx="92" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                    <g transform="translate(256, 140)">
                        <circle cx="0" cy="0" r="16" fill="url(#splashGold)"/>
                        <path d="M0 -30 L0 30 M-30 0 L30 0 M-21 -21 L21 21 M-21 21 L21 -21" stroke="#fcd34d" stroke-width="4" stroke-linecap="round"/>
                        <circle cx="0" cy="0" r="6" fill="#ffffff"/>
                    </g>
                    <g transform="translate(256, 260)">
                        <path d="M0 -55 C-60 -75 -120 -50 -130 -45 L-130 55 C-120 50 -60 25 0 45 Z" fill="#ffffff" stroke="#cbd5e1" stroke-width="3"/>
                        <line x1="-110" y1="-20" x2="-25" y2="-10" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="-110" y1="2" x2="-25" y2="12" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="-110" y1="24" x2="-45" y2="34" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
                        <path d="M0 -55 C60 -75 120 -50 130 -45 L130 55 C120 50 60 25 0 45 Z" fill="#f8fafc" stroke="#cbd5e1" stroke-width="3"/>
                        <line x1="25" y1="-10" x2="110" y2="-20" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="25" y1="12" x2="110" y2="2" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="25" y1="34" x2="90" y2="24" stroke="#94a3b8" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="0" y1="-56" x2="0" y2="47" stroke="url(#splashGold)" stroke-width="6" stroke-linecap="round"/>
                    </g>
                    <g transform="translate(256, 360)">
                        <rect x="-85" y="0" width="170" height="34" rx="8" fill="url(#splashGold)" stroke="#ffffff" stroke-width="2"/>
                        <text x="0" y="24" text-anchor="middle" font-family="system-ui, -apple-system, sans-serif" font-weight="900" font-size="20" fill="#0f172a" letter-spacing="2">WDU</text>
                    </g>
                </svg>
            </div>

            <!-- University Title -->
            <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.025em; margin: 0 0 6px 0; color: #ffffff;">
                Woldia University Library
            </h1>
            <div style="font-size: 13.5px; font-weight: 700; color: #fbbf24; letter-spacing: 0.05em; margin-bottom: 6px; text-transform: uppercase;">
                የወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት
            </div>
            <p style="font-size: 12.5px; color: #94a3b8; margin: 0 0 28px 0; max-width: 320px; line-height: 1.5;">
                Academic Excellence & Automated Duty Management
            </p>

            <!-- Loading Progress Bar -->
            <div style="width: 160px; height: 4px; background: rgba(255, 255, 255, 0.12); border-radius: 9999px; overflow: hidden; position: relative;">
                <div style="position: absolute; top: 0; bottom: 0; background: linear-gradient(90deg, #3b82f6, #60a5fa, #fbbf24); border-radius: 9999px; animation: wdu-bar 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;"></div>
            </div>

            <!-- Auto-Recovery / Refresh Action if App Mount is Delayed on Mobile -->
            <div id="wdu-splash-recovery" style="display: none; margin-top: 24px;">
                <button type="button" onclick="window.__wduResetAndReload()" style="padding: 9px 20px; font-size: 13px; font-weight: 700; color: #ffffff; background: #2563eb; border: 1px solid rgba(255,255,255,0.25); border-radius: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: all 0.2s;">
                    Reload Portal / እንደገና ጫን
                </button>
            </div>
        </div>
    </div>

    <!-- Client-Side Splash & Cache Auto-Recovery Script -->
    <script>
        window.__wduResetAndReload = function() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(regs) {
                    for (var i = 0; i < regs.length; i++) {
                        regs[i].unregister();
                    }
                    if ('caches' in window) {
                        caches.keys().then(function(keys) {
                            Promise.all(keys.map(function(k) { return caches.delete(k); })).then(function() {
                                window.location.reload(true);
                            });
                        });
                    } else {
                        window.location.reload(true);
                    }
                }).catch(function() {
                    window.location.reload(true);
                });
            } else {
                window.location.reload(true);
            }
        };

        // Show reload button if splash is still on screen after 3.5 seconds
        setTimeout(function() {
            var splash = document.getElementById('wdu-splash');
            var recovery = document.getElementById('wdu-splash-recovery');
            if (splash && recovery && document.getElementById('app') && document.getElementById('app').contains(splash)) {
                recovery.style.display = 'block';
            }
        }, 3500);

        // Auto-purge stale SW cache if module script loading error occurs
        window.addEventListener('error', function(e) {
            var msg = e && (e.message || '');
            if (typeof msg === 'string' && (msg.indexOf('MIME type') !== -1 || msg.indexOf('Failed to load module') !== -1 || msg.indexOf('dynamically imported') !== -1)) {
                console.warn('[UniLibrary] Detected stale bundle error, clearing service worker cache...');
                if ('serviceWorker' in navigator && 'caches' in window) {
                    caches.keys().then(function(keys) {
                        Promise.all(keys.map(function(k) { return caches.delete(k); })).then(function() {
                            window.location.reload(true);
                        });
                    });
                }
            }
        });
    </script>
</body>
</html>
