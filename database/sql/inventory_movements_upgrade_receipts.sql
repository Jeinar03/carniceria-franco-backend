-- Ejecutar UNA SOLA VEZ si inventory_movements ya fue creada con la versión anterior.
ALTER TABLE `inventory_movements`
  ADD COLUMN `reception_id` CHAR(36) NULL AFTER `user_id`,
  ADD COLUMN `unit_cost` DECIMAL(14,2) UNSIGNED NULL AFTER `unit`,
  ADD COLUMN `total_cost` DECIMAL(16,2) UNSIGNED NULL AFTER `unit_cost`,
  ADD KEY `inventory_movements_reception_idx` (`reception_id`);

-- Conserva un costo estimado para entradas anteriores usando el precio actual.
UPDATE `inventory_movements` im
INNER JOIN `products` p ON p.`id` = im.`product_id`
SET im.`unit_cost` = p.`precio`,
    im.`total_cost` = ROUND(p.`precio` * im.`quantity`, 2)
WHERE im.`type` = 'entrada'
  AND im.`origin` = 'compra'
  AND im.`unit_cost` IS NULL;
