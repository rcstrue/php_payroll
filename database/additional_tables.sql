-- =====================================================
-- RCS HRMS Pro - Additional Tables for New Features
-- Run this after the main hrms_database.sql
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- INVOICES TABLE (for GST Billing)
-- =====================================================

CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50) NOT NULL,
    `client_id` INT(11) NOT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE DEFAULT NULL,
    `month` INT(2) DEFAULT NULL,
    `year` INT(4) DEFAULT NULL,
    `service_type` VARCHAR(100) DEFAULT 'Manpower Supply',
    `sac_code` VARCHAR(20) DEFAULT '998511',
    `place_of_supply` VARCHAR(100) DEFAULT NULL,
    `billing_type` ENUM('manpower', 'manual') DEFAULT 'manpower',
    `subtotal` DECIMAL(15,2) DEFAULT 0,
    `cgst_rate` DECIMAL(5,2) DEFAULT 9.00,
    `cgst_amount` DECIMAL(15,2) DEFAULT 0,
    `sgst_rate` DECIMAL(5,2) DEFAULT 9.00,
    `sgst_amount` DECIMAL(15,2) DEFAULT 0,
    `igst_rate` DECIMAL(5,2) DEFAULT 0,
    `igst_amount` DECIMAL(15,2) DEFAULT 0,
    `total_amount` DECIMAL(15,2) DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('draft', 'sent', 'paid', 'cancelled') DEFAULT 'draft',
    `payment_date` DATE DEFAULT NULL,
    `payment_mode` VARCHAR(50) DEFAULT NULL,
    `payment_reference` VARCHAR(100) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoice_number` (`invoice_number`),
    KEY `client_id` (`client_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice Items
CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(11) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `quantity` INT(11) DEFAULT 1,
    `rate` DECIMAL(15,2) DEFAULT 0,
    `amount` DECIMAL(15,2) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SETTLEMENT TABLES (F&F)
-- =====================================================

CREATE TABLE IF NOT EXISTS `employee_settlements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(36) NOT NULL,
    `last_working_day` DATE NOT NULL,
    `leaving_reason` VARCHAR(100) DEFAULT NULL,
    `service_years` DECIMAL(5,2) DEFAULT 0,
    `salary_days` INT(3) DEFAULT 0,
    `salary_amount` DECIMAL(15,2) DEFAULT 0,
    `leave_encashment_days` DECIMAL(5,2) DEFAULT 0,
    `leave_encashment_amount` DECIMAL(15,2) DEFAULT 0,
    `gratuity_years` INT(2) DEFAULT 0,
    `gratuity_amount` DECIMAL(15,2) DEFAULT 0,
    `bonus_amount` DECIMAL(15,2) DEFAULT 0,
    `notice_shortfall` INT(3) DEFAULT 0,
    `notice_recovery` DECIMAL(15,2) DEFAULT 0,
    `advance_recovery` DECIMAL(15,2) DEFAULT 0,
    `other_deductions` DECIMAL(15,2) DEFAULT 0,
    `total_earnings` DECIMAL(15,2) DEFAULT 0,
    `total_deductions` DECIMAL(15,2) DEFAULT 0,
    `net_payable` DECIMAL(15,2) DEFAULT 0,
    `status` ENUM('pending', 'approved', 'paid', 'on_hold') DEFAULT 'pending',
    `payment_date` DATE DEFAULT NULL,
    `payment_mode` VARCHAR(50) DEFAULT NULL,
    `payment_reference` VARCHAR(100) DEFAULT NULL,
    `approved_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SALARY REVISION TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS `salary_revisions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(36) NOT NULL,
    `old_basic` DECIMAL(15,2) DEFAULT 0,
    `new_basic` DECIMAL(15,2) DEFAULT 0,
    `old_gross` DECIMAL(15,2) DEFAULT 0,
    `new_gross` DECIMAL(15,2) DEFAULT 0,
    `revision_type` ENUM('percentage', 'fixed', 'daily_rate', 'monthly_rate', 'location_change') DEFAULT 'percentage',
    `percentage` DECIMAL(5,2) DEFAULT NULL,
    `daily_rate` DECIMAL(10,2) DEFAULT NULL,
    `effective_from` DATE NOT NULL,
    `revision_month` INT(2) DEFAULT NULL,
    `revision_year` INT(4) DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `revision_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATION LOGS
-- =====================================================

CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `type` ENUM('sms', 'email', 'whatsapp') NOT NULL,
    `recipient` VARCHAR(100) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `status` ENUM('sent', 'failed', 'pending', 'link_generated') DEFAULT 'pending',
    `response` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `type` (`type`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CLIENT RATE CARDS (for billing)
-- =====================================================

CREATE TABLE IF NOT EXISTS `client_rate_cards` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `client_id` INT(11) NOT NULL,
    `worker_category` VARCHAR(50) DEFAULT NULL,
    `bill_type` ENUM('monthly', 'daily') DEFAULT 'monthly',
    `bill_rate` DECIMAL(15,2) NOT NULL,
    `service_charge_percent` DECIMAL(5,2) DEFAULT 0,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EMPLOYEE SALARY STRUCTURES (Extended)
-- =====================================================

CREATE TABLE IF NOT EXISTS `employee_salary_structures` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(36) NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE DEFAULT NULL,
    `basic_wage` DECIMAL(15,2) DEFAULT 0,
    `da` DECIMAL(15,2) DEFAULT 0,
    `hra` DECIMAL(15,2) DEFAULT 0,
    `conveyance` DECIMAL(15,2) DEFAULT 0,
    `medical_allowance` DECIMAL(15,2) DEFAULT 0,
    `special_allowance` DECIMAL(15,2) DEFAULT 0,
    `other_allowance` DECIMAL(15,2) DEFAULT 0,
    `gross_salary` DECIMAL(15,2) DEFAULT 0,
    `daily_rate` DECIMAL(10,2) DEFAULT NULL,
    `monthly_rate` DECIMAL(15,2) DEFAULT NULL,
    `pf_applicable` TINYINT(1) DEFAULT 1,
    `esi_applicable` TINYINT(1) DEFAULT 1,
    `pt_applicable` TINYINT(1) DEFAULT 1,
    `lwf_applicable` TINYINT(1) DEFAULT 1,
    `bonus_applicable` TINYINT(1) DEFAULT 1,
    `gratuity_applicable` TINYINT(1) DEFAULT 1,
    `overtime_applicable` TINYINT(1) DEFAULT 0,
    `location_id` INT(11) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EMPLOYEE LOCATIONS (for multi-location tracking)
-- =====================================================

CREATE TABLE IF NOT EXISTS `employee_locations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(36) NOT NULL,
    `unit_id` INT(11) NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE DEFAULT NULL,
    `is_current` TINYINT(1) DEFAULT 1,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SETTINGS FOR NOTIFICATIONS
-- =====================================================

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('notif_sms_api_key', '', 'string', 'Fast2SMS API Key for SMS notifications'),
('notif_sms_provider', 'fast2sms', 'string', 'SMS Provider (fast2sms, textlocal, msg91)'),
('notif_email_host', 'smtp.gmail.com', 'string', 'SMTP Host for email'),
('notif_email_user', '', 'string', 'Email username'),
('notif_email_pass', '', 'string', 'Email password/app password'),
('notif_email_from', 'noreply@rcshrms.com', 'string', 'From email address'),
('notif_whatsapp_api_url', '', 'string', 'WhatsApp Business API URL (optional)'),
('notif_whatsapp_api_token', '', 'string', 'WhatsApp API token (optional)');

-- =====================================================
-- NOTIFICATIONS TABLE (for in-app notifications)
-- =====================================================

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT 'info',
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STATES TABLE (for minimum wages)
-- =====================================================

CREATE TABLE IF NOT EXISTS `states` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `state_name` VARCHAR(100) NOT NULL,
    `state_code` VARCHAR(10) DEFAULT NULL,
    `pt_applicable` TINYINT(1) DEFAULT 1,
    `lwf_applicable` TINYINT(1) DEFAULT 1,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `state_name` (`state_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert states
INSERT IGNORE INTO `states` (`state_name`, `state_code`) VALUES
('Gujarat', 'GJ'),
('Maharashtra', 'MH'),
('Rajasthan', 'RJ'),
('Madhya Pradesh', 'MP'),
('Chhattisgarh', 'CG'),
('Delhi', 'DL'),
('Karnataka', 'KA'),
('Tamil Nadu', 'TN'),
('Telangana', 'TS'),
('Uttar Pradesh', 'UP'),
('West Bengal', 'WB'),
('Kerala', 'KL'),
('Haryana', 'HR'),
('Punjab', 'PB'),
('Bihar', 'BR'),
('Jharkhand', 'JH'),
('Odisha', 'OD'),
('Andhra Pradesh', 'AP');

-- =====================================================
-- ZONES TABLE (for minimum wage zones)
-- =====================================================

CREATE TABLE IF NOT EXISTS `zones` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `state_id` INT(11) NOT NULL,
    `zone_name` VARCHAR(100) NOT NULL,
    `zone_code` VARCHAR(20) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `state_id` (`state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Gujarat zones
INSERT IGNORE INTO `zones` (`state_id`, `zone_name`, `zone_code`) 
SELECT id, 'Zone I (Major Cities)', 'Z1' FROM states WHERE state_name = 'Gujarat';
INSERT IGNORE INTO `zones` (`state_id`, `zone_name`, `zone_code`) 
SELECT id, 'Zone II (Other Areas)', 'Z2' FROM states WHERE state_name = 'Gujarat';

SET FOREIGN_KEY_CHECKS = 1;
