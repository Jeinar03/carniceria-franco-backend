-- Ejecutar en la misma base MySQL/MariaDB de la aplicación.
-- El saldo se calcula como entradas menos salidas; products.stock deja de utilizarse.
CREATE TABLE `inventory_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `sale_id` BIGINT UNSIGNED NULL,
  `sale_detail_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `reception_id` CHAR(36) NULL,
  `type` ENUM('entrada','salida') NOT NULL,
  `origin` ENUM('compra','venta','cancelacion','ajuste') NOT NULL,
  `quantity` DECIMAL(14,3) UNSIGNED NOT NULL,
  `balance_after` DECIMAL(14,3) NOT NULL,
  `unit` VARCHAR(30) NOT NULL,
  `unit_cost` DECIMAL(14,2) UNSIGNED NULL,
  `total_cost` DECIMAL(16,2) UNSIGNED NULL,
  `notes` VARCHAR(500) NULL,
  `status` ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  `cancelled_at` DATETIME NULL,
  `cancelled_by` BIGINT UNSIGNED NULL,
  `cancellation_reason` VARCHAR(500) NULL,
  `moved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_movements_product_date_idx` (`product_id`, `moved_at`),
  KEY `inventory_movements_type_date_idx` (`type`, `moved_at`),
  KEY `inventory_movements_sale_idx` (`sale_id`),
  KEY `inventory_movements_reception_idx` (`reception_id`),
  KEY `inventory_movements_status_idx` (`status`),
  UNIQUE KEY `inventory_movements_detail_type_origin_uq` (`sale_detail_id`, `type`, `origin`),
  CONSTRAINT `inventory_movements_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `inventory_movements_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_sale_detail_fk` FOREIGN KEY (`sale_detail_id`) REFERENCES `sale_details` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_cancelled_by_fk` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_quantity_chk` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversión inicial: conserva como saldo de apertura las existencias positivas
-- que ya estaban capturadas antes de habilitar el módulo. Se ejecuta una sola vez.
INSERT INTO `inventory_movements`
  (`product_id`, `type`, `origin`, `quantity`, `balance_after`, `unit`, `unit_cost`, `total_cost`, `notes`, `moved_at`, `created_at`, `updated_at`)
SELECT
  p.`id`, 'entrada', 'ajuste', p.`stock`, p.`stock`, COALESCE(p.`unidad_venta`, 'pieza'),
  p.`precio`, ROUND(p.`precio` * p.`stock`, 2), 'Saldo inicial importado de products.stock', NOW(), NOW(), NOW()
FROM `products` p
WHERE p.`stock` > 0;

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
  CONSTRAINT `inventory_movement_audits_movement_fk` FOREIGN KEY (`inventory_movement_id`) REFERENCES `inventory_movements` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `inventory_movement_audits_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La columna products.stock puede conservarse por compatibilidad con el esquema
-- existente, pero desde este cambio el sistema ya no la consulta ni la actualiza.
