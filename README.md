# Attendance Management System (AMS) - LOGRO Showroom

## Overview
The Attendance Management System (AMS) is a modern web application developed for **LOGRO Showroom** to simplify employee attendance tracking, ID badge generation, and shift management.

The system features real-time in-camera QR scanning, zero-reload AJAX operations, dynamic store operating hours, local SQLite3 storage (with MySQL support), and strict **Sri Lanka Standard Time (Asia/Colombo UTC+05:30)** synchronization.

---

## Key Features

### 1. Modern SaaS UI & Zero-Reload AJAX
- **No Page Reloads**: Adding staff, marking attendance, pausing/unpausing accounts, deleting records, and saving settings happen seamlessly in real-time via AJAX with toast notifications.
- **Glassmorphism & SaaS Design**: Responsive typography (Plus Jakarta Sans), dynamic KPI counters, live clock pill, and status badges.
- **Printable ID Badges**: Clean, ready-to-print SVG QR code cards with automatic `@media print` styling.

### 2. Timezone & Operating Hours (Sri Lanka Time)
- **Timezone**: Strict `Asia/Colombo` (UTC+05:30) configuration across database queries, application logic, and client views.
- **Dynamic Operating Hours**: Both **Store Opening Time** and **Store Closing Time** are dynamically configurable in Settings.
- **Shift Classification**:
  - Arrived before 12:00 PM: **Full Day**
  - Arrived 12:00 PM or after (before closing time): **Half Day**
  - Attempts before opening or after closing: Rejected with notification.
  - Days before join date: Labeled **Not Joined** (does not hurt attendance rate).
  - Upcoming dates: Labeled **Upcoming** (neutral indicator).

### 3. Local SQLite3 & Multi-Database Engine
- **SQLite3 (Default)**: Out-of-the-box local file database (`database/attendance.sqlite3`) with WAL mode and foreign key enforcement. Zero setup required!
- **MySQL Support**: Can switch database type anytime by changing `DB_TYPE` to `'mysql'` in `config.php`.

### 4. In-Camera QR Scanner
- Integrated HTML5 camera QR reader with high performance (`scanner.html`).
- Audio-visual feedback on successful or failed check-in with staff avatar preview.
- Dynamic token extraction supporting both full check-in URLs and raw tokens.

---

## Project Structure

```text
Attendance-Management-System---LOGRO/
├── ajax/
│   ├── add_staff_ajax.php            # Multipart staff registration via AJAX
│   ├── checkin_ajax.php              # Real-time QR scanner check-in API
│   ├── get_dashboard_stats_ajax.php  # Dashboard metric synchronization
│   ├── get_staff_history_ajax.php    # Monthly log filter & KPI recalculation
│   ├── mark_attendance.php           # Manual attendance check-in
│   ├── pause_staff.php               # Suspend staff account
│   ├── remove_staff.php              # Delete staff and clean uploaded files
│   ├── unpause_staff.php             # Reactivate staff account
│   └── update_settings_ajax.php      # Operating hours update
├── assets/
│   ├── css/style.css                 # Modern CSS design system
│   └── js/main.js                    # AJAX request handlers & UI state
├── classes/
│   ├── Admin.php                     # Authentication & session management
│   ├── Attendance.php                # Sri Lanka time attendance business logic
│   ├── Database.php                  # PDO wrapper with SQLite3 auto-migration
│   ├── Settings.php                  # Store opening & closing hours
│   └── Staff.php                     # Staff CRUD & SVG QR code generator
├── database/
│   └── attendance.sqlite3            # Local SQLite3 database file
├── migrations/
│   ├── 001_initial_schema.sql
│   ├── 002_add_opening_time_to_settings.sql
│   └── migrate.php                   # Database migration runner
├── pages/
│   ├── dashboard.php                 # Real-time overview & attendance table
│   ├── qrcodes.php                   # Staff ID passes & QR printables
│   ├── settings.php                  # Dynamic operating hours configuration
│   └── staff_details.php             # Individual employee monthly logs & KPIs
├── uploads/
│   ├── qrcodes/                      # Generated vector SVG QR codes
│   └── staff/                        # Staff profile pictures
├── config.php                        # Global environment configuration
├── index.php                         # Modern Admin login portal
├── logout.php                        # Secure session teardown
├── scanner.html                      # Live camera QR scanner application
├── staff_checkin.php                 # Direct browser check-in handler
└── test_time.php                     # System health & Sri Lanka time diagnostics
```

---

## Quick Start & Setup

### 1. Requirements
- PHP 8.1+ with `pdo`, `pdo_sqlite`, `pdo_mysql`, and `gd` extensions enabled.
- Composer.

### 2. Installation
```bash
git clone https://github.com/nizx00hex/Attendance-Management-System---LOGRO.git
cd Attendance-Management-System---LOGRO
composer install
```

### 3. Permissions
Ensure write permissions on `database/` and `uploads/`:
```bash
chmod -R 0777 database uploads
```

### 4. Start Local Development Server
You can run the application directly using PHP's built-in web server:
```bash
php -S 0.0.0.0:8000
```
Open your browser at:
- **Admin Portal**: [http://localhost:8000](http://localhost:8000)
- **Live Camera Scanner**: [http://localhost:8000/scanner.html](http://localhost:8000/scanner.html)
- **Health & Time Check**: [http://localhost:8000/test_time.php](http://localhost:8000/test_time.php)

### 5. Default Credentials
- **Username**: `admin`
- **Password**: `admin123`

---

## Health Check & Verification
Run the built-in diagnostic tool to verify timezone, database connection, and store hours:
```bash
php test_time.php
```
Or open `http://localhost:8000/test_time.php` in your web browser.
