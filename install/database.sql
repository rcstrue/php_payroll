-- =====================================================
-- RCS HRMS Pro - Database Schema (MariaDB Compatible)
-- For: RCS TRUE FACILITIES PVT LTD
-- Compatible: MariaDB 10.3+, MySQL 5.7+
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- COMPANY & SYSTEM SETTINGS
-- =====================================================

CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `address` text,
  `city` varchar(100),
  `state` varchar(100),
  `pincode` varchar(10),
  `gst_number` varchar(20),
  `pan_number` varchar(10),
  `contact_email` varchar(100),
  `contact_phone` varchar(20),
  `logo_path` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('general','payroll','compliance','attendance','email') DEFAULT 'general',
  `description` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- USERS & ROLES
-- =====================================================

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_code` varchar(20) NOT NULL,
  `description` text,
  `permissions` text,
  `level` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_code` (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `first_name` varchar(100),
  `last_name` varchar(100),
  `phone` varchar(20),
  `profile_image` varchar(255),
  `language` enum('en','hi') DEFAULT 'en',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime,
  `login_attempts` int(11) DEFAULT 0,
  `password_changed_at` datetime,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  `expires_at` datetime,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(100) NOT NULL,
  `module` varchar(50),
  `record_id` int(11),
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CLIENTS & UNITS
-- =====================================================

CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_code` varchar(20) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `address` text,
  `city` varchar(100),
  `state` varchar(100),
  `pincode` varchar(10),
  `gst_number` varchar(20),
  `contact_person` varchar(100),
  `contact_phone` varchar(20),
  `contact_email` varchar(100),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_code` (`client_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `unit_name` varchar(255) NOT NULL,
  `address` text,
  `city` varchar(100),
  `state` varchar(100),
  `pincode` varchar(10),
  `contact_person` varchar(100),
  `contact_phone` varchar(20),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unit_code` (`unit_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `unit_id` int(11),
  `contract_type` enum('manpower','housekeeping','security','other') DEFAULT 'manpower',
  `start_date` date NOT NULL,
  `end_date` date,
  `billing_cycle` enum('monthly','fortnightly','weekly') DEFAULT 'monthly',
  `service_charges` decimal(10,2) DEFAULT 0.00,
  `service_charges_type` enum('percentage','fixed') DEFAULT 'percentage',
  `gst_applicable` tinyint(1) DEFAULT 1,
  `terms_conditions` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_number` (`contract_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EMPLOYEES (Fixed for MariaDB - Removed Generated Columns with CURDATE)
-- =====================================================

CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(20) NOT NULL,
  `biometric_id` varchar(20),
  `salutation` enum('Mr','Mrs','Ms','Dr') DEFAULT 'Mr',
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100),
  `last_name` varchar(100),
  `father_name` varchar(100),
  `mother_name` varchar(100),
  `gender` enum('Male','Female','Other') NOT NULL,
  `date_of_birth` date,
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT 'Single',
  `blood_group` varchar(5),
  `nationality` varchar(50) DEFAULT 'Indian',
  
  -- Contact Details
  `personal_email` varchar(100),
  `official_email` varchar(100),
  `mobile_number` varchar(15),
  `alternate_mobile` varchar(15),
  `emergency_contact_name` varchar(100),
  `emergency_contact_relation` varchar(50),
  `emergency_contact_number` varchar(15),
  
  -- Address Details
  `present_address` text,
  `present_city` varchar(100),
  `present_state` varchar(100),
  `present_pincode` varchar(10),
  `permanent_address` text,
  `permanent_city` varchar(100),
  `permanent_state` varchar(100),
  `permanent_pincode` varchar(10),
  `same_as_present` tinyint(1) DEFAULT 0,
  
  -- Identity Documents
  `aadhaar_number` varchar(12),
  `pan_number` varchar(10),
  `voter_id` varchar(20),
  `driving_license` varchar(20),
  `passport_number` varchar(20),
  `uan_number` varchar(12),
  `esic_ip_number` varchar(17),
  
  -- Bank Details
  `bank_name` varchar(100),
  `bank_branch` varchar(100),
  `bank_account_number` varchar(20),
  `bank_ifsc_code` varchar(11),
  `bank_account_type` enum('Savings','Current','Salary') DEFAULT 'Savings',
  
  -- Employment Details
  `client_id` int(11),
  `unit_id` int(11),
  `contract_id` int(11),
  `designation` varchar(100),
  `department` varchar(100),
  `worker_category` enum('Skilled','Semi-Skilled','Unskilled','Supervisor','Manager','Other') DEFAULT 'Unskilled',
  `worker_type` enum('Worker','Loader','Packer','Supervisor','Manager','Other') DEFAULT 'Worker',
  `employment_type` enum('Permanent','Temporary','Contract','Daily Wages') DEFAULT 'Contract',
  `date_of_joining` date,
  `date_of_leaving` date,
  `probation_period` int(11) DEFAULT 3,
  `confirmation_date` date,
  
  -- Wage Details
  `basic_wage` decimal(10,2) DEFAULT 0.00,
  `da` decimal(10,2) DEFAULT 0.00,
  `hra` decimal(10,2) DEFAULT 0.00,
  `conveyance` decimal(10,2) DEFAULT 0.00,
  `medical_allowance` decimal(10,2) DEFAULT 0.00,
  `special_allowance` decimal(10,2) DEFAULT 0.00,
  `other_allowance` decimal(10,2) DEFAULT 0.00,
  `gross_salary` decimal(10,2) DEFAULT 0.00,
  
  -- Statutory Details
  `pf_applicable` tinyint(1) DEFAULT 1,
  `pf_number` varchar(25),
  `pf_joining_date` date,
  `pf_wages_limit` tinyint(1) DEFAULT 0,
  `pf_contribution_type` enum('Full','Restricted','Voluntary') DEFAULT 'Full',
  `eps_applicable` tinyint(1) DEFAULT 1,
  `esi_applicable` tinyint(1) DEFAULT 1,
  `esi_dispensary` varchar(100),
  `pt_applicable` tinyint(1) DEFAULT 1,
  `lwf_applicable` tinyint(1) DEFAULT 1,
  `bonus_applicable` tinyint(1) DEFAULT 1,
  `gratuity_applicable` tinyint(1) DEFAULT 1,
  `overtime_applicable` tinyint(1) DEFAULT 1,
  
  -- Nominee Details
  `pf_nominee_name` varchar(100),
  `pf_nominee_relation` varchar(50),
  `pf_nominee_dob` date,
  `pf_nominee_share` decimal(5,2) DEFAULT 100.00,
  `esi_nominee_name` varchar(100),
  `esi_nominee_relation` varchar(50),
  `gratuity_nominee_name` varchar(100),
  `gratuity_nominee_relation` varchar(50),
  `gratuity_nominee_dob` date,
  `gratuity_nominee_address` text,
  
  -- Photo & Documents
  `photo_path` varchar(255),
  `signature_path` varchar(255),
  
  -- Status
  `status` enum('Active','Inactive','Left','Terminated','Suspended') DEFAULT 'Active',
  `leaving_reason` text,
  
  -- API Integration
  `external_id` varchar(100),
  `api_sync_status` enum('Synced','Pending','Error') DEFAULT 'Pending',
  `api_sync_date` datetime,
  
  `created_by` int(11),
  `updated_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `aadhaar_number` (`aadhaar_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `document_type` enum('Aadhaar Card','PAN Card','Voter ID','Driving License','Passport','Bank Passbook','Photo','Signature','Police Verification','Education Certificate','Experience Certificate','Medical Certificate','Other') NOT NULL,
  `document_name` varchar(255),
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11),
  `file_type` varchar(50),
  `uploaded_by` int(11),
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11),
  `verified_at` datetime,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STATES & ZONES
-- =====================================================

CREATE TABLE `states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_name` varchar(100) NOT NULL,
  `state_code` varchar(10) NOT NULL,
  `zone_type` enum('Zone','Area','None') DEFAULT 'Zone',
  `pt_applicable` tinyint(1) DEFAULT 1,
  `lwf_applicable` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `state_code` (`state_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `zone_code` varchar(20) NOT NULL,
  `districts` text,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `state_zone` (`state_id`, `zone_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `industries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `industry_name` varchar(255) NOT NULL,
  `industry_code` varchar(20) NOT NULL,
  `category` varchar(100),
  `schedule` varchar(50),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `industry_code` (`industry_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MINIMUM WAGES MASTER
-- =====================================================

CREATE TABLE `minimum_wages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `zone_id` int(11),
  `industry_id` int(11),
  `effective_from` date NOT NULL,
  `effective_to` date,
  `worker_category` enum('Unskilled','Semi-Skilled','Skilled','Highly Skilled','Supervisor','Clerical') NOT NULL,
  `basic_per_day` decimal(10,2) DEFAULT 0.00,
  `basic_per_month` decimal(10,2) DEFAULT 0.00,
  `da_per_day` decimal(10,2) DEFAULT 0.00,
  `da_per_month` decimal(10,2) DEFAULT 0.00,
  `special_allowance_per_day` decimal(10,2) DEFAULT 0.00,
  `special_allowance_per_month` decimal(10,2) DEFAULT 0.00,
  `total_per_day` decimal(10,2) DEFAULT 0.00,
  `total_per_month` decimal(10,2) DEFAULT 0.00,
  `hra_percent` decimal(5,2) DEFAULT 0.00,
  `notification_number` varchar(100),
  `notification_date` date,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STATUTORY RATES
-- =====================================================

CREATE TABLE `pf_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `effective_from` date NOT NULL,
  `employee_share` decimal(5,2) DEFAULT 12.00,
  `employer_share_pf` decimal(5,2) DEFAULT 3.67,
  `employer_share_eps` decimal(5,2) DEFAULT 8.33,
  `employer_share_edlis` decimal(5,2) DEFAULT 0.50,
  `epf_admin_charges` decimal(5,2) DEFAULT 0.50,
  `wage_ceiling` decimal(10,2) DEFAULT 15000.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `esi_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `effective_from` date NOT NULL,
  `employee_share` decimal(5,2) DEFAULT 0.75,
  `employer_share` decimal(5,2) DEFAULT 3.25,
  `wage_ceiling` decimal(10,2) DEFAULT 21000.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `professional_tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  `salary_from` decimal(10,2) NOT NULL,
  `salary_to` decimal(10,2),
  `pt_amount` decimal(10,2) NOT NULL,
  `gender_specific` enum('All','Male','Female') DEFAULT 'All',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lwf_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  `employee_share` decimal(10,2) DEFAULT 0.00,
  `employer_share` decimal(10,2) DEFAULT 0.00,
  `contribution_frequency` enum('Monthly','Quarterly','Half-Yearly','Yearly') DEFAULT 'Yearly',
  `contribution_months` varchar(100),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ATTENDANCE
-- =====================================================

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `unit_id` int(11),
  `status` enum('Present','Absent','Weekly Off','Holiday','Paid Leave','Unpaid Leave','Sick Leave','Casual Leave','Half Day','Overtime Only') DEFAULT 'Present',
  `in_time` time,
  `out_time` time,
  `working_hours` decimal(4,2) DEFAULT 0.00,
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `overtime_approved` tinyint(1) DEFAULT 0,
  `remarks` varchar(255),
  `source` enum('Manual','Excel Upload','Biometric','Mobile App') DEFAULT 'Manual',
  `uploaded_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_date` (`employee_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `unit_id` int(11),
  `total_days` int(11) DEFAULT 0,
  `present_days` decimal(5,2) DEFAULT 0.00,
  `absent_days` decimal(5,2) DEFAULT 0.00,
  `weekly_offs` decimal(5,2) DEFAULT 0.00,
  `holidays` decimal(5,2) DEFAULT 0.00,
  `paid_leaves` decimal(5,2) DEFAULT 0.00,
  `unpaid_leaves` decimal(5,2) DEFAULT 0.00,
  `half_days` decimal(5,2) DEFAULT 0.00,
  `total_working_days` decimal(5,2) DEFAULT 0.00,
  `total_overtime_hours` decimal(6,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_month_year` (`employee_id`, `month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11),
  `holiday_name` varchar(100) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_type` enum('National','State','Local','Optional') DEFAULT 'National',
  `is_recurring` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAYROLL
-- =====================================================

CREATE TABLE `payroll_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_name` varchar(50) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `pay_days` int(11) DEFAULT 31,
  `status` enum('Draft','Processing','Processed','Approved','Paid','Locked') DEFAULT 'Draft',
  `processed_by` int(11),
  `processed_at` datetime,
  `approved_by` int(11),
  `approved_at` datetime,
  `payment_date` date,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `month_year` (`month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payroll_period_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `unit_id` int(11),
  `total_days` int(11) DEFAULT 0,
  `paid_days` decimal(5,2) DEFAULT 0.00,
  `unpaid_days` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(6,2) DEFAULT 0.00,
  `basic` decimal(10,2) DEFAULT 0.00,
  `da` decimal(10,2) DEFAULT 0.00,
  `hra` decimal(10,2) DEFAULT 0.00,
  `conveyance` decimal(10,2) DEFAULT 0.00,
  `medical_allowance` decimal(10,2) DEFAULT 0.00,
  `special_allowance` decimal(10,2) DEFAULT 0.00,
  `other_allowance` decimal(10,2) DEFAULT 0.00,
  `overtime_amount` decimal(10,2) DEFAULT 0.00,
  `gross_earnings` decimal(10,2) DEFAULT 0.00,
  `pf_employee` decimal(10,2) DEFAULT 0.00,
  `esi_employee` decimal(10,2) DEFAULT 0.00,
  `professional_tax` decimal(10,2) DEFAULT 0.00,
  `lwf_employee` decimal(10,2) DEFAULT 0.00,
  `tds` decimal(10,2) DEFAULT 0.00,
  `salary_advance` decimal(10,2) DEFAULT 0.00,
  `other_deduction` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `pf_employer` decimal(10,2) DEFAULT 0.00,
  `eps_employer` decimal(10,2) DEFAULT 0.00,
  `edlis_employer` decimal(10,2) DEFAULT 0.00,
  `epf_admin_charges` decimal(10,2) DEFAULT 0.00,
  `esi_employer` decimal(10,2) DEFAULT 0.00,
  `lwf_employer` decimal(10,2) DEFAULT 0.00,
  `bonus_provision` decimal(10,2) DEFAULT 0.00,
  `gratuity_provision` decimal(10,2) DEFAULT 0.00,
  `total_employer_contribution` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) DEFAULT 0.00,
  `gross_salary` decimal(10,2) DEFAULT 0.00,
  `ctc` decimal(10,2) DEFAULT 0.00,
  `payment_mode` enum('Bank Transfer','Cash','Cheque') DEFAULT 'Bank Transfer',
  `payment_status` enum('Pending','Processing','Paid','Failed') DEFAULT 'Pending',
  `status` enum('Draft','Processed','Approved','Paid','Hold') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `period_employee` (`payroll_period_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COMPLIANCE
-- =====================================================

CREATE TABLE `compliance_calendar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compliance_type` enum('PF','ESI','PT','LWF','Bonus','Gratuity','Other') NOT NULL,
  `compliance_name` varchar(100) NOT NULL,
  `due_date` date NOT NULL,
  `frequency` enum('Monthly','Quarterly','Half-Yearly','Yearly','One-time') DEFAULT 'Monthly',
  `state_id` int(11),
  `form_number` varchar(20),
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `compliance_filings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compliance_type` enum('PF','ESI','PT','LWF','Bonus','Gratuity','Other') NOT NULL,
  `filing_period_month` int(11),
  `filing_period_year` int(11),
  `due_date` date,
  `filed_date` date,
  `status` enum('Pending','Filed','Approved','Rejected') DEFAULT 'Pending',
  `filed_by` int(11),
  `reference_number` varchar(100),
  `challan_number` varchar(100),
  `challan_date` date,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payslip_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `template_code` varchar(20) NOT NULL,
  `template_html` text,
  `template_css` text,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_code` (`template_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `notification_type` enum('Compliance','Payroll','System','Update','Alert') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text,
  `link` varchar(255),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Insert Company
INSERT INTO `companies` (`id`, `company_name`, `address`, `city`, `state`, `pincode`, `gst_number`, `pan_number`) VALUES
(1, 'RCS TRUE FACILITIES PVT LTD', '110, Someswar Square, Vesu', 'Surat', 'Gujarat', '395007', '24AAICR1390M1Z3', 'AAICR1390M');

-- Insert Roles
INSERT INTO `roles` (`id`, `role_name`, `role_code`, `description`, `level`, `is_active`) VALUES
(1, 'Administrator', 'admin', 'Full system access', 100, 1),
(2, 'HR Executive', 'hr_executive', 'HR operations', 80, 1),
(3, 'Manager', 'manager', 'Unit management', 60, 1),
(4, 'Supervisor', 'supervisor', 'Attendance supervision', 40, 1),
(5, 'Worker', 'worker', 'View own details', 20, 1);

-- Insert Default Admin User (Password: password)
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role_id`, `first_name`, `last_name`, `is_active`) VALUES
(1, 'admin', 'admin@rcsfacility.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'System', 'Admin', 1);

-- Insert States
INSERT INTO `states` (`id`, `state_name`, `state_code`, `zone_type`, `pt_applicable`, `lwf_applicable`) VALUES
(1, 'Maharashtra', 'MH', 'Zone', 1, 1),
(2, 'Gujarat', 'GJ', 'Zone', 1, 1),
(3, 'Rajasthan', 'RJ', 'Zone', 1, 1),
(4, 'Madhya Pradesh', 'MP', 'Zone', 1, 1),
(5, 'Chhattisgarh', 'CG', 'Zone', 1, 1);

-- Insert Zones for Gujarat
INSERT INTO `zones` (`state_id`, `zone_name`, `zone_code`) VALUES
(2, 'Zone 1 (Ahmedabad, Vadodara, Surat, Rajkot)', 'GJ-Z1'),
(2, 'Zone 2 (Rest of Gujarat)', 'GJ-Z2');

-- Insert PF Rates
INSERT INTO `pf_rates` (`effective_from`, `employee_share`, `employer_share_pf`, `employer_share_eps`, `employer_share_edlis`, `epf_admin_charges`, `wage_ceiling`) VALUES
('2024-01-01', 12.00, 3.67, 8.33, 0.50, 0.50, 15000.00);

-- Insert ESI Rates
INSERT INTO `esi_rates` (`effective_from`, `employee_share`, `employer_share`, `wage_ceiling`) VALUES
('2024-01-01', 0.75, 3.25, 21000.00);

-- Insert PT Rates for Gujarat
INSERT INTO `professional_tax_rates` (`state_id`, `effective_from`, `salary_from`, `salary_to`, `pt_amount`) VALUES
(2, '2024-01-01', 0, 5999, 0),
(2, '2024-01-01', 6000, 8999, 80),
(2, '2024-01-01', 9000, 11999, 150),
(2, '2024-01-01', 12000, NULL, 200);

-- Insert PT Rates for Maharashtra
INSERT INTO `professional_tax_rates` (`state_id`, `effective_from`, `salary_from`, `salary_to`, `pt_amount`) VALUES
(1, '2024-01-01', 0, 2500, 0),
(1, '2024-01-01', 2501, 5000, 125),
(1, '2024-01-01', 5001, 10000, 200),
(1, '2024-01-01', 10001, NULL, 200);

-- Insert LWF Rates
INSERT INTO `lwf_rates` (`state_id`, `effective_from`, `employee_share`, `employer_share`, `contribution_frequency`, `contribution_months`) VALUES
(1, '2024-01-01', 12, 36, 'Half-Yearly', '["June", "December"]'),
(2, '2024-01-01', 6, 18, 'Yearly', '["December"]');

-- Insert Industries
INSERT INTO `industries` (`industry_name`, `industry_code`, `category`, `schedule`) VALUES
('Shops & Commercial Establishments', 'SCE', 'Services', 'Schedule I'),
('Security Services', 'SEC', 'Services', 'Schedule II'),
('Housekeeping Services', 'HK', 'Services', 'Schedule I'),
('Construction', 'CONST', 'Industry', 'Schedule IV'),
('Manufacturing - General', 'MFG', 'Industry', 'Schedule I');

-- Insert Payslip Templates
INSERT INTO `payslip_templates` (`template_name`, `template_code`, `is_default`, `is_active`) VALUES
('Standard Payslip', 'STD', 1, 1),
('Detailed Payslip', 'DET', 0, 1),
('Compact Payslip', 'CMP', 0, 1),
('Client Format A', 'CFA', 0, 1),
('Client Format B', 'CFB', 0, 1);

-- Insert Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('company_name', 'RCS TRUE FACILITIES PVT LTD', 'general', 'Company Name'),
('company_address', '110, Someswar Square, Vesu, Surat, 395007', 'general', 'Company Address'),
('company_gst', '24AAICR1390M1Z3', 'general', 'GST Number'),
('company_pan', 'AAICR1390M', 'general', 'PAN Number'),
('overtime_rate', '2.0', 'payroll', 'Overtime rate multiplier');

SET FOREIGN_KEY_CHECKS = 1;
