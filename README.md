# UniLibrary — University Library & Turn-Based Duty Management System

A clean, modern, and robust Fullstack **Laravel 13** and **Vue 3** University Library System designed with enterprise-grade user management, Spatie roles & permissions, Laravel Sanctum authentication, Pinia state management with persisted state, high-performance database caching, and **duty-schedule turn enforcement**.

---

## 🏛️ Key Capabilities & Features

### 1. User & Identity Management
- Fields: `name` (Full Name), `username` (unique, lowercase alphanumeric), `email` (unique), `password`, `role`, `status` (active/inactive/suspended), `phone`.
- Roles & Permissions powered by `spatie/laravel-permission`:
  - **Super Admin / Library Director**: Full system configuration, user management, duty shift scheduling, and master override over all library functions.
  - **Head Librarian**: Manages books catalog, circulation, and assigns duty turns.
  - **Desk Librarian (Staff)**: Performs day-to-day operations (circulation desk, catalog review).
  - **Student Member**: Searches catalog, views available books and loan history.

### 2. Turn & Schedule-Based Duty Access Control (Core Innovation)
- Users can log in at any time to review the catalog, browse books, check their profile, and inspect their personal duty calendar.
- However, **high-privilege library operations** (such as **Issuing & Checking Out Books**, **Waiving Overdue Fines**, or **Auditing Rare Book Stacks**) are strictly gated by the user's **Duty Schedule**:
  - Gated via `EnsureScheduledDutyTurn` middleware and `DutyScheduleService`.
  - If the user is on duty for that specific task on today's date within active shift hours (or possesses an administrative override), the action proceeds smoothly.
  - If the user is off duty, the request is intercepted with an HTTP 403 response providing an explanatory notice and their **next scheduled duty turn date**:
    ```json
    {
      "success": false,
      "message": "You are logged in, but you can only perform 'Book Circulation & Issue' during your assigned duty schedule. You have no active shift scheduled for today (2026-11-15).",
      "code": "OUTSIDE_DUTY_TURN",
      "duty_gate": {
        "task": "book_circulation",
        "task_name": "Book Circulation & Issue",
        "is_on_duty_today": false,
        "next_turn": "2026-11-18"
      }
    }
    ```
- Fast performance backed by query caching (`Cache::remember`), auto-invalidated on schedule updates.

### 3. University Library Catalog & Circulation
- Comprehensive book catalog with ISBN, author, title, category (Computer Science, Medicine, Law, Engineering), shelf location, total copies, and live available copies count.
- Circulation tracking: Book loans with borrower details, issuing librarian ID, due dates, overdue detection, and returns.

### 4. Clean UI Matching Gibi-Gubae Design System
- Modern dark/light theme tokens matching `/home/daniel/Documents/Projects/fullstack/Gibi-Gubae`.
- Deep modern sidebar with Lucide icons, responsive collapsed states, and live on-duty status indicators.
- Top navigation bar displaying real-time duty badge and user profile avatar.
- Quick toast notifications and modals.

---

## 🔑 Pre-Seeded Demo Accounts

All accounts use the password: `password123`

| Username | Role | Scheduled Today? | Behavior when Issuing a Book |
| :--- | :--- | :---: | :--- |
| **`admin`** | Super Admin | *Admin Override* | Allowed (Administrative master privilege) |
| **`tigist_duty`** | Desk Librarian | **YES (08:00 - 17:30)** | **Allowed** (Book issued, copies decremented) |
| **`yohannes_offduty`** | Desk Librarian | **NO** (Next turn: +3 days) | **Blocked** (403 Duty Gate with next turn prompt) |
| **`sara_student`** | Student Member | N/A | Patron account for borrowing |

---

## 🚀 Running the Project

### 1. Requirements
- PHP 8.3+ with `pdo_pgsql`
- PostgreSQL 16+ running on local port
- Node.js 20+ and npm

### 2. Environment Setup
```bash
cd /home/daniel/Documents/Projects/fullstack/univ-library
cp .env.example .env # (Already configured for PostgreSQL univ_library_db)
```

### 3. Run Database Migrations & Seeds
```bash
php artisan migrate:fresh --seed
```

### 4. Build Frontend Assets
```bash
npm run build
```

### 5. Launch the Application
Run both backend and frontend servers:
```bash
composer start
# or concurrently:
# php artisan serve --host=127.0.0.1 --port=8000
# npm run dev
```
Access the application at: **http://127.0.0.1:8000**

---

## 🧪 Automated Testing

Run the feature tests verifying authentication and schedule gate enforcement:
```bash
php artisan test
```
Outputs:
```text
   PASS  Tests\Feature\DutyScheduleEnforcementTest
  ✓ user can login with username or email
  ✓ on duty librarian can issue book today
  ✓ off duty librarian is blocked by schedule gate
  ✓ admin has override privilege
```
