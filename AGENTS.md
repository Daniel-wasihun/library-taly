# UniLibrary — Project Context & Agent Memory (AGENTS.md)

## 📌 Project Overview
**UniLibrary** is an enterprise-grade University Library & Duty Management System designed for **Woldia University Library**, built with **Laravel 13 (PHP 8.3)** and **Vue 3.5 (TypeScript + Vite 8 + Tailwind CSS v4)**.

- **Production URL**: [https://unilibrary-portal.onrender.com](https://unilibrary-portal.onrender.com)
- **Git Repository**: `https://github.com/Daniel-wasihun/library-taly.git` (primary branch: `main`)
- **Render Service ID**: `srv-dc2u16jm8hqs73abgcr0`
- **Database**: Remote Supabase PostgreSQL (`db.wclmfdoxqnvikbkslsqf.supabase.co`) with configurable `DB_SCHEMA` support for isolated schema setups.

---

## 🏛️ Core Architectural Modules

### 1. Turn & Schedule-Based Duty Access Control
- High-privilege library operations (circulation, attendance counting, fine waivers) are gated by `EnsureScheduledDutyTurn` middleware and `DutyScheduleService.php`.
- Attendants are restricted to their assigned library branch and scheduled duty shift time window.
- Out-of-shift attempts return specific codes (`OUTSIDE_DUTY_TURN`, `OUTSIDE_SHIFT_HOURS`, `BRANCH_RESTRICTED`).
- Backend error responses are localized client-side to Amharic in `AttendanceCounter.vue` via `localizedDutyReason`.

### 2. Offline-First PWA Attendance Counter
- **PWA Infrastructure**: Service Worker (`public/sw.js`), `manifest.json`, mobile-capable meta tags, and Woldia University Library clean branding/splash screen.
- **PWA Route Memory & State Persistence**: `router/index.ts` automatically tracks user navigation (`unilib_pwa_last_route`) and seamlessly restores the user's exact previous page upon reopening the standalone PWA or browser tab, with permission and authentication checks.
- **PWA Install Prompt**: Captures `beforeinstallprompt` to present a tailored, professional install banner (`PwaInstallPrompt.vue`) and provides a persistent "Install App" button in the navbar when running in a browser tab or after app removal, with a 3-step guide for iOS Safari.
- **Custom In-App Official Report Preview Modal (`ReportPreviewModal.vue`)**: Replaced external browser tabs (`window.open`) with a custom in-app document preview modal that renders official WDU headers, reference numbers, KPI cards, clean demographic tables, and seals directly within the standalone PWA without opening external tabs. Users can trigger isolated system printing or download PDF, Excel, and CSV files directly to any selected directory via the universal file downloader (`saveFileWithPickerOrFallback`).
- **Strict 2-Minute Invariant for Edits & Deletions**: Both backend (`AttendanceController.php`) and frontend (`AttendanceView.vue`) enforce a strict 120-second window from `entry_at`. The record remains editable/deletable for exactly 120 seconds, and is disabled strictly after 2 minutes have elapsed (anchored to `Africa/Addis_Ababa` +03:00 via `parseEntryTime` to eliminate timezone drift and skew) with **zero bypass, even for Super Admin**.
- **Multi-Device Real-Time Auto-Sync & Polling**: Real-time counter and attendance logs poll silently every 10 seconds in `AttendanceView.vue` and every 15 seconds in `DashboardView.vue`, with instant synchronization triggers on window focus and visibility change. Usage recorded on one computer is instantly reflected across all other terminals without requiring manual reloads.
- **Academic Years Management**: Strictly only **2019 E.C.** (`AY-2019-EC`, 2026/2027 G.C.) exists across the database and UI, with legacy academic years purged.
- **Professional Rotational Duty Schedules**: Seeded 4-group (A, B, C, D) Day and Night rotating shift schedules for previous and current Ethiopian calendar months (Meskerem 1, Tikimt 2, Hidar 3 - current, and Tahsas 4 of 2019 E.C.).
- **Executive-Level Clean Analytics Exports**: Report templates (`attendance_print.blade.php`, `AttendanceReportService.php`) streamlined to present general and relevant quantitative metrics, demographic tables, and branch traffic figures without verbose predictions or paragraph clutter.
- **Service Worker Caching Strategy**: Network-First strategy for HTML navigation requests to prevent stale hashed Vite bundle references. Strict MIME-type checking ensures missing static assets (`.js`, `.css`) never get cached as HTML error pages. Missing assets return 404 in `routes/web.php` rather than triggering SPA fallbacks. Report downloads, exports, and print endpoints explicitly bypass the Service Worker directly to network (`unilib-pwa-v5`) to prevent offline fallback mock JSON from corrupting binary exports.
- **DomPDF Fonts Cache**: Directory `storage/fonts` is initialized with `0775` permissions in `docker/entrypoint.sh` and dynamically created in `AttendanceController.php` ensuring robust server-side PDF generation on Linux/Docker environments.
- **Client Auto-Recovery**: `app.blade.php` and `router/index.ts` include auto-recovery listeners that detect chunk load/MIME errors and automatically refresh or allow quick one-tap cache purge and reload.
- **Local Persistence & Sync Queue**: `attendanceStore.ts` stores offline entries with optimistic UI updates.
- **Schedule Validation on Sync**: When reconnecting to the network, cached entries outside the scheduled duty shift are pruned to uphold data integrity.
- **Silent Counting UX**: No disruptive toasts on button presses. Network state transitions use subtle yellow (offline) and green (synced) indicators.
- **Auto-Replacing Latest Entry Banner**: Located in `AttendanceMonitor.vue`, replaces immediately on new entries using Vue's `<transition name="fade" mode="out-in">` keyed by entry ID/slug.
- **Dynamic Real-Time Stats**: `AttendanceView.vue` computes live totals directly across cached and server logs.

### 3. Dual Ethiopian & Gregorian Date/Time Clock & Absolute Timezone Accuracy
- `UnifiedDateTimeClock.vue` is embedded in the top navbar across both desktop and mobile viewports.
- Standardized strictly to `Africa/Addis_Ababa` (UTC+3, EAT) using `Intl.DateTimeFormat` on frontend and Laravel `APP_TIMEZONE=Africa/Addis_Ababa` on backend.
- Eloquent `LibraryAttendance` serializes timestamps with explicit ISO 8601 offset (`Y-m-d\TH:i:sP`), preventing dual +3 hour offset drift.
- Synchronizes Gregorian and Ethiopian calendar dates and live dawn clock (6:00 AM dawn = 12:00) consistently across devices.

### 4. UI Design System & Dark Mode Standards
- Styled with Tailwind CSS v4 utilizing Gibi-Gubae / Senbet design tokens (`app.css`).
- High-contrast dark mode support: Male counter batch buttons (`+2` .. `+15`) and primary button use explicit `dark:text-white` on `dark:bg-slate-900/90` to avoid illegible blue-on-dark contrast.

---

## 🛠️ Tech Stack & Directory Structure

- **Backend**: Laravel 13, PHP 8.3, Laravel Sanctum, Spatie Permission, Barryvdh DomPDF.
  - `app/Http/Controllers/`: API and web controllers (Attendance, Duty, Books, Users).
  - `app/Services/DutyScheduleService.php`: Core duty validation and scheduling engine.
  - `app/Http/Middleware/EnsureScheduledDutyTurn.php`: Route protection middleware.
- **Frontend**: Vue 3.5, Vite 8, Pinia (persisted state), Vue Router 4, Tailwind CSS v4, Lucide Vue icons.
  - `resources/js/views/attendance/`: Attendance tracking components (`AttendanceCounter.vue`, `AttendanceMonitor.vue`, `AttendanceAnalytics.vue`, `AttendanceHeader.vue`).
  - `resources/js/stores/`: `attendanceStore.ts`, `authStore.ts`, `languageStore.ts`, `libraryStore.ts`.
  - `resources/js/components/common/`: `UnifiedDateTimeClock.vue`.
  - `resources/css/app.css`: Design tokens, theme colors, transition classes.

---

## 🚀 Key Commands & Operational Rules

### Build & Test
```bash
# Compile frontend assets
npm run build

# Run local development servers
npm run dev

# Run Laravel test suite
php artisan test
```

### Git & Deploy Workflow
- **Auto-Deploy**: Render automatically triggers a deployment when changes are pushed to `origin/main`.
- **Render API Trigger**:
  ```bash
  curl -s -X POST -H "Authorization: Bearer <RENDER_API_KEY>" \
    "https://api.render.com/v1/services/srv-dc2u16jm8hqs73abgcr0/deploys" \
    -H "Content-Type: application/json" -d '{"clearCache":"do_not_clear"}'
  ```
- **File System & Sandbox Note**: When executing builds (`npm run build`) or Git commands, always ensure file write permissions (`BypassSandbox: true` where applicable).
