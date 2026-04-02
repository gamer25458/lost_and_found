-- Migration: Add support for multiple photos per lost/found item

CREATE TABLE IF NOT EXISTS `lost_item_photos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lost_item_id` INT UNSIGNED NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `sort_order` SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_lost_item` (`lost_item_id`),
    FOREIGN KEY (`lost_item_id`) REFERENCES `lost_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `found_item_photos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `found_item_id` INT UNSIGNED NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `sort_order` SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_found_item` (`found_item_id`),
    FOREIGN KEY (`found_item_id`) REFERENCES `found_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
