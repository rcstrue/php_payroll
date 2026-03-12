-- =====================================================
-- RCS HRMS Pro - Complete Database Schema
-- Company: RCS TRUE FACILITIES PVT LTD
-- Compatible with: MariaDB 10.3.39
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- COMPANY & SETTINGS TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS `companies` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `company_name` VARCHAR(255) NOT NULL,
    `legal_name` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `pincode` VARCHAR(20) DEFAULT NULL,
    `gst_number` VARCHAR(50) DEFAULT NULL,
    `pan_number` VARCHAR(50) DEFAULT NULL,
    `cin_number` VARCHAR(50) DEFAULT NULL,
    `pf_establishment_id` VARCHAR(50) DEFAULT NULL,
    `esi_establishment_id` VARCHAR(50) DEFAULT NULL,
    `pt_registration_number` VARCHAR(50) DEFAULT NULL,
    `lwlf_registration_number` VARCHAR(50) DEFAULT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `contact_email` VARCHAR(255) DEFAULT NULL,
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `logo_path` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `companies` (`company_name`, `legal_name`, `address`, `city`, `state`, `pincode`, `gst_number`, `pan_number`) 
VALUES ('RCS TRUE FACILITIES PVT LTD', 'RCS TRUE FACILITIES PRIVATE LIMITED', '110, Someswar Square, Vesu', 'Surat', 'Gujarat', '395007', '24AAICR1390M1Z3', 'AAICR1390M');

-- System Settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('pf_rate', '12', 'number', 'PF Contribution Rate (%)'),
('pf_admin_charges', '0.5', 'number', 'PF Admin Charges (%)'),
('edli_rate', '0.5', 'number', 'EDLI Rate (%)'),
('esi_rate_employee', '0.75', 'number', 'ESI Employee Contribution (%)'),
('esi_rate_employer', '3.25', 'number', 'ESI Employer Contribution (%)'),
('esi_wage_ceiling', '21000', 'number', 'ESI Wage Ceiling (₹)'),
('pf_wage_ceiling', '15000', 'number', 'PF Wage Ceiling (₹)'),
('bonus_minimum', '8.33', 'number', 'Minimum Bonus (%)'),
('bonus_maximum', '20', 'number', 'Maximum Bonus (%)'),
('bonus_wage_ceiling', '7000', 'number', 'Bonus Wage Ceiling (₹)'),
('gratuity_years', '5', 'number', 'Minimum Years for Gratuity'),
('overtime_rate_single', '1', 'number', 'OT Single Rate Multiplier'),
('overtime_rate_double', '2', 'number', 'OT Double Rate Multiplier'),
('working_days_month', '26', 'number', 'Standard Working Days per Month'),
('working_hours_day', '8', 'number', 'Standard Working Hours per Day'),
('payroll_cutoff_day', '25', 'number', 'Payroll Cutoff Day'),
('language_default', 'en', 'string', 'Default Language (en/hi)');

-- =====================================================
-- USERS & AUTHENTICATION
-- =====================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'hr', 'manager', 'supervisor', 'worker') NOT NULL DEFAULT 'worker',
    `employee_id` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `password_changed_at` DATETIME DEFAULT NULL,
    `failed_login_attempts` INT(11) DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Admin User (Password: admin@123 - change on first login)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `is_active`) VALUES
('admin', 'admin@rcsfacility.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- User Sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `session_token` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `session_token` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Log
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CLIENTS & CONTRACTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `client_code` VARCHAR(50) NOT NULL,
    `client_name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `contact_email` VARCHAR(255) DEFAULT NULL,
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `pincode` VARCHAR(20) DEFAULT NULL,
    `gst_number` VARCHAR(50) DEFAULT NULL,
    `pan_number` VARCHAR(50) DEFAULT NULL,
    `service_tax_number` VARCHAR(50) DEFAULT NULL,
    `billing_cycle` ENUM('monthly', 'fortnightly', 'weekly') DEFAULT 'monthly',
    `payment_terms` INT(11) DEFAULT 30,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `client_code` (`client_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Units/Work Locations
CREATE TABLE IF NOT EXISTS `units` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `client_id` INT(11) NOT NULL,
    `unit_code` VARCHAR(50) NOT NULL,
    `unit_name` VARCHAR(255) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(20) DEFAULT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `state_minimum_wage_zone` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unit_code` (`unit_code`),
    KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contracts/Work Orders
CREATE TABLE IF NOT EXISTS `contracts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `contract_number` VARCHAR(100) NOT NULL,
    `client_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `contract_title` VARCHAR(255) DEFAULT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE DEFAULT NULL,
    `contract_value` DECIMAL(15,2) DEFAULT NULL,
    `service_type` VARCHAR(255) DEFAULT NULL,
    `billing_cycle` ENUM('monthly', 'fortnightly', 'weekly') DEFAULT 'monthly',
    `manpower_count` INT(11) DEFAULT 0,
    `terms_and_conditions` TEXT DEFAULT NULL,
    `document_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'expired', 'terminated', 'pending') DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `contract_number` (`contract_number`),
    KEY `client_id` (`client_id`),
    KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EMPLOYEES/WORKERS
-- =====================================================

CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_code` VARCHAR(50) NOT NULL,
    `biometric_id` VARCHAR(50) DEFAULT NULL,
    `uan_number` VARCHAR(50) DEFAULT NULL,
    `esi_number` VARCHAR(50) DEFAULT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `father_name` VARCHAR(255) DEFAULT NULL,
    `mother_name` VARCHAR(255) DEFAULT NULL,
    `gender` ENUM('male', 'female', 'other') NOT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `marital_status` ENUM('single', 'married', 'divorced', 'widowed') DEFAULT 'single',
    `blood_group` VARCHAR(10) DEFAULT NULL,
    `personal_email` VARCHAR(255) DEFAULT NULL,
    `official_email` VARCHAR(255) DEFAULT NULL,
    `mobile_number` VARCHAR(20) DEFAULT NULL,
    `alternate_mobile` VARCHAR(20) DEFAULT NULL,
    `emergency_contact_name` VARCHAR(255) DEFAULT NULL,
    `emergency_contact_number` VARCHAR(20) DEFAULT NULL,
    `emergency_contact_relation` VARCHAR(100) DEFAULT NULL,
    
    -- Address Details
    `permanent_address` TEXT DEFAULT NULL,
    `permanent_city` VARCHAR(100) DEFAULT NULL,
    `permanent_state` VARCHAR(100) DEFAULT NULL,
    `permanent_pincode` VARCHAR(20) DEFAULT NULL,
    `permanent_country` VARCHAR(100) DEFAULT 'India',
    `current_address` TEXT DEFAULT NULL,
    `current_city` VARCHAR(100) DEFAULT NULL,
    `current_state` VARCHAR(100) DEFAULT NULL,
    `current_pincode` VARCHAR(20) DEFAULT NULL,
    `current_country` VARCHAR(100) DEFAULT 'India',
    `is_same_as_permanent` TINYINT(1) DEFAULT 1,
    
    -- Identity Documents
    `pan_number` VARCHAR(20) DEFAULT NULL,
    `aadhaar_number` VARCHAR(20) DEFAULT NULL,
    `aadhaar_enrollment_id` VARCHAR(50) DEFAULT NULL,
    `voter_id` VARCHAR(50) DEFAULT NULL,
    `driving_license` VARCHAR(50) DEFAULT NULL,
    `passport_number` VARCHAR(50) DEFAULT NULL,
    
    -- Bank Details
    `bank_name` VARCHAR(255) DEFAULT NULL,
    `bank_account_number` VARCHAR(50) DEFAULT NULL,
    `bank_ifsc_code` VARCHAR(20) DEFAULT NULL,
    `bank_branch` VARCHAR(255) DEFAULT NULL,
    `bank_account_type` ENUM('savings', 'current', 'salary') DEFAULT 'savings',
    `micr_code` VARCHAR(20) DEFAULT NULL,
    
    -- Employment Details
    `client_id` INT(11) DEFAULT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `contract_id` INT(11) DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `skill_category` ENUM('unskilled', 'semi-skilled', 'skilled', 'highly-skilled', 'supervisor', 'manager') DEFAULT 'unskilled',
    `worker_category` ENUM('worker', 'loader', 'packer', 'supervisor', 'manager', 'other') DEFAULT 'worker',
    `employment_type` ENUM('permanent', 'contract', 'temporary', 'casual', 'apprentice') DEFAULT 'contract',
    `date_of_joining` DATE NOT NULL,
    `date_of_leaving` DATE DEFAULT NULL,
    `probation_period` INT(11) DEFAULT 3,
    `notice_period` INT(11) DEFAULT 30,
    `experience_years` DECIMAL(5,2) DEFAULT 0,
    
    -- Wage Details
    `state_for_minimum_wage` VARCHAR(100) DEFAULT NULL,
    `minimum_wage_zone` VARCHAR(50) DEFAULT NULL,
    `basic_salary` DECIMAL(15,2) DEFAULT 0,
    `da` DECIMAL(15,2) DEFAULT 0,
    `hra` DECIMAL(15,2) DEFAULT 0,
    `conveyance` DECIMAL(15,2) DEFAULT 0,
    `medical_allowance` DECIMAL(15,2) DEFAULT 0,
    `special_allowance` DECIMAL(15,2) DEFAULT 0,
    `other_allowances` DECIMAL(15,2) DEFAULT 0,
    
    -- Statutory Settings
    `is_pf_applicable` TINYINT(1) DEFAULT 1,
    `is_pf_restricted` TINYINT(1) DEFAULT 0,
    `pf_joining_date` DATE DEFAULT NULL,
    `is_esi_applicable` TINYINT(1) DEFAULT 1,
    `esi_dispensary` VARCHAR(255) DEFAULT NULL,
    `is_pt_applicable` TINYINT(1) DEFAULT 1,
    `is_lwf_applicable` TINYINT(1) DEFAULT 1,
    `is_bonus_applicable` TINYINT(1) DEFAULT 1,
    `is_gratuity_applicable` TINYINT(1) DEFAULT 1,
    `is_overtime_applicable` TINYINT(1) DEFAULT 0,
    `is_npf_member` TINYINT(1) DEFAULT 0,
    `is_pension_member` TINYINT(1) DEFAULT 1,
    
    -- Family Details for PF/ESI
    `nominee_name_pf` VARCHAR(255) DEFAULT NULL,
    `nominee_relation_pf` VARCHAR(100) DEFAULT NULL,
    `nominee_dob_pf` DATE DEFAULT NULL,
    `nominee_address_pf` TEXT DEFAULT NULL,
    `nominee_name_esi` VARCHAR(255) DEFAULT NULL,
    `nominee_relation_esi` VARCHAR(100) DEFAULT NULL,
    `nominee_name_gratuity` VARCHAR(255) DEFAULT NULL,
    `nominee_relation_gratuity` VARCHAR(100) DEFAULT NULL,
    `nominee_dob_gratuity` DATE DEFAULT NULL,
    `nominee_address_gratuity` TEXT DEFAULT NULL,
    
    -- Family Members for ESI
    `spouse_name` VARCHAR(255) DEFAULT NULL,
    `spouse_dob` DATE DEFAULT NULL,
    `children_count` INT(2) DEFAULT 0,
    
    -- Status
    `status` ENUM('active', 'inactive', 'terminated', 'resigned', 'absconding', 'deceased') DEFAULT 'active',
    `leaving_reason` TEXT DEFAULT NULL,
    `police_verification_status` ENUM('pending', 'verified', 'failed') DEFAULT 'pending',
    `police_verification_date` DATE DEFAULT NULL,
    `police_verification_document` VARCHAR(255) DEFAULT NULL,
    
    -- Photo & Documents
    `photo_path` VARCHAR(255) DEFAULT NULL,
    `resume_path` VARCHAR(255) DEFAULT NULL,
    
    -- System Fields
    `source` ENUM('manual', 'api', 'import') DEFAULT 'manual',
    `api_reference_id` VARCHAR(100) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_code` (`employee_code`),
    UNIQUE KEY `uan_number` (`uan_number`),
    KEY `client_id` (`client_id`),
    KEY `unit_id` (`unit_id`),
    KEY `status` (`status`),
    KEY `skill_category` (`skill_category`),
    KEY `state_for_minimum_wage` (`state_for_minimum_wage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Documents
CREATE TABLE IF NOT EXISTS `employee_documents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `document_type` ENUM('aadhaar', 'pan', 'bank_proof', 'photo', 'address_proof', 'education', 'experience', 'police_verification', 'medical', 'other') NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `document_path` VARCHAR(255) NOT NULL,
    `document_number` VARCHAR(100) DEFAULT NULL,
    `issue_date` DATE DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `verification_status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    `verified_by` INT(11) DEFAULT NULL,
    `verified_at` DATETIME DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Family Members (for ESI)
CREATE TABLE IF NOT EXISTS `employee_family` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `member_name` VARCHAR(255) NOT NULL,
    `relationship` ENUM('spouse', 'son', 'daughter', 'father', 'mother', 'other') NOT NULL,
    `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `is_dependent` TINYINT(1) DEFAULT 1,
    `is_nominee` TINYINT(1) DEFAULT 0,
    `nominee_share` DECIMAL(5,2) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Service History
CREATE TABLE IF NOT EXISTS `employee_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `action_type` ENUM('joined', 'transferred', 'promoted', 'increment', 'resigned', 'terminated', 'rejoined') NOT NULL,
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `effective_date` DATE NOT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ATTENDANCE
-- =====================================================

CREATE TABLE IF NOT EXISTS `attendance` (
    `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `attendance_date` DATE NOT NULL,
    `shift_id` INT(11) DEFAULT NULL,
    `shift_start_time` TIME DEFAULT NULL,
    `shift_end_time` TIME DEFAULT NULL,
    `check_in_time` TIME DEFAULT NULL,
    `check_out_time` TIME DEFAULT NULL,
    `actual_hours` DECIMAL(4,2) DEFAULT 0,
    `worked_hours` DECIMAL(4,2) DEFAULT 0,
    `overtime_hours` DECIMAL(4,2) DEFAULT 0,
    `status` ENUM('present', 'absent', 'half_day', 'weekly_off', 'holiday', 'paid_leave', 'unpaid_leave', 'sick_leave', 'casual_leave', 'earned_leave') DEFAULT 'present',
    `leave_type_id` INT(11) DEFAULT NULL,
    `is_weekly_off` TINYINT(1) DEFAULT 0,
    `is_holiday` TINYINT(1) DEFAULT 0,
    `remarks` VARCHAR(255) DEFAULT NULL,
    `source` ENUM('manual', 'biometric', 'mobile', 'import') DEFAULT 'import',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_date` (`employee_id`, `attendance_date`),
    KEY `unit_id` (`unit_id`),
    KEY `attendance_date` (`attendance_date`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance Upload History
CREATE TABLE IF NOT EXISTS `attendance_uploads` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `upload_date` DATE NOT NULL,
    `month` INT(2) NOT NULL,
    `year` INT(4) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `client_id` INT(11) DEFAULT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) DEFAULT NULL,
    `total_records` INT(11) DEFAULT 0,
    `processed_records` INT(11) DEFAULT 0,
    `error_records` INT(11) DEFAULT 0,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `uploaded_by` INT(11) DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `error_log` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `month_year` (`month`, `year`),
    KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAYROLL
-- =====================================================

-- Payroll Periods
CREATE TABLE IF NOT EXISTS `payroll_periods` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `period_name` VARCHAR(100) NOT NULL,
    `month` INT(2) NOT NULL,
    `year` INT(4) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `cutoff_date` DATE DEFAULT NULL,
    `payment_date` DATE DEFAULT NULL,
    `status` ENUM('draft', 'processing', 'approved', 'paid', 'cancelled') DEFAULT 'draft',
    `total_employees` INT(11) DEFAULT 0,
    `total_gross` DECIMAL(18,2) DEFAULT 0,
    `total_deductions` DECIMAL(18,2) DEFAULT 0,
    `total_net` DECIMAL(18,2) DEFAULT 0,
    `approved_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `month_year` (`month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salary Structure
CREATE TABLE IF NOT EXISTS `salary_structures` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE DEFAULT NULL,
    `basic` DECIMAL(15,2) DEFAULT 0,
    `da` DECIMAL(15,2) DEFAULT 0,
    `hra` DECIMAL(15,2) DEFAULT 0,
    `conveyance` DECIMAL(15,2) DEFAULT 0,
    `medical_allowance` DECIMAL(15,2) DEFAULT 0,
    `special_allowance` DECIMAL(15,2) DEFAULT 0,
    `other_allowances` DECIMAL(15,2) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Details (Monthly Salary)
CREATE TABLE IF NOT EXISTS `payroll` (
    `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
    `payroll_period_id` INT(11) NOT NULL,
    `employee_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `client_id` INT(11) DEFAULT NULL,
    
    -- Attendance Summary
    `present_days` DECIMAL(4,2) DEFAULT 0,
    `absent_days` DECIMAL(4,2) DEFAULT 0,
    `weekly_offs` DECIMAL(4,2) DEFAULT 0,
    `holidays` DECIMAL(4,2) DEFAULT 0,
    `paid_leaves` DECIMAL(4,2) DEFAULT 0,
    `unpaid_leaves` DECIMAL(4,2) DEFAULT 0,
    `total_working_days` DECIMAL(4,2) DEFAULT 30,
    `paid_days` DECIMAL(4,2) DEFAULT 0,
    `overtime_hours` DECIMAL(6,2) DEFAULT 0,
    
    -- Earnings
    `basic` DECIMAL(15,2) DEFAULT 0,
    `da` DECIMAL(15,2) DEFAULT 0,
    `hra` DECIMAL(15,2) DEFAULT 0,
    `conveyance` DECIMAL(15,2) DEFAULT 0,
    `medical_allowance` DECIMAL(15,2) DEFAULT 0,
    `special_allowance` DECIMAL(15,2) DEFAULT 0,
    `other_allowances` DECIMAL(15,2) DEFAULT 0,
    `overtime_amount` DECIMAL(15,2) DEFAULT 0,
    `bonus` DECIMAL(15,2) DEFAULT 0,
    `incentive` DECIMAL(15,2) DEFAULT 0,
    `arrears` DECIMAL(15,2) DEFAULT 0,
    
    -- Deductions
    `pf_employee` DECIMAL(15,2) DEFAULT 0,
    `pf_employer` DECIMAL(15,2) DEFAULT 0,
    `pf_admin_charges` DECIMAL(15,2) DEFAULT 0,
    `edli_employee` DECIMAL(15,2) DEFAULT 0,
    `edli_employer` DECIMAL(15,2) DEFAULT 0,
    `esi_employee` DECIMAL(15,2) DEFAULT 0,
    `esi_employer` DECIMAL(15,2) DEFAULT 0,
    `pt_employee` DECIMAL(15,2) DEFAULT 0,
    `lwf_employee` DECIMAL(15,2) DEFAULT 0,
    `lwf_employer` DECIMAL(15,2) DEFAULT 0,
    `tds` DECIMAL(15,2) DEFAULT 0,
    `advance_deduction` DECIMAL(15,2) DEFAULT 0,
    `other_deductions` DECIMAL(15,2) DEFAULT 0,
    
    -- Net Amount
    `net_salary` DECIMAL(15,2) DEFAULT 0,
    `employer_contribution` DECIMAL(15,2) DEFAULT 0,
    `total_cost` DECIMAL(15,2) DEFAULT 0,
    
    -- Payment Status
    `payment_status` ENUM('pending', 'processing', 'paid', 'failed', 'on_hold') DEFAULT 'pending',
    `payment_mode` ENUM('bank_transfer', 'cash', 'cheque') DEFAULT 'bank_transfer',
    `payment_reference` VARCHAR(255) DEFAULT NULL,
    `payment_date` DATE DEFAULT NULL,
    `bank_reference` VARCHAR(255) DEFAULT NULL,
    
    -- Status
    `is_processed` TINYINT(1) DEFAULT 0,
    `processed_at` DATETIME DEFAULT NULL,
    `processed_by` INT(11) DEFAULT NULL,
    `is_approved` TINYINT(1) DEFAULT 0,
    `approved_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `period_employee` (`payroll_period_id`, `employee_id`),
    KEY `employee_id` (`employee_id`),
    KEY `unit_id` (`unit_id`),
    KEY `client_id` (`client_id`),
    KEY `payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MINIMUM WAGES (5 States)
-- =====================================================

CREATE TABLE IF NOT EXISTS `minimum_wages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `state` VARCHAR(100) NOT NULL,
    `zone` VARCHAR(50) DEFAULT NULL,
    `industry_type` VARCHAR(100) DEFAULT 'All',
    `skill_category` ENUM('unskilled', 'semi-skilled', 'skilled', 'highly-skilled', 'supervisor', 'clerical') NOT NULL,
    `worker_category` ENUM('worker', 'loader', 'packer', 'supervisor', 'manager', 'watch_security', 'sweeping_cleaning', 'other') DEFAULT 'worker',
    `basic_per_day` DECIMAL(10,2) DEFAULT 0,
    `da_per_day` DECIMAL(10,2) DEFAULT 0,
    `total_per_day` DECIMAL(10,2) DEFAULT 0,
    `basic_per_month` DECIMAL(10,2) DEFAULT 0,
    `da_per_month` DECIMAL(10,2) DEFAULT 0,
    `total_per_month` DECIMAL(10,2) DEFAULT 0,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE DEFAULT NULL,
    `notification_number` VARCHAR(100) DEFAULT NULL,
    `notification_date` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `state_zone` (`state`, `zone`),
    KEY `skill_category` (`skill_category`),
    KEY `effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Gujarat Minimum Wages (Current - Update as per notifications)
INSERT INTO `minimum_wages` (`state`, `zone`, `skill_category`, `worker_category`, `basic_per_day`, `da_per_day`, `total_per_day`, `basic_per_month`, `da_per_month`, `total_per_month`, `effective_from`, `notification_number`) VALUES
-- Gujarat - Zone I (Major Cities)
('Gujarat', 'Zone I', 'unskilled', 'worker', 402.00, 101.70, 503.70, 10452.00, 2644.20, 13096.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone I', 'semi-skilled', 'worker', 420.00, 101.70, 521.70, 10920.00, 2644.20, 13564.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone I', 'skilled', 'worker', 475.00, 101.70, 576.70, 12350.00, 2644.20, 14994.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone I', 'highly-skilled', 'worker', 523.00, 101.70, 624.70, 13598.00, 2644.20, 16242.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone I', 'supervisor', 'supervisor', 523.00, 101.70, 624.70, 13598.00, 2644.20, 16242.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone I', 'clerical', 'other', 475.00, 101.70, 576.70, 12350.00, 2644.20, 14994.20, '2024-04-01', 'GFD-2024/123'),
-- Gujarat - Zone II (Other Areas)
('Gujarat', 'Zone II', 'unskilled', 'worker', 378.00, 101.70, 479.70, 9828.00, 2644.20, 12472.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone II', 'semi-skilled', 'worker', 395.00, 101.70, 496.70, 10270.00, 2644.20, 12914.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone II', 'skilled', 'worker', 449.00, 101.70, 550.70, 11674.00, 2644.20, 14318.20, '2024-04-01', 'GFD-2024/123'),
('Gujarat', 'Zone II', 'highly-skilled', 'worker', 493.00, 101.70, 594.70, 12818.00, 2644.20, 15462.20, '2024-04-01', 'GFD-2024/123'),
-- Maharashtra
('Maharashtra', 'Zone I', 'unskilled', 'worker', 500.00, 75.00, 575.00, 13000.00, 1950.00, 14950.00, '2024-07-01', 'MFD-2024/456'),
('Maharashtra', 'Zone I', 'semi-skilled', 'worker', 553.00, 75.00, 628.00, 14378.00, 1950.00, 16328.00, '2024-07-01', 'MFD-2024/456'),
('Maharashtra', 'Zone I', 'skilled', 'worker', 627.00, 75.00, 702.00, 16302.00, 1950.00, 18252.00, '2024-07-01', 'MFD-2024/456'),
('Maharashtra', 'Zone II', 'unskilled', 'worker', 467.00, 60.00, 527.00, 12142.00, 1560.00, 13702.00, '2024-07-01', 'MFD-2024/456'),
('Maharashtra', 'Zone II', 'semi-skilled', 'worker', 518.00, 60.00, 578.00, 13468.00, 1560.00, 15028.00, '2024-07-01', 'MFD-2024/456'),
('Maharashtra', 'Zone II', 'skilled', 'worker', 590.00, 60.00, 650.00, 15340.00, 1560.00, 16900.00, '2024-07-01', 'MFD-2024/456'),
-- Rajasthan
('Rajasthan', 'Zone I', 'unskilled', 'worker', 403.00, 95.00, 498.00, 10478.00, 2470.00, 12948.00, '2024-01-01', 'RFD-2024/789'),
('Rajasthan', 'Zone I', 'semi-skilled', 'worker', 438.00, 95.00, 533.00, 11388.00, 2470.00, 13858.00, '2024-01-01', 'RFD-2024/789'),
('Rajasthan', 'Zone I', 'skilled', 'worker', 492.00, 95.00, 587.00, 12792.00, 2470.00, 15262.00, '2024-01-01', 'RFD-2024/789'),
-- Madhya Pradesh
('Madhya Pradesh', 'Zone I', 'unskilled', 'worker', 370.00, 85.00, 455.00, 9620.00, 2210.00, 11830.00, '2024-04-01', 'MPFD-2024/321'),
('Madhya Pradesh', 'Zone I', 'semi-skilled', 'worker', 396.00, 85.00, 481.00, 10296.00, 2210.00, 12506.00, '2024-04-01', 'MPFD-2024/321'),
('Madhya Pradesh', 'Zone I', 'skilled', 'worker', 437.00, 85.00, 522.00, 11362.00, 2210.00, 13572.00, '2024-04-01', 'MPFD-2024/321'),
-- Chhattisgarh
('Chhattisgarh', 'Zone I', 'unskilled', 'worker', 355.00, 78.00, 433.00, 9230.00, 2028.00, 11258.00, '2024-04-01', 'CGFD-2024/654'),
('Chhattisgarh', 'Zone I', 'semi-skilled', 'worker', 378.00, 78.00, 456.00, 9828.00, 2028.00, 11856.00, '2024-04-01', 'CGFD-2024/654'),
('Chhattisgarh', 'Zone I', 'skilled', 'worker', 415.00, 78.00, 493.00, 10790.00, 2028.00, 12818.00, '2024-04-01', 'CGFD-2024/654');

-- Minimum Wage Update Notifications
CREATE TABLE IF NOT EXISTS `minimum_wage_updates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `state` VARCHAR(100) NOT NULL,
    `notification_number` VARCHAR(100) NOT NULL,
    `notification_date` DATE NOT NULL,
    `effective_from` DATE NOT NULL,
    `description` TEXT DEFAULT NULL,
    `document_path` VARCHAR(255) DEFAULT NULL,
    `is_applied` TINYINT(1) DEFAULT 0,
    `applied_at` DATETIME DEFAULT NULL,
    `applied_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `state` (`state`),
    KEY `effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PROFESSIONAL TAX SLABS (State-wise)
-- =====================================================

CREATE TABLE IF NOT EXISTS `professional_tax_slabs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `state` VARCHAR(100) NOT NULL,
    `salary_from` DECIMAL(15,2) NOT NULL,
    `salary_to` DECIMAL(15,2) DEFAULT NULL,
    `pt_amount` DECIMAL(10,2) NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `state` (`state`),
    KEY `salary_range` (`salary_from`, `salary_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gujarat PT Slabs
INSERT INTO `professional_tax_slabs` (`state`, `salary_from`, `salary_to`, `pt_amount`, `effective_from`) VALUES
('Gujarat', 0, 5999, 0, '2024-04-01'),
('Gujarat', 6000, 8999, 80, '2024-04-01'),
('Gujarat', 9000, 11999, 150, '2024-04-01'),
('Gujarat', 12000, NULL, 200, '2024-04-01');

-- Maharashtra PT Slabs
INSERT INTO `professional_tax_slabs` (`state`, `salary_from`, `salary_to`, `pt_amount`, `effective_from`) VALUES
('Maharashtra', 0, 4999, 0, '2024-04-01'),
('Maharashtra', 5000, 9999, 175, '2024-04-01'),
('Maharashtra', 10000, NULL, 200, '2024-04-01');

-- Rajasthan PT Slabs
INSERT INTO `professional_tax_slabs` (`state`, `salary_from`, `salary_to`, `pt_amount`, `effective_from`) VALUES
('Rajasthan', 0, 9999, 0, '2024-04-01'),
('Rajasthan', 10000, 14999, 100, '2024-04-01'),
('Rajasthan', 15000, 19999, 150, '2024-04-01'),
('Rajasthan', 20000, NULL, 200, '2024-04-01');

-- Madhya Pradesh PT Slabs
INSERT INTO `professional_tax_slabs` (`state`, `salary_from`, `salary_to`, `pt_amount`, `effective_from`) VALUES
('Madhya Pradesh', 0, 9999, 0, '2024-04-01'),
('Madhya Pradesh', 10000, 14999, 125, '2024-04-01'),
('Madhya Pradesh', 15000, NULL, 208, '2024-04-01');

-- Chhattisgarh PT Slabs
INSERT INTO `professional_tax_slabs` (`state`, `salary_from`, `salary_to`, `pt_amount`, `effective_from`) VALUES
('Chhattisgarh', 0, 5999, 0, '2024-04-01'),
('Chhattisgarh', 6000, 9999, 100, '2024-04-01'),
('Chhattisgarh', 10000, 14999, 150, '2024-04-01'),
('Chhattisgarh', 15000, NULL, 200, '2024-04-01');

-- =====================================================
-- LABOUR WELFARE FUND
-- =====================================================

CREATE TABLE IF NOT EXISTS `labour_welfare_fund` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `state` VARCHAR(100) NOT NULL,
    `employee_contribution` DECIMAL(10,2) NOT NULL,
    `employer_contribution` DECIMAL(10,2) NOT NULL,
    `contribution_period` ENUM('monthly', 'quarterly', 'half_yearly', 'yearly') DEFAULT 'half_yearly',
    `due_months` VARCHAR(50) DEFAULT '6,12',
    `effective_from` DATE NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `labour_welfare_fund` (`state`, `employee_contribution`, `employer_contribution`, `contribution_period`, `due_months`, `effective_from`) VALUES
('Gujarat', 6.00, 18.00, 'yearly', '12', '2024-01-01'),
('Maharashtra', 12.00, 36.00, 'half_yearly', '6,12', '2024-01-01'),
('Rajasthan', 5.00, 10.00, 'yearly', '12', '2024-01-01'),
('Madhya Pradesh', 5.00, 10.00, 'yearly', '12', '2024-01-01'),
('Chhattisgarh', 5.00, 10.00, 'yearly', '12', '2024-01-01');

-- =====================================================
-- COMPLIANCE & RETURNS
-- =====================================================

-- Compliance Calendar
CREATE TABLE IF NOT EXISTS `compliance_calendar` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `compliance_name` VARCHAR(255) NOT NULL,
    `compliance_type` ENUM('pf', 'esi', 'pt', 'lwf', 'bonus', 'gratuity', 'other') NOT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `frequency` ENUM('monthly', 'quarterly', 'half_yearly', 'yearly', 'one_time') NOT NULL,
    `due_day` INT(11) DEFAULT NULL,
    `due_month` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `form_number` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `compliance_calendar` (`compliance_name`, `compliance_type`, `state`, `frequency`, `due_day`, `description`, `form_number`) VALUES
('PF Monthly Return (ECR)', 'pf', NULL, 'monthly', 15, 'File PF Monthly Return (ECR) on EPFO Portal', 'ECR'),
('PF Payment', 'pf', NULL, 'monthly', 15, 'Deposit PF Contribution by 15th of following month', 'Challan'),
('ESI Monthly Return', 'esi', NULL, 'monthly', 15, 'File ESI Monthly Return on ESIC Portal', 'Return'),
('ESI Payment', 'esi', NULL, 'monthly', 15, 'Deposit ESI Contribution by 15th of following month', 'Challan'),
('Professional Tax Return', 'pt', 'Gujarat', 'monthly', 15, 'File PT Return and Pay by 15th', 'Form 5'),
('Professional Tax Return', 'pt', 'Maharashtra', 'monthly', 21, 'File PT Return by 21st', 'Form III'),
('Professional Tax Return', 'pt', 'Rajasthan', 'yearly', 31, 'File Annual PT Return', 'Form 1'),
('LWF Return', 'lwf', 'Maharashtra', 'half_yearly', 15, 'File LWF Return - June & December', 'Form D'),
('LWF Return', 'lwf', 'Gujarat', 'yearly', 31, 'File LWF Return - December', 'Form A'),
('Annual Return - Contract Labour', 'other', NULL, 'yearly', 31, 'Submit Annual Return under Contract Labour Act', 'Form XXV'),
('License Renewal', 'other', NULL, 'yearly', NULL, 'Renew Contract Labour License', 'Form VI');

-- Compliance Filings
CREATE TABLE IF NOT EXISTS `compliance_filings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `compliance_calendar_id` INT(11) NOT NULL,
    `period_month` INT(2) NOT NULL,
    `period_year` INT(4) NOT NULL,
    `due_date` DATE NOT NULL,
    `filed_date` DATE DEFAULT NULL,
    `status` ENUM('pending', 'filed', 'late', 'cancelled') DEFAULT 'pending',
    `amount` DECIMAL(15,2) DEFAULT NULL,
    `challan_number` VARCHAR(100) DEFAULT NULL,
    `challan_date` DATE DEFAULT NULL,
    `receipt_number` VARCHAR(100) DEFAULT NULL,
    `document_path` VARCHAR(255) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `filed_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `compliance_calendar_id` (`compliance_calendar_id`),
    KEY `period` (`period_month`, `period_year`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- LEAVES
-- =====================================================

CREATE TABLE IF NOT EXISTS `leave_types` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `leave_name` VARCHAR(100) NOT NULL,
    `leave_code` VARCHAR(20) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `days_per_year` INT(11) DEFAULT 0,
    `is_paid` TINYINT(1) DEFAULT 1,
    `is_carry_forward` TINYINT(1) DEFAULT 0,
    `max_carry_forward` INT(11) DEFAULT 0,
    `is_encashable` TINYINT(1) DEFAULT 0,
    `gender_specific` ENUM('all', 'male', 'female') DEFAULT 'all',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `leave_types` (`leave_name`, `leave_code`, `days_per_year`, `is_paid`, `is_carry_forward`, `max_carry_forward`, `is_encashable`) VALUES
('Casual Leave', 'CL', 7, 1, 0, 0, 0),
('Sick Leave', 'SL', 7, 1, 0, 0, 0),
('Earned Leave', 'EL', 15, 1, 1, 30, 1),
('Maternity Leave', 'ML', 182, 1, 0, 0, 0),
('Paternity Leave', 'PL', 15, 1, 0, 0, 0),
('National Holiday', 'NH', 3, 1, 0, 0, 0),
('Weekly Off', 'WO', 4, 1, 0, 0, 0);

-- Employee Leave Balance
CREATE TABLE IF NOT EXISTS `employee_leave_balance` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `leave_type_id` INT(11) NOT NULL,
    `year` INT(4) NOT NULL,
    `opening_balance` DECIMAL(5,2) DEFAULT 0,
    `accrued` DECIMAL(5,2) DEFAULT 0,
    `used` DECIMAL(5,2) DEFAULT 0,
    `balance` DECIMAL(5,2) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_leave_year` (`employee_id`, `leave_type_id`, `year`),
    KEY `year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FORMS & TEMPLATES
-- =====================================================

-- Form Templates
CREATE TABLE IF NOT EXISTS `form_templates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `form_name` VARCHAR(255) NOT NULL,
    `form_code` VARCHAR(50) NOT NULL,
    `form_type` ENUM('appointment_letter', 'form_v', 'form_xvi', 'form_xvii', 'form_f2', 'pf_nomination', 'gratuity_nomination', 'payslip', 'id_card', 'experience_letter', 'relieving_letter', 'other') NOT NULL,
    `template_content` LONGTEXT DEFAULT NULL,
    `variables` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `form_code` (`form_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payslip Formats
CREATE TABLE IF NOT EXISTS `payslip_formats` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `format_name` VARCHAR(100) NOT NULL,
    `format_code` VARCHAR(50) NOT NULL,
    `template_html` LONGTEXT DEFAULT NULL,
    `css_styles` TEXT DEFAULT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 5 Payslip Formats
INSERT INTO `payslip_formats` (`format_name`, `format_code`, `is_default`) VALUES
('Standard Format', 'standard', 1),
('Detailed Format', 'detailed', 0),
('Compact Format', 'compact', 0),
('Client Format A', 'client_a', 0),
('Client Format B', 'client_b', 0);

-- =====================================================
-- HOLIDAYS
-- =====================================================

CREATE TABLE IF NOT EXISTS `holidays` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `holiday_name` VARCHAR(255) NOT NULL,
    `holiday_date` DATE NOT NULL,
    `holiday_type` ENUM('national', 'state', 'local', 'optional') DEFAULT 'national',
    `state` VARCHAR(100) DEFAULT NULL,
    `is_recurring` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert India National Holidays 2024-2025
INSERT INTO `holidays` (`holiday_name`, `holiday_date`, `holiday_type`, `is_recurring`) VALUES
('Republic Day', '2024-01-26', 'national', 1),
('Holi', '2024-03-25', 'national', 0),
('Good Friday', '2024-03-29', 'national', 0),
('Independence Day', '2024-08-15', 'national', 1),
('Gandhi Jayanti', '2024-10-02', 'national', 1),
('Diwali', '2024-11-01', 'national', 0),
('Christmas', '2024-12-25', 'national', 1),
('New Year Day', '2025-01-01', 'national', 1),
('Republic Day', '2025-01-26', 'national', 1);

-- Gujarat State Holidays
INSERT INTO `holidays` (`holiday_name`, `holiday_date`, `holiday_type`, `state`, `is_recurring`) VALUES
('Gujarat Day', '2024-05-01', 'state', 'Gujarat', 1),
('Uttarayan', '2024-01-14', 'state', 'Gujarat', 1),
('Janmashtami', '2024-08-26', 'state', 'Gujarat', 0);

-- Maharashtra State Holidays
INSERT INTO `holidays` (`holiday_name`, `holiday_date`, `holiday_type`, `state`, `is_recurring`) VALUES
('Maharashtra Day', '2024-05-01', 'state', 'Maharashtra', 1),
('Gudi Padwa', '2024-04-09', 'state', 'Maharashtra', 0);

-- =====================================================
-- API INTEGRATION
-- =====================================================

CREATE TABLE IF NOT EXISTS `api_integrations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `integration_name` VARCHAR(255) NOT NULL,
    `api_endpoint` VARCHAR(500) NOT NULL,
    `api_key` VARCHAR(255) DEFAULT NULL,
    `api_secret` VARCHAR(255) DEFAULT NULL,
    `auth_type` ENUM('none', 'api_key', 'basic', 'bearer', 'oauth') DEFAULT 'none',
    `last_sync` DATETIME DEFAULT NULL,
    `sync_status` ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `api_integrations` (`integration_name`, `api_endpoint`, `auth_type`) VALUES
('Employee Self Registration Portal', 'https://sid.rcsfacility.com/api/employees', 'none');

-- API Sync Log
CREATE TABLE IF NOT EXISTS `api_sync_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `integration_id` INT(11) NOT NULL,
    `sync_type` ENUM('employee_import', 'attendance_import', 'full_sync') NOT NULL,
    `records_fetched` INT(11) DEFAULT 0,
    `records_created` INT(11) DEFAULT 0,
    `records_updated` INT(11) DEFAULT 0,
    `records_failed` INT(11) DEFAULT 0,
    `status` ENUM('success', 'partial', 'failed') DEFAULT 'success',
    `error_message` TEXT DEFAULT NULL,
    `started_at` DATETIME NOT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `integration_id` (`integration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    `module` VARCHAR(100) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REPORTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `saved_reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `report_name` VARCHAR(255) NOT NULL,
    `report_type` VARCHAR(100) NOT NULL,
    `filters` TEXT DEFAULT NULL,
    `columns` TEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- END OF DATABASE SCHEMA
-- =====================================================
