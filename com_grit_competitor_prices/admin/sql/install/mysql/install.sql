CREATE TABLE IF NOT EXISTS `#__competitor_prices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `competitor_name` varchar(255) NOT NULL,
  `competitor_url` varchar(1024) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
