-- Migration: 001_initial_schema.sql
-- Description: Create initial schema and seed data for Attendance Management System (LOGRO)

-- 1. Admin Table
CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `opening_time` TIME NOT NULL DEFAULT '09:30:00',
    `closing_time` TIME NOT NULL DEFAULT '22:00:00',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Staff Table
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `join_date` DATE NOT NULL,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `qr_code` VARCHAR(255) DEFAULT NULL UNIQUE,
    `status` ENUM('active', 'paused') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Attendance Table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `arrived_at` TIME NOT NULL,
    `status` ENUM('full_day', 'half_day', 'absent') NOT NULL DEFAULT 'full_day',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_staff_date` (`staff_id`, `date`),
    INDEX `idx_date` (`date`),
    INDEX `idx_staff_id` (`staff_id`),
    CONSTRAINT `fk_attendance_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed Initial Data
-- Default Settings
INSERT INTO `settings` (`id`, `closing_time`)
SELECT 1, '22:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `settings` LIMIT 1);

-- Default Admin User (username: admin, password: admin123)
INSERT INTO `admin` (`name`, `username`, `password`)
SELECT 'Administrator', 'admin', '$2y$12$eDHILbPM7dZyWd2OP5SZReryKOpyyPXSGEOaIjdflubjnv6AMefpK'
WHERE NOT EXISTS (SELECT 1 FROM `admin` WHERE `username` = 'admin');
