CREATE TABLE `indicador_preguntas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pregunta` TEXT NOT NULL,
  `descripcion` TEXT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `mostrar_al_finalizar_pedido` TINYINT(1) NOT NULL DEFAULT 1,
  `orden` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_indicador_preguntas_estado` (`activo`, `mostrar_al_finalizar_pedido`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `indicador_respuestas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pregunta_id` BIGINT UNSIGNED NOT NULL,
  `sale_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `respuesta` TINYINT UNSIGNED NOT NULL,
  `comentario` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indicador_respuesta_pedido_cliente` (`pregunta_id`, `sale_id`, `customer_id`),
  KEY `idx_indicador_respuestas_pedido_cliente` (`sale_id`, `customer_id`),
  CONSTRAINT `fk_indicador_respuestas_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `indicador_preguntas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicador_respuestas_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicador_respuestas_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_indicador_respuesta_escala` CHECK (`respuesta` BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `indicador_preguntas`
  (`pregunta`, `descripcion`, `activo`, `mostrar_al_finalizar_pedido`, `orden`, `created_at`, `updated_at`)
VALUES
  ('Que tan satisfecho estas con la rapidez del proceso al realizar tu pedido?', 'Rapidez del proceso de compra', 1, 1, 1, NOW(), NOW()),
  ('Los cortes de carne, el peso y la preparacion recibida coincidieron exactamente con lo que solicitaste.', 'Precision del pedido recibido', 1, 1, 2, NOW(), NOW()),
  ('Las sugerencias y recomendaciones de cortes mostradas en la plataforma fueron acertadas y facilitaron tu compra.', 'Calidad de recomendaciones', 1, 1, 3, NOW(), NOW()),
  ('La informacion sobre el estado de tu pedido (preparacion y entrega) fue clara y oportuna.', 'Claridad del seguimiento', 1, 1, 4, NOW(), NOW()),
  ('En general, como evaluas tu experiencia con este proceso de pedidos frente a la atencion tradicional?', 'Experiencia general contra atencion tradicional', 1, 1, 5, NOW(), NOW());
