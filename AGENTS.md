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
- **Local Persistence & Sync Queue**: `attendanceStore.ts` stores offline entries with optimistic UI updates.
- **Schedule Validation on Sync**: When reconnecting to the network, cached entries outside the scheduled duty shift are pruned to uphold data integrity.
- **Silent Counting UX**: No disruptive toasts on button presses. Network state transitions use subtle yellow (offline) and green (synced) indicators.
- **Auto-Replacing Latest Entry Banner**: Located in `AttendanceMonitor.vue`, replaces immediately on new entries using Vue's `<transition name="fade" mode="out-in">` keyed by entry ID/slug.
- **Dynamic Real-Time Stats**: `AttendanceView.vue` computes live totals directly across cached and server logs.

### 3. Dual Ethiopian & Gregorian Date/Time Clock
- `UnifiedDateTimeClock.vue` is embedded in the top navbar across both desktop and mobile viewports.
- Synchronizes Gregorian and Ethiopian calendar dates and live clock time consistently across devices.

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
