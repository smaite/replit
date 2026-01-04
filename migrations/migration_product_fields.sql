-- Add new tables for enhanced product management

-- Brands table
CREATE TABLE IF NOT EXISTS `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shipping Profiles table
CREATE TABLE IF NOT EXISTS `shipping_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_per_kg` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Return Policies table
CREATE TABLE IF NOT EXISTS `return_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) DEFAULT NULL, -- NULL for system defaults
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `days` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Variants table
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)), -- JSON { "Color": "Red", "Size": "XL" }
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add new columns to products table
ALTER TABLE `products`
ADD COLUMN `condition` enum('new','used') DEFAULT 'new' AFTER `description`,
ADD COLUMN `brand_id` int(11) DEFAULT NULL AFTER `category_id`,
ADD COLUMN `shipping_weight` decimal(8,2) DEFAULT 0.00 AFTER `stock`,
ADD COLUMN `shipping_profile_id` int(11) DEFAULT NULL AFTER `shipping_weight`,
ADD COLUMN `return_policy_id` int(11) DEFAULT NULL AFTER `shipping_profile_id`,
ADD COLUMN `video_url` varchar(500) DEFAULT NULL AFTER `tags`,
ADD COLUMN `bullet_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bullet_points`)),
ADD COLUMN `dimensions_length` decimal(8,2) DEFAULT 0.00,
ADD COLUMN `dimensions_width` decimal(8,2) DEFAULT 0.00,
ADD COLUMN `dimensions_height` decimal(8,2) DEFAULT 0.00,
ADD COLUMN `handling_days` int(11) DEFAULT 1,
ADD COLUMN `free_shipping` tinyint(1) DEFAULT 0,
ADD COLUMN `flash_sale_eligible` tinyint(1) DEFAULT 0;

-- Add indexes
ALTER TABLE `products`
ADD KEY `brand_id` (`brand_id`),
ADD KEY `shipping_profile_id` (`shipping_profile_id`),
ADD KEY `return_policy_id` (`return_policy_id`);

-- Insert default return policies
INSERT INTO `return_policies` (`name`, `description`, `days`) VALUES
('No Returns', 'Returns are not accepted for this item.', 0),
('7 Days Return', 'Returns accepted within 7 days of delivery.', 7),
('14 Days Return', 'Returns accepted within 14 days of delivery.', 14),
('30 Days Return', 'Returns accepted within 30 days of delivery.', 30);

-- Insert some default brands
INSERT INTO `brands` (`name`, `status`) VALUES
('Samsung', 'active'),
('Apple', 'active'),
('Nike', 'active'),
('Adidas', 'active'),
('Sony', 'active'),
('Generic', 'active');
