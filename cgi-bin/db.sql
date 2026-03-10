-- =============================================
-- SCK Harmonogram — schemat bazy danych
-- =============================================
-- ZALECANE: użyj install.php zamiast tego pliku
-- install.php automatycznie tworzy tabele
-- i generuje prawidłowe hashe haseł.
--
-- Jeśli wolisz ręcznie: zaimportuj ten plik,
-- a potem ustaw hasła przez tools/hash.php
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Tabela: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL,
    `password` VARCHAR(255) NOT NULL DEFAULT '',
    `full_name` VARCHAR(191) NOT NULL,
    `department` VARCHAR(100) NOT NULL DEFAULT 'SCK',
    `employment_fraction` DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    `role` ENUM('admin','employee') NOT NULL DEFAULT 'employee',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
    `can_view_all` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Tabela: schedule_entries
-- ----------------------------
CREATE TABLE IF NOT EXISTS `schedule_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `entry_date` DATE NOT NULL,
    `shift_type` VARCHAR(50) NOT NULL DEFAULT 'standard',
    `shift_start` TIME DEFAULT NULL,
    `shift_end` TIME DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_date` (`user_id`, `entry_date`),
    KEY `idx_date` (`entry_date`),
    KEY `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Tabela: notifications
-- ----------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'info',
    `title` VARCHAR(255) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `related_date` DATE DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user_read` (`user_id`, `is_read`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Tabela: settings
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('employee_view_mode', 'own');

-- ----------------------------
-- Tabela: shift_types
-- ----------------------------
CREATE TABLE IF NOT EXISTS `shift_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `label` VARCHAR(100) NOT NULL,
    `color` VARCHAR(20) NOT NULL DEFAULT '#fff',
    `text_color` VARCHAR(20) NOT NULL DEFAULT '#333',
    `default_start` TIME DEFAULT NULL,
    `default_end` TIME DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shift_types` (`code`,`label`,`color`,`text_color`,`default_start`,`default_end`,`sort_order`) VALUES
    ('standard','Zmiana standardowa','#fff7ed','#7c2d12','07:30:00','16:00:00',0),
    ('urlop','Urlop','#22c55e','#fff',NULL,NULL,1),
    ('wolne','Wolne (W)','#f1f5f9','#64748b',NULL,NULL,2),
    ('chorobowe','Chorobowe','#fbbf24','#78350f',NULL,NULL,3),
    ('szkolenie','Szkolenie','#06b6d4','#fff','08:00:00','16:00:00',4),
    ('wydarzenie','Wydarzenie','#ea580c','#fff','09:00:00','17:00:00',5),
    ('swieto','Święto','#dc2626','#fff',NULL,NULL,6),
    ('brak','Brak dyżuru (X)','#cbd5e1','#475569',NULL,NULL,7),
    ('kino','Kino','#f97316','#fff','14:00:00','18:30:00',8),
    ('koncert','Koncert','#ea580c','#fff','17:00:00','21:30:00',9),
    ('bazylika','Bazylika','#7c3aed','#fff','12:30:00','21:30:00',10),
    ('dyzur','Dyżur','#0f172a','#fbbf24',NULL,NULL,11)
ON DUPLICATE KEY UPDATE id=id;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------
-- Seed: konta testowe
-- ----------------------------
-- UWAGA: hasła poniżej to PLACEHOLDER.
-- Po imporcie tego pliku:
--   1. Wejdź na tools/hash.php
--   2. Wygeneruj hash dla "admin123"
--   3. Wygeneruj hash dla "test123"
--   4. Uruchom UPDATE poniżej z prawdziwymi hashami
--   5. USUŃ tools/hash.php
--
-- Albo po prostu użyj install.php — zrobi to za Ciebie.
-- ----------------------------

INSERT INTO `users` (`email`, `password`, `full_name`, `department`, `employment_fraction`, `role`, `active`, `must_change_password`)
VALUES
    ('admin@sck.strzegom.pl',    '__HASH_ADMIN123__',  'Administrator HR', 'Kadry', 1.00, 'admin',    1, 0),
    ('employee@sck.strzegom.pl', '__HASH_TEST123__',   'Bereta Ewa',       'SCK',   1.00, 'employee', 1, 0)
ON DUPLICATE KEY UPDATE id=id;

-- Po wygenerowaniu hashy w tools/hash.php:
-- UPDATE users SET password='TUTAJ_HASH' WHERE email='admin@sck.strzegom.pl';
-- UPDATE users SET password='TUTAJ_HASH' WHERE email='employee@sck.strzegom.pl';
