-- ============================================================
-- migration_v2.sql — Run ONCE on your existing XAMPP database
-- In phpMyAdmin: select DB `lost_and_found` → SQL → paste this file
-- Or: mysql -u root lost_and_found < migration_v2.sql
-- ============================================================
-- Backs up old claims (simple rename). If you have no important data in `claims`, safe to run.
-- ============================================================

USE `lost_and_found`;

-- Old claims table (different shape) — archive then replace
DROP TABLE IF EXISTS `claims_old_backup`;
RENAME TABLE `claims` TO `claims_old_backup`;

ALTER TABLE `lost_items`
  ADD COLUMN `resolved` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

ALTER TABLE `found_items`
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`,
  ADD COLUMN `approval_hold` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

UPDATE `found_items` SET `is_active` = 1 WHERE `status` = 'found';

-- New claims model (proofs, link to found + lost)
CREATE TABLE `claims` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `found_item_id` INT UNSIGNED NOT NULL,
  `lost_item_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `proof_description` TEXT NOT NULL,
  `serial_number` VARCHAR(255) DEFAULT NULL,
  `box_receipt_notes` TEXT DEFAULT NULL,
  `device_unlock_password` VARCHAR(255) DEFAULT NULL,
  `admin_reject_reason` TEXT DEFAULT NULL,
  `handled_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_found_lost` (`user_id`,`found_item_id`,`lost_item_id`),
  KEY `idx_found` (`found_item_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_claims_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_claims_found` FOREIGN KEY (`found_item_id`) REFERENCES `found_items`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_claims_lost` FOREIGN KEY (`lost_item_id`) REFERENCES `lost_items`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_claims_handled` FOREIGN KEY (`handled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `claim_photos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `claim_id` INT UNSIGNED NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_claim` (`claim_id`),
  CONSTRAINT `fk_claim_photos_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `issued_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `claim_id` INT UNSIGNED NOT NULL,
  `legal_name` VARCHAR(255) NOT NULL,
  `id_passport_number` VARCHAR(120) NOT NULL,
  `handover_photo_path` VARCHAR(255) NOT NULL,
  `issued_by_admin_id` INT UNSIGNED NOT NULL,
  `issued_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_claim` (`claim_id`),
  CONSTRAINT `fk_issued_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_issued_admin` FOREIGN KEY (`issued_by_admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `notifications`
  ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `preview` VARCHAR(500) NULL DEFAULT NULL AFTER `message`,
  ADD COLUMN `link` VARCHAR(512) NULL DEFAULT NULL AFTER `preview`,
  ADD COLUMN `ref_type` VARCHAR(32) NULL DEFAULT NULL AFTER `link`,
  ADD COLUMN `ref_id` INT UNSIGNED NULL DEFAULT NULL AFTER `ref_type`;
