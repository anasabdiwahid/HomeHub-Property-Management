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

-- Initial Users (Password: admin123, manager123, user123)
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `status`) VALUES
(1, 'Admin Abdi', 'admin@homehub.so', '+252 61 500 0001', '$2y$10$fUhiFnOt3nUt4QAwrPCp5ewnZ1rRyMZcEOVJBpxJzBs6/U5o7ZqD.', 'admin', 'active'),
(2, 'Ahmed Nur (Manager)', 'manager@homehub.so', '+252 61 500 0002', '$2y$10$VL.jaQcCI8AgAKEI7FxBbebe7n6BMscJLHQlWbUhPkV8ndvKiAhWa', 'manager', 'active'),
(3, 'Farah Ali (Manager)', 'farah@homehub.so', '+252 61 500 0003', '$2y$10$VL.jaQcCI8AgAKEI7FxBbebe7n6BMscJLHQlWbUhPkV8ndvKiAhWa', 'manager', 'active'),
(4, 'Jama Hassan (Tenant)', 'user@homehub.so', '+252 61 500 0004', '$2y$10$0VbOge8PX.J9ERZO10MM9OssMgzH9MB5ejJs.ncfLeb6daSkNkTcy', 'user', 'active'),
(5, 'Hodan Dahir (Tenant)', 'hodan@homehub.so', '+252 61 500 0005', '$2y$10$0VbOge8PX.J9ERZO10MM9OssMgzH9MB5ejJs.ncfLeb6daSkNkTcy', 'user', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Initial Categories
INSERT INTO `categories` (`id`, `category_name`, `description`) VALUES
(1, 'Residential Apartment', 'Modern multi-family apartments with 24/7 security, backup generator and water supply.'),
(2, 'Luxury Villa', 'Spacious standalone villas with private compound, parking and premium modern finishing.'),
(3, 'Commercial Office', 'Prime commercial office spaces along major business roads for enterprises and agencies.'),
(4, 'Townhouse', 'Multi-level comfortable townhouses for families in peaceful residential zones.'),
(5, 'Studio Apartment', 'Cozy, affordable single-room self-contained units ideal for students and professionals.')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- Initial Houses
INSERT INTO `houses` (`id`, `category_id`, `manager_id`, `house_name`, `house_code`, `address`, `city`, `rent_price`, `total_apartments`, `occupied_apartments`, `vacant_apartments`, `description`, `image`) VALUES
(1, 1, 2, 'Wadajir Heights Tower', 'HH-MG-001', 'Airport Road, Wadajir District', 'Wadajir', 750.00, 12, 9, 3, 'Prime 12-unit residential tower close to Aden Adde International Airport. Features elevator, solar backup electricity, 24/7 guarded security, and reliable clean water supply.', 'wadajir_heights.jpg'),
(2, 2, 2, 'Darusalaam Royal Villa', 'HH-MG-002', 'Street 14, Darusalaam City', 'Darusalaam', 1400.00, 1, 1, 0, 'Luxurious 5-bedroom villa with private compound, manicured garden, modern kitchen, rooftop terrace, and garage parking for 3 cars in the peaceful Darusalaam suburb.', 'darusalaam_villa.jpg'),
(3, 1, 2, 'Hodan Modern Suites', 'HH-MG-003', 'Maka Al Mukarama, Near KM4', 'Hodan', 600.00, 8, 6, 2, 'High-demand apartments in the heart of Hodan. Walking distance to supermarkets, restaurants, and medical centers. High-speed fiber internet and perimeter security.', 'hodan_suites.jpg'),
(4, 3, 3, 'Waaberi Commercial Plaza', 'HH-MG-004', '21 October Road, Waaberi District', 'Waaberi', 1200.00, 6, 4, 2, 'Premium multi-office commercial plaza with conference room facilities, reliable fiber internet, backup generator, and dedicated client parking bays in Waaberi.', 'waaberi_plaza.jpg'),
(5, 2, 3, 'Liido Ocean View Residence', 'HH-MG-005', 'Liido Beachfront Road, Cabdicasiis District', 'Cabdicasiis', 900.00, 4, 2, 2, 'Breathtaking ocean-front multi-unit property with sea breeze, spacious balconies, master en-suites, and peaceful environment near Liido Beach.', 'liido_ocean.jpg'),
(6, 5, 3, 'KM4 Modern Studios', 'HH-MG-006', 'Maka Al Mukarama, Near KM4 Roundabout', 'Hodan', 350.00, 10, 8, 2, 'Fully serviced modern studio apartments tailored for professionals and university scholars in Hodan. Includes kitchen cabinet, prepaid electric sub-meter, and high-speed Wi-Fi.', 'km4_studios.jpg')
ON DUPLICATE KEY UPDATE `house_name` = VALUES(`house_name`);

-- Initial Rental Requests
INSERT INTO `rental_requests` (`id`, `user_id`, `house_id`, `status`, `request_note`, `admin_notes`, `move_in_date`, `created_at`) VALUES
(1, 4, 1, 'approved', 'Requesting 2-bedroom unit on 3rd floor. Moving in with family.', 'Tenant verified and contract signed. Deposit cleared.', '2026-10-15', '2026-09-20 10:15:00'),
(2, 5, 3, 'approved', 'Looking for quiet apartment near KM4 for remote consultancy work.', 'ID verified, reference checked. Approved by Ahmed Nur.', '2026-10-01', '2026-09-25 14:30:00'),
(3, 4, 6, 'pending', 'Inquiring for a studio unit near KM4 Hodan for upcoming business assignment.', 'Pending manager review of availability dates.', '2026-11-01', '2026-10-02 08:45:00'),
(4, 5, 2, 'rejected', 'Inquiry for short-term 2-week lease for royal villa in Darusalaam.', 'Declined: minimum lease requirement for royal villa is 6 months.', '2026-10-10', '2026-09-28 16:20:00')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Initial Payments
INSERT INTO `payments` (`id`, `house_id`, `user_id`, `amount`, `payment_date`, `payment_method`, `reference_no`, `notes`) VALUES
(1, 1, 4, 750.00, '2026-09-22', 'EVC Plus', 'EVC-982314-MG', 'October 2026 monthly rent payment for Apt 302'),
(2, 3, 5, 600.00, '2026-09-27', 'EVC Plus', 'EVC-771209-MG', 'October 2026 monthly rent payment for Apt 104'),
(3, 4, NULL, 1200.00, '2026-09-15', 'Bank Transfer', 'DAHAB-88124', 'September 2026 commercial office rent Suite B Waaberi'),
(4, 1, NULL, 750.00, '2026-09-05', 'Sahal', 'SAH-33918-MG', 'September 2026 rent collection Unit 201 Wadajir'),
(5, 5, NULL, 900.00, '2026-09-18', 'EVC Plus', 'EVC-445102-MG', 'September 2026 rent collection Liido Ocean View Unit 2')
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);
