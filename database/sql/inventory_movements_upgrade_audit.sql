-- Ejecutar UNA SOLA VEZ después de inventory_movements_upgrade_receipts.sql.
ALTER TABLE `inventory_movements`
  ADD COLUMN `status` ENUM('active','cancelled') NOT NULL DEFAULT 'active' AFTER `notes`,
  ADD COLUMN `cancelled_at` DATETIME NULL AFTER `status`,
  ADD COLUMN `cancelled_by` BIGINT UNSIGNED NULL AFTER `cancelled_at`,
  ADD COLUMN `cancellation_reason` VARCHAR(500) NULL AFTER `cancelled_by`,
  ADD KEY `inventory_movements_status_idx` (`status`),
  ADD CONSTRAINT `inventory_movements_cancelled_by_fk`
    FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;

-- Las recepciones antiguas sin identificador quedan individualizadas para edición y PDF.
UPDATE `inventory_movements`
SET `reception_id` = UUID()
WHERE `type` = 'entrada'
  AND `origin` = 'compra'
  AND `reception_id` IS NULL;

CREATE TABLE `inventory_movement_audits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_movement_id` BIGINT UNSIGNED NOT NULL,
  `reception_id` CHAR(36) NULL,
  `action` ENUM('edited','cancelled') NOT NULL,
  `old_quantity` DECIMAL(14,3) UNSIGNED NOT NULL,
  `new_quantity` DECIMAL(14,3) UNSIGNED NOT NULL,
  `old_unit_cost` DECIMAL(14,2) UNSIGNED NULL,
  `new_unit_cost` DECIMAL(14,2) UNSIGNED NULL,
  `old_total_cost` DECIMAL(16,2) UNSIGNED NULL,
  `new_total_cost` DECIMAL(16,2) UNSIGNED NULL,
  `old_notes` VARCHAR(500) NULL,
  `new_notes` VARCHAR(500) NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_movement_audits_movement_idx` (`inventory_movement_id`),
  KEY `inventory_movement_audits_reception_idx` (`reception_id`),
  CONSTRAINT `inventory_movement_audits_movement_fk`
    FOREIGN KEY (`inventory_movement_id`) REFERENCES `inventory_movements` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `inventory_movement_audits_user_fk`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
