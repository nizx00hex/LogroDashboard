-- Migration: 002_add_opening_time_to_settings.sql
-- Description: Add editable opening_time column to settings table

ALTER TABLE `settings`
ADD COLUMN `opening_time` TIME NOT NULL DEFAULT '09:30:00' AFTER `id`;
