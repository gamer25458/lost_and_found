-- ============================================================
-- lost_and_found — complete database schema
-- Run this in phpMyAdmin or via: mysql -u root lost_and_found < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `lost_and_found`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `lost_and_found`;

-- ------------------------------------------------------------
-- 1. USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `full_name`  VARCHAR(100)  NOT NULL,
    `email`      VARCHAR(255)  NOT NULL UNIQUE,
    `password`   VARCHAR(255)  NOT NULL,
    `role`       ENUM('user','admin') NOT NULL DEFAULT 'user',
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. LOST ITEMS (updated with color, unique_details, session_id)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lost_items` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED  NOT NULL,
    `item_name`     VARCHAR(255)  NOT NULL,
    `description`   TEXT,
    `category`      VARCHAR(100),
    `color`         VARCHAR(50)   DEFAULT NULL,      -- NEW
    `location_lost` VARCHAR(255),
    `date_lost`     DATE,
    `session_id`    VARCHAR(100)  DEFAULT NULL,      -- NEW
    `image_path`    VARCHAR(255),
    `status`        ENUM('lost','found') NOT NULL DEFAULT 'lost',
    `resolved`      TINYINT(1)    NOT NULL DEFAULT 0,
    `unique_details` TEXT          DEFAULT NULL,      -- NEW (internal, not user‑visible)
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user`     (`user_id`),
    KEY `idx_name`     (`item_name`),
    KEY `idx_category` (`category`),
    KEY `idx_date`     (`date_lost`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. FOUND ITEMS (updated with color, unique_details, session_id)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `found_items` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED  NOT NULL,
    `item_name`      VARCHAR(255)  NOT NULL,
    `description`    TEXT,
    `category`       VARCHAR(100),
    `color`          VARCHAR(50)   DEFAULT NULL,      -- NEW
    `location_found` VARCHAR(255),
    `date_found`     DATE,
    `session_id`     VARCHAR(100)  DEFAULT NULL,      -- NEW
    `image_path`     VARCHAR(255),
    `status`         ENUM('found','claimed') NOT NULL DEFAULT 'found',
    `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `approval_hold`  TINYINT(1)    NOT NULL DEFAULT 0,
    `unique_details` TEXT          DEFAULT NULL,      -- NEW (internal, stored by admin)
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user`     (`user_id`),
    KEY `idx_name`     (`item_name`),
    KEY `idx_category` (`category`),
    KEY `idx_date`     (`date_found`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. MESSAGES  ← THIS WAS MISSING — root cause of your bug
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `sender_id`   INT UNSIGNED  NOT NULL,
    `receiver_id` INT UNSIGNED  NOT NULL,
    `message`     TEXT          NOT NULL,
    `is_read`     TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sender`   (`sender_id`),
    KEY `idx_receiver` (`receiver_id`),
    KEY `idx_convo`    (`sender_id`, `receiver_id`),
    FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. NOTIFICATIONS  ← THIS WAS MISSING
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `title`      VARCHAR(255)  DEFAULT NULL,
    `message`    TEXT          NOT NULL,
    `preview`    VARCHAR(500)  DEFAULT NULL,
    `link`       VARCHAR(512)  DEFAULT NULL,
    `ref_type`   VARCHAR(32)   DEFAULT NULL,
    `ref_id`     INT UNSIGNED  DEFAULT NULL,
    `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. CLAIMS (proof + found/lost pair)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `claims` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `found_item_id` INT UNSIGNED NOT NULL,
    `lost_item_id`  INT UNSIGNED NOT NULL,
    `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `proof_description` TEXT NOT NULL,
    `serial_number` VARCHAR(255) DEFAULT NULL,
    `box_receipt_notes` TEXT DEFAULT NULL,
    `device_unlock_password` VARCHAR(255) DEFAULT NULL,
    `admin_reject_reason` TEXT DEFAULT NULL,
    `handled_by` INT UNSIGNED  DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_found_lost` (`user_id`,`found_item_id`,`lost_item_id`),
    KEY `idx_user`   (`user_id`),
    KEY `idx_found`  (`found_item_id`),
    KEY `idx_status` (`status`),
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`found_item_id`) REFERENCES `found_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lost_item_id`) REFERENCES `lost_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`handled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `claim_photos` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `claim_id`   INT UNSIGNED  NOT NULL,
    `path`       VARCHAR(255)  NOT NULL,
    `sort_order` SMALLINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_claim` (`claim_id`),
    FOREIGN KEY (`claim_id`) REFERENCES `claims`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `issued_items` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `claim_id`   INT UNSIGNED  NOT NULL,
    `legal_name` VARCHAR(255)  NOT NULL,
    `id_passport_number` VARCHAR(120) NOT NULL,
    `handover_photo_path` VARCHAR(255) NOT NULL,
    `issued_by_admin_id` INT UNSIGNED NOT NULL,
    `issued_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_claim` (`claim_id`),
    FOREIGN KEY (`claim_id`) REFERENCES `claims`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`issued_by_admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. ADMIN ASSIGNMENTS  ← THIS WAS MISSING
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_assignments` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `admin_id`   INT UNSIGNED  NOT NULL,
    `item_id`    INT UNSIGNED  NOT NULL,
    `item_type`  ENUM('lost','found') NOT NULL,
    `status`     ENUM('handling','resolved') NOT NULL DEFAULT 'handling',
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin`   (`admin_id`),
    KEY `idx_item`    (`item_id`, `item_type`),
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. CREATE DEFAULT ADMIN ACCOUNT
-- Password is:  admin123
-- Change this immediately after first login!
-- ------------------------------------------------------------
INSERT IGNORE INTO `users` (`full_name`, `email`, `password`, `role`)
VALUES (
    'Administrator',
    'admin@lostandfound.com',
    '$2y$10$6AE6ajhTPpAyrPPsyVKgXejK2UinbBWNgDXcbm13C0L1Y9pPr3zuq',
    'admin'
);