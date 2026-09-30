# Database Migrations

This folder contains database migrations and an automated migration runner for the **Attendance Management System (LOGRO)**.

## Files

- **`001_initial_schema.sql`**: Full MySQL schema creating:
  - `admin` table (with default credentials seeded)
  - `settings` table (with default store closing time seeded)
  - `staff` table (with unique QR code tokens and status)
  - `attendance` table (with foreign keys, unique constraint on staff + date)
- **`migrate.php`**: Automated runner script.

---

## How to Run Migrations

### Option 1: Via PHP CLI (Recommended)

From the project root directory, run:

```bash
php migrations/migrate.php
```

The script will:
1. Connect to MySQL using settings from `config.php`.
2. Automatically create the database `shop_attendance` if it does not exist.
3. Create a `migrations` tracking table.
4. Execute any unapplied `.sql` migration files.

---

### Option 2: Via phpMyAdmin or MySQL Client

You can import `001_initial_schema.sql` directly:

```bash
mysql -u root -p shop_attendance < migrations/001_initial_schema.sql
```

---

## Default Seed Credentials

- **Admin Username**: `admin`
- **Admin Password**: `admin123`
- **Default Closing Time**: `22:00:00` (10:00 PM)
