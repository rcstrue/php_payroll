-- =====================================================
-- RCS HRMS Pro - Manpower Supplier/Contractor Tables
-- Additional tables for manpower supplier functionality
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. CLIENT BILLING & INVOICES
-- =====================================================

-- Invoice Headers
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50) NOT NULL,
    `client_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `contract_id` INT(11) DEFAULT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `period_from` DATE NOT NULL,
    `period_to` DATE NOT NULL,
    `subtotal` DECIMAL(15,2) DEFAULT 0.00,
    `cgst_amount` DECIMAL(15,2) DEFAULT 0.00,
    `sgst_amount` DECIMAL(15,2) DEFAULT 0.00,
    `igst_amount` DECIMAL(15,2) DEFAULT 0.00,
    `tds_amount` DECIMAL(15,2) DEFAULT 0.00,
    `other_charges` DECIMAL(15,2) DEFAULT 0.00,
    `round_off` DECIMAL(15,2) DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `terms_conditions` TEXT DEFAULT NULL,
    `status` ENUM('draft', 'sent', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'draft',
    `sent_at` DATETIME DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoice_number` (`invoice_number`),
    KEY `client_id` (`client_id`),
    KEY `unit_id` (`unit_id`),
    KEY `invoice_date` (`invoice_date`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice Line Items
CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(11) NOT NULL,
    `employee_id` INT(11) DEFAULT NULL,
    `description` VARCHAR(255) NOT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `days_worked` DECIMAL(5,2) DEFAULT 0.00,
    `rate_per_day` DECIMAL(10,2) DEFAULT 0.00,
    `quantity` DECIMAL(10,2) DEFAULT 1.00,
    `unit_price` DECIMAL(10,2) DEFAULT 0.00,
    `amount` DECIMAL(15,2) DEFAULT 0.00,
    `gst_rate` DECIMAL(5,2) DEFAULT 18.00,
    `sort_order` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `invoice_id` (`invoice_id`),
    KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice Payments
CREATE TABLE IF NOT EXISTS `invoice_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(11) NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_mode` ENUM('bank_transfer', 'cheque', 'cash', 'upi', 'neft', 'rtgs') DEFAULT 'bank_transfer',
    `reference_number` VARCHAR(100) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `cheque_number` VARCHAR(50) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `received_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. DEPLOYMENT/PLACEMENT TRACKING
-- =====================================================

-- Employee Deployments
CREATE TABLE IF NOT EXISTS `employee_deployments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `client_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `contract_id` INT(11) DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `deployment_date` DATE NOT NULL,
    `end_date` DATE DEFAULT NULL,
    `billing_rate` DECIMAL(10,2) DEFAULT 0.00,
    `billing_type` ENUM('per_day', 'per_month', 'per_hour') DEFAULT 'per_month',
    `shift_timing` VARCHAR(100) DEFAULT NULL,
    `reporting_to` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('active', 'ended', 'transferred', 'replaced') DEFAULT 'active',
    `end_reason` TEXT DEFAULT NULL,
    `replacement_employee_id` INT(11) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `client_id` (`client_id`),
    KEY `unit_id` (`unit_id`),
    KEY `status` (`status`),
    KEY `deployment_date` (`deployment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. MANPOWER DEMAND/REQUISITION
-- =====================================================

-- Manpower Requisitions
CREATE TABLE IF NOT EXISTS `manpower_requisitions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `requisition_number` VARCHAR(50) NOT NULL,
    `client_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `contract_id` INT(11) DEFAULT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `skill_category` ENUM('unskilled', 'semi-skilled', 'skilled', 'highly-skilled', 'supervisor') DEFAULT 'unskilled',
    `worker_category` ENUM('worker', 'loader', 'packer', 'supervisor', 'security', 'housekeeping', 'other') DEFAULT 'worker',
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `filled_quantity` INT(11) DEFAULT 0,
    `required_by_date` DATE NOT NULL,
    `min_qualification` VARCHAR(100) DEFAULT NULL,
    `min_experience` INT(11) DEFAULT 0,
    `min_age` INT(11) DEFAULT 18,
    `max_age` INT(11) DEFAULT 50,
    `gender_preference` ENUM('any', 'male', 'female') DEFAULT 'any',
    `shift_timing` VARCHAR(100) DEFAULT NULL,
    `billing_rate` DECIMAL(10,2) DEFAULT NULL,
    `special_requirements` TEXT DEFAULT NULL,
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `status` ENUM('pending', 'approved', 'in_progress', 'partially_filled', 'completed', 'cancelled') DEFAULT 'pending',
    `approved_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `requested_by` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `requisition_number` (`requisition_number`),
    KEY `client_id` (`client_id`),
    KEY `status` (`status`),
    KEY `required_by_date` (`required_by_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. RATE CARD MANAGEMENT
-- =====================================================

-- Client Rate Cards
CREATE TABLE IF NOT EXISTS `client_rate_cards` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `client_id` INT(11) DEFAULT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `contract_id` INT(11) DEFAULT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `skill_category` ENUM('unskilled', 'semi-skilled', 'skilled', 'highly-skilled', 'supervisor') DEFAULT 'unskilled',
    `worker_category` ENUM('worker', 'loader', 'packer', 'supervisor', 'security', 'housekeeping', 'other') DEFAULT 'worker',
    `billing_rate_per_day` DECIMAL(10,2) DEFAULT 0.00,
    `billing_rate_per_month` DECIMAL(10,2) DEFAULT 0.00,
    `overtime_rate_per_hour` DECIMAL(10,2) DEFAULT 0.00,
    `night_shift_allowance` DECIMAL(10,2) DEFAULT 0.00,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE DEFAULT NULL,
    `gst_applicable` TINYINT(1) DEFAULT 1,
    `gst_rate` DECIMAL(5,2) DEFAULT 18.00,
    `tds_applicable` TINYINT(1) DEFAULT 1,
    `tds_rate` DECIMAL(5,2) DEFAULT 2.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `client_id` (`client_id`),
    KEY `unit_id` (`unit_id`),
    KEY `designation` (`designation`),
    KEY `effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. TIMESHEET MANAGEMENT
-- =====================================================

-- Client Timesheets
CREATE TABLE IF NOT EXISTS `client_timesheets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `timesheet_number` VARCHAR(50) NOT NULL,
    `client_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `period_from` DATE NOT NULL,
    `period_to` DATE NOT NULL,
    `total_employees` INT(11) DEFAULT 0,
    `total_man_days` DECIMAL(10,2) DEFAULT 0.00,
    `total_overtime_hours` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('draft', 'submitted', 'approved', 'rejected', 'invoiced') DEFAULT 'draft',
    `submitted_at` DATETIME DEFAULT NULL,
    `submitted_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `approved_by` INT(11) DEFAULT NULL,
    `client_approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `client_approved_by` VARCHAR(100) DEFAULT NULL,
    `client_approved_at` DATETIME DEFAULT NULL,
    `invoice_id` INT(11) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `timesheet_number` (`timesheet_number`),
    KEY `client_id` (`client_id`),
    KEY `period_from` (`period_from`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timesheet Details
CREATE TABLE IF NOT EXISTS `client_timesheet_details` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `timesheet_id` INT(11) NOT NULL,
    `employee_id` INT(11) NOT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `days_worked` DECIMAL(5,2) DEFAULT 0.00,
    `overtime_hours` DECIMAL(5,2) DEFAULT 0.00,
    `night_shift_days` INT(11) DEFAULT 0,
    `billing_rate` DECIMAL(10,2) DEFAULT 0.00,
    `overtime_rate` DECIMAL(10,2) DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) DEFAULT 0.00,
    `remarks` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `timesheet_employee` (`timesheet_id`, `employee_id`),
    KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. RECRUITMENT/ONBOARDING
-- =====================================================

-- Job Applicants/Candidates
CREATE TABLE IF NOT EXISTS `job_applicants` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `applicant_code` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `father_name` VARCHAR(255) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
    `mobile_number` VARCHAR(20) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `pincode` VARCHAR(20) DEFAULT NULL,
    `aadhaar_number` VARCHAR(20) DEFAULT NULL,
    `pan_number` VARCHAR(20) DEFAULT NULL,
    `qualification` VARCHAR(100) DEFAULT NULL,
    `experience_years` INT(11) DEFAULT 0,
    `current_employer` VARCHAR(255) DEFAULT NULL,
    `current_salary` DECIMAL(10,2) DEFAULT NULL,
    `expected_salary` DECIMAL(10,2) DEFAULT NULL,
    `skill_category` ENUM('unskilled', 'semi-skilled', 'skilled', 'highly-skilled', 'supervisor') DEFAULT 'unskilled',
    `preferred_location` VARCHAR(100) DEFAULT NULL,
    `resume_path` VARCHAR(255) DEFAULT NULL,
    `photo_path` VARCHAR(255) DEFAULT NULL,
    `source` ENUM('walk-in', 'reference', 'job-portal', 'agency', 'social-media', 'other') DEFAULT 'walk-in',
    `source_reference` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('new', 'screening', 'interview_scheduled', 'interviewed', 'selected', 'offered', 'joined', 'rejected', 'on_hold') DEFAULT 'new',
    `requisition_id` INT(11) DEFAULT NULL,
    `interview_date` DATETIME DEFAULT NULL,
    `interview_notes` TEXT DEFAULT NULL,
    `selection_date` DATE DEFAULT NULL,
    `offer_date` DATE DEFAULT NULL,
    `joining_date` DATE DEFAULT NULL,
    `converted_employee_id` INT(11) DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `assigned_to` INT(11) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `applicant_code` (`applicant_code`),
    KEY `mobile_number` (`mobile_number`),
    KEY `status` (`status`),
    KEY `requisition_id` (`requisition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Applicant Interviews
CREATE TABLE IF NOT EXISTS `applicant_interviews` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `applicant_id` INT(11) NOT NULL,
    `interview_round` INT(11) DEFAULT 1,
    `interview_type` ENUM('telephonic', 'video', 'face_to_face', 'practical') DEFAULT 'face_to_face',
    `scheduled_date` DATETIME NOT NULL,
    `interviewer` VARCHAR(100) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    `rating` INT(1) DEFAULT NULL,
    `feedback` TEXT DEFAULT NULL,
    `recommendation` ENUM('reject', 'hold', 'next_round', 'select') DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `applicant_id` (`applicant_id`),
    KEY `scheduled_date` (`scheduled_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. CLIENT PERFORMANCE FEEDBACK
-- =====================================================

-- Client Feedback on Employees
CREATE TABLE IF NOT EXISTS `client_feedback` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `client_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `employee_id` INT(11) NOT NULL,
    `feedback_date` DATE NOT NULL,
    `feedback_period_from` DATE DEFAULT NULL,
    `feedback_period_to` DATE DEFAULT NULL,
    `punctuality_rating` INT(1) DEFAULT NULL,
    `work_quality_rating` INT(1) DEFAULT NULL,
    `behavior_rating` INT(1) DEFAULT NULL,
    `safety_compliance_rating` INT(1) DEFAULT NULL,
    `overall_rating` DECIMAL(3,1) DEFAULT NULL,
    `strengths` TEXT DEFAULT NULL,
    `areas_of_improvement` TEXT DEFAULT NULL,
    `recommendation` ENUM('retain', 'replace', 'promote', 'train') DEFAULT 'retain',
    `feedback_by` VARCHAR(100) DEFAULT NULL,
    `feedback_by_designation` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `client_id` (`client_id`),
    KEY `employee_id` (`employee_id`),
    KEY `feedback_date` (`feedback_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. ASSET/EQUIPMENT MANAGEMENT
-- =====================================================

-- Assets Master
CREATE TABLE IF NOT EXISTS `assets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `asset_code` VARCHAR(50) NOT NULL,
    `asset_name` VARCHAR(255) NOT NULL,
    `asset_type` ENUM('uniform', 'safety_equipment', 'tools', 'electronics', 'documents', 'keys', 'id_card', 'other') DEFAULT 'other',
    `description` TEXT DEFAULT NULL,
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `purchase_date` DATE DEFAULT NULL,
    `purchase_cost` DECIMAL(10,2) DEFAULT NULL,
    `quantity` INT(11) DEFAULT 1,
    `available_quantity` INT(11) DEFAULT 1,
    `is_returnable` TINYINT(1) DEFAULT 1,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `asset_code` (`asset_code`),
    KEY `asset_type` (`asset_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Asset Issuance
CREATE TABLE IF NOT EXISTS `employee_assets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `asset_id` INT(11) NOT NULL,
    `quantity` INT(11) DEFAULT 1,
    `issue_date` DATE NOT NULL,
    `expected_return_date` DATE DEFAULT NULL,
    `actual_return_date` DATE DEFAULT NULL,
    `issue_condition` ENUM('new', 'good', 'fair') DEFAULT 'good',
    `return_condition` ENUM('good', 'damaged', 'lost') DEFAULT NULL,
    `issue_remarks` VARCHAR(255) DEFAULT NULL,
    `return_remarks` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('issued', 'returned', 'damaged', 'lost') DEFAULT 'issued',
    `damage_charges` DECIMAL(10,2) DEFAULT NULL,
    `issued_by` INT(11) DEFAULT NULL,
    `received_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `asset_id` (`asset_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. OVERTIME BILLING
-- =====================================================

-- Overtime Billing Records
CREATE TABLE IF NOT EXISTS `overtime_billing` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `client_id` INT(11) NOT NULL,
    `unit_id` INT(11) DEFAULT NULL,
    `employee_id` INT(11) NOT NULL,
    `overtime_date` DATE NOT NULL,
    `hours` DECIMAL(4,2) NOT NULL,
    `rate_per_hour` DECIMAL(10,2) DEFAULT 0.00,
    `amount` DECIMAL(10,2) DEFAULT 0.00,
    `billing_status` ENUM('pending', 'billed', 'paid') DEFAULT 'pending',
    `invoice_id` INT(11) DEFAULT NULL,
    `approved_by_client` TINYINT(1) DEFAULT 0,
    `client_approval_date` DATETIME DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `client_id` (`client_id`),
    KEY `employee_id` (`employee_id`),
    KEY `overtime_date` (`overtime_date`),
    KEY `billing_status` (`billing_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INDEXES FOR FOREIGN KEYS
-- =====================================================

ALTER TABLE `invoices` ADD CONSTRAINT `fk_invoice_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);
ALTER TABLE `invoice_items` ADD CONSTRAINT `fk_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;
ALTER TABLE `invoice_payments` ADD CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;
ALTER TABLE `employee_deployments` ADD CONSTRAINT `fk_deployment_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);
ALTER TABLE `manpower_requisitions` ADD CONSTRAINT `fk_requisition_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);
ALTER TABLE `client_rate_cards` ADD CONSTRAINT `fk_ratecard_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);
ALTER TABLE `client_timesheets` ADD CONSTRAINT `fk_timesheet_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);
ALTER TABLE `client_timesheet_details` ADD CONSTRAINT `fk_tsd_timesheet` FOREIGN KEY (`timesheet_id`) REFERENCES `client_timesheets` (`id`) ON DELETE CASCADE;
ALTER TABLE `client_feedback` ADD CONSTRAINT `fk_feedback_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);
ALTER TABLE `employee_assets` ADD CONSTRAINT `fk_empasset_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`);
ALTER TABLE `overtime_billing` ADD CONSTRAINT `fk_ot_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);

SET FOREIGN_KEY_CHECKS = 1;
