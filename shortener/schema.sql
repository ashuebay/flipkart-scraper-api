-- MySQL schema for PHP shortener with per-target daily limits
-- Engine: InnoDB, Charset: utf8mb4

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Shortener definitions
CREATE TABLE IF NOT EXISTS `shorteners` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `namespace` VARCHAR(64) NULL,
  `code` VARCHAR(128) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_namespace_code` (`namespace`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ordered targets for each shortener
CREATE TABLE IF NOT EXISTS `shortener_targets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shortener_id` BIGINT UNSIGNED NOT NULL,
  `position` INT UNSIGNED NOT NULL,
  `target_url` VARCHAR(2048) NOT NULL,
  `daily_quota` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_targets_shortener`
    FOREIGN KEY (`shortener_id`) REFERENCES `shorteners`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_shortener_position` (`shortener_id`, `position`),
  KEY `idx_shortener_active` (`shortener_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-target daily click counters (one row per day)
CREATE TABLE IF NOT EXISTS `daily_clicks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `click_date` DATE NOT NULL,
  `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_clicks_target`
    FOREIGN KEY (`target_id`) REFERENCES `shortener_targets`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_target_date` (`target_id`, `click_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional helper view to see remaining capacity per target for today
-- Note: Views cannot reference variables like CURDATE() portably per session time zone.
-- You can adapt this if you prefer a materialized report.

