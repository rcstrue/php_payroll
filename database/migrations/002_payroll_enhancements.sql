-- =====================================================
-- RCS HRMS Pro - Payroll Enhancements Migration
-- Version: 2.2.0
-- Date: 2024
-- Description: Adds advanced payroll features
-- =====================================================

-- =====================================================
-- 1. Update payroll_periods table - Add Frozen status support
-- =====================================================
-- NOTE: Status enum already includes 'Locked' which serves as Frozen
-- Adding additional columns for better tracking

ALTER TABLE `payroll_periods` 
ADD COLUMN `frozen_at` DATETIME NULL DEFAULT NULL AFTER `payment_date`,
ADD COLUMN `frozen_by` INT(11) NULL DEFAULT NULL AFTER `frozen_at`,
ADD COLUMN `hold_count` INT(11) NOT NULL DEFAULT 0 AFTER `frozen_by`,
ADD COLUMN `exception_count` INT(11) NOT NULL DEFAULT 0 AFTER `hold_count`;

-- =====================================================
-- 2. Update payroll table - Add new columns
-- =====================================================
ALTER TABLE `payroll` 
ADD COLUMN `salary_hold` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
ADD COLUMN `hold_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `salary_hold`,
ADD COLUMN `hold_date` DATE NULL DEFAULT NULL AFTER `hold_reason`,
ADD COLUMN `released_date` DATE NULL DEFAULT NULL AFTER `hold_date`,
ADD COLUMN `payroll_dirty` TINYINT(1) NOT NULL DEFAULT 0 AFTER `released_date`,
ADD COLUMN `dirty_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `payroll_dirty`,
ADD COLUMN `exception_type` VARCHAR(100) NULL DEFAULT NULL AFTER `dirty_reason`,
ADD COLUMN `last_calculated_at` DATETIME NULL DEFAULT NULL AFTER `exception_type`,
ADD COLUMN `calculated_by` INT(11) NULL DEFAULT NULL AFTER `last_calculated_at`;

-- =====================================================
-- 3. Modify payroll status enum to include Frozen
-- =====================================================
-- Note: For existing data, we modify the enum to add 'Frozen'
ALTER TABLE `payroll` 
MODIFY COLUMN `status` ENUM('Draft', 'Processed', 'Approved', 'Paid', 'Hold', 'Frozen', 'Cancelled') 
NOT NULL DEFAULT 'Draft';

ALTER TABLE `payroll_periods` 
MODIFY COLUMN `status` ENUM('Draft', 'Processing', 'Processed', 'Approved', 'Paid', 'Locked', 'Frozen') 
NOT NULL DEFAULT 'Draft';

-- =====================================================
-- 4. Add indexes for performance optimization
-- =====================================================
-- Payroll table indexes
CREATE INDEX `idx_payroll_period_emp` ON `payroll` (`payroll_period_id`, `employee_id`);
CREATE INDEX `idx_payroll_status` ON `payroll` (`status`);
CREATE INDEX `idx_payroll_hold` ON `payroll` (`salary_hold`);
CREATE INDEX `idx_payroll_dirty` ON `payroll` (`payroll_dirty`);
CREATE INDEX `idx_payroll_unit` ON `payroll` (`unit_id`);
CREATE INDEX `idx_payroll_period_status` ON `payroll` (`payroll_period_id`, `status`);

-- Payroll periods indexes
CREATE INDEX `idx_period_month_year` ON `payroll_periods` (`month`, `year`);
CREATE INDEX `idx_period_status` ON `payroll_periods` (`status`);

-- Employees table indexes for payroll processing
CREATE INDEX `idx_employee_client` ON `employees` (`client_id`);
CREATE INDEX `idx_employee_unit` ON `employees` (`unit_id`);
CREATE INDEX `idx_employee_status` ON `employees` (`status`);

-- Salary structures indexes
CREATE INDEX `idx_salary_emp_effective` ON `employee_salary_structures` (`employee_id`, `effective_from`, `effective_to`);

-- Employee advances indexes
CREATE INDEX `idx_advance_emp_month_year` ON `employee_advances` (`employee_id`, `month`, `year`);

-- =====================================================
-- 5. Create payroll_exceptions table for tracking issues
-- =====================================================
CREATE TABLE IF NOT EXISTS `payroll_exceptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `payroll_period_id` INT(11) NOT NULL,
    `employee_id` VARCHAR(36) NOT NULL,
    `exception_type` ENUM('Missing Attendance', 'Missing Bank Details', 'Undefined Salary', 'Invalid Data', 'Other') NOT NULL,
    `exception_message` TEXT NULL,
    `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
    `resolved_at` DATETIME NULL DEFAULT NULL,
    `resolved_by` INT(11) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_exception_period` (`payroll_period_id`),
    INDEX `idx_exception_type` (`exception_type`),
    INDEX `idx_exception_resolved` (`is_resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. Create payroll_history table for audit trail
-- =====================================================
CREATE TABLE IF NOT EXISTS `payroll_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `payroll_id` INT(11) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `changed_by` INT(11) NULL DEFAULT NULL,
    `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_history_payroll` (`payroll_id`),
    INDEX `idx_history_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. Add settings for payroll configuration
-- =====================================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('payroll_auto_hold_exceptions', '1', 'payroll', 'Automatically hold payroll for employees with exceptions'),
('payroll_require_approval', '1', 'payroll', 'Require approval before marking payroll as paid'),
('payroll_allow_negative_net', '0', 'payroll', 'Allow negative net pay after deductions'),
('payroll_default_payment_mode', 'Bank Transfer', 'payroll', 'Default payment mode for new payroll')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- =====================================================
-- NOTES FOR DEVELOPERS
-- =====================================================
-- 
-- IMPORTANT: The following columns are used for specific purposes:
-- 
-- 1. salary_hold: When set to 1, the employee's salary will be held
--    and not included in bank transfers or payments
-- 
-- 2. payroll_dirty: When set to 1, indicates the payroll needs 
--    recalculation due to changes in attendance, salary, or advances
-- 
-- 3. exception_type: Stores the type of exception if any
--    (e.g., 'Missing Attendance', 'Missing Bank Details')
-- 
-- 4. status 'Frozen': A payroll period marked as Frozen cannot be
--    modified - no edits, recalculations, or deletions allowed
-- 
-- 5. Status Flow:
--    Draft -> Processed -> Approved -> Paid/Frozen
--    At any stage: Hold can be applied to individual employees
-- 
-- DO NOT:
-- - Modify the status enum values without updating the Payroll class
-- - Delete payroll records directly - use the deletePayroll() method
-- - Skip the dirty flag check when recalculating payroll
-- 
-- =====================================================
