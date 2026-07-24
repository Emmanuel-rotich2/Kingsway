CREATE TABLE IF NOT EXISTS `rate_limit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `request_time` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate_limit_ip_time` (`ip_address`, `request_time`),
  KEY `idx_rate_limit_time` (`request_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
