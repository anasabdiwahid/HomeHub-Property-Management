-- HomeHub Property Management System Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.4+ / PHP 8+

CREATE DATABASE IF NOT EXISTS `homehub_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `homehub_db`;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
  `avatar` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: houses
CREATE TABLE IF NOT EXISTS `houses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `manager_id` INT NULL,
  `house_name` VARCHAR(150) NOT NULL,
  `house_code` VARCHAR(50) NOT NULL UNIQUE,
  `address` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `rent_price` DECIMAL(10,2) NOT NULL,
  `total_apartments` INT NOT NULL DEFAULT 1,
  `occupied_apartments` INT NOT NULL DEFAULT 0,
  `vacant_apartments` INT NOT NULL DEFAULT 1,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rental_requests
CREATE TABLE IF NOT EXISTS `rental_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `house_id` INT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `request_note` TEXT NULL,
  `admin_notes` TEXT NULL,
  `move_in_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`house_id`) REFERENCES `houses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `house_id` INT NOT NULL,
  `user_id` INT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'EVC Plus',
  `reference_no` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`house_id`) REFERENCES `houses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: reports (logs of generated reports)
CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_type` VARCHAR(100) NOT NULL,
  `generated_by` INT NULL,
  `parameters` TEXT NULL,
  `report_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('system_name', 'HomeHub Property Management'),
('currency', '$'),
('company_email', 'contact@homehub.so'),
('company_phone', '+252 61 555 4321'),
('company_address', 'Maka Al Mukarama Road, Hodan, Mogadishu, Somalia'),
('app_version', '1.0.0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Initial Users (Admin Only: ayman@gmail.com / ayman0000)
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `status`) VALUES
(1, 'Ayman (Admin)', 'ayman@gmail.com', '+252 61 500 0001', '$2y$10$ZTwEBzEnHRKCLi5QJSEUxuNYYn2HnutRnljvzkthhDENAg.DbOyLW', 'admin', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Initial Categories
INSERT INTO `categories` (`id`, `category_name`, `description`) VALUES
(1, 'Residential Apartment', 'Modern multi-family apartments with 24/7 security, backup generator and water supply.'),
(2, 'Luxury Villa', 'Spacious standalone villas with private compound, parking and premium modern finishing.'),
(3, 'Commercial Office', 'Prime commercial office spaces along major business roads for enterprises and agencies.'),
(4, 'Townhouse', 'Multi-level comfortable townhouses for families in peaceful residential zones.'),
(5, 'Studio Apartment', 'Cozy, affordable single-room self-contained units ideal for students and professionals.')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

