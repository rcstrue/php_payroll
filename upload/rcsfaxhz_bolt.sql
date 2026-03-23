-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 19, 2026 at 11:34 AM
-- Server version: 10.3.39-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rcsfaxhz_bolt`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager') NOT NULL DEFAULT 'manager',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_summary`
--

CREATE TABLE `attendance_summary` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `total_present` decimal(5,2) DEFAULT 0.00,
  `total_extra` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(6,2) DEFAULT 0.00,
  `total_wo` int(3) DEFAULT 0,
  `source` enum('Manual','Excel Upload') DEFAULT 'Manual',
  `unit_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `gst_number` varchar(20) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `gst_number` varchar(20) DEFAULT NULL,
  `pan_number` varchar(10) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_calendar`
--

CREATE TABLE `compliance_calendar` (
  `id` int(11) NOT NULL,
  `compliance_type` enum('PF','ESI','PT','LWF','Bonus','Gratuity','Other') NOT NULL,
  `compliance_name` varchar(100) NOT NULL,
  `due_date` date NOT NULL,
  `frequency` enum('Monthly','Quarterly','Half-Yearly','Yearly','One-time') DEFAULT 'Monthly',
  `state_id` int(11) DEFAULT NULL,
  `form_number` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_filings`
--

CREATE TABLE `compliance_filings` (
  `id` int(11) NOT NULL,
  `compliance_type` enum('PF','ESI','PT','LWF','Bonus','Gratuity','Other') NOT NULL,
  `filing_period_month` int(11) DEFAULT NULL,
  `filing_period_year` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `filed_date` date DEFAULT NULL,
  `status` enum('Pending','Filed','Approved','Rejected') DEFAULT 'Pending',
  `filed_by` int(11) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `challan_number` varchar(100) DEFAULT NULL,
  `challan_date` date DEFAULT NULL,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `contract_type` enum('manpower','housekeeping','security','other') DEFAULT 'manpower',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `billing_cycle` enum('monthly','fortnightly','weekly') DEFAULT 'monthly',
  `service_charges` decimal(10,2) DEFAULT 0.00,
  `service_charges_type` enum('percentage','fixed') DEFAULT 'percentage',
  `gst_applicable` tinyint(1) DEFAULT 1,
  `terms_conditions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `designations`
--

CREATE TABLE `designations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `desi_view` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `mobile_number` varchar(15) NOT NULL,
  `alternate_mobile` varchar(15) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `father_name` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `aadhaar_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `uan_number` varchar(50) DEFAULT NULL,
  `esic_number` varchar(50) DEFAULT NULL,
  `marital_status` varchar(30) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `pin_code` varchar(10) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `account_holder_name` varchar(255) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `date_of_joining` date DEFAULT NULL,
  `confirmation_date` date DEFAULT NULL,
  `probation_period` int(11) DEFAULT 3,
  `date_of_leaving` date DEFAULT NULL,
  `profile_pic_url` text DEFAULT NULL,
  `profile_pic_cropped_url` text DEFAULT NULL,
  `aadhaar_front_url` text DEFAULT NULL,
  `aadhaar_back_url` text DEFAULT NULL,
  `bank_document_url` text DEFAULT NULL,
  `status` enum('approved','pending_hr_verification','inactive','terminated') DEFAULT 'pending_hr_verification',
  `profile_completion` int(11) DEFAULT 0,
  `employee_role` enum('admin','manager','employee') DEFAULT 'employee',
  `manager_edits_pending` tinyint(1) DEFAULT 0,
  `nominee_name` varchar(255) DEFAULT NULL,
  `nominee_relationship` varchar(100) DEFAULT NULL,
  `nominee_dob` date DEFAULT NULL,
  `nominee_contact` varchar(15) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `designation` varchar(36) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employment_type` enum('Permanent','Temporary','Contract','Daily Wages') DEFAULT 'Contract',
  `worker_category` enum('Skilled','Semi-Skilled','Unskilled','Supervisor','Manager','Other') DEFAULT 'Unskilled',
  `employee_code` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_advances`
--

CREATE TABLE `employee_advances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `adv1` decimal(10,2) DEFAULT 0.00,
  `adv2` decimal(10,2) DEFAULT 0.00,
  `office_advance` decimal(10,2) DEFAULT 0.00,
  `dress_advance` decimal(10,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` enum('Aadhaar Card','PAN Card','Voter ID','Driving License','Passport','Bank Passbook','Photo','Signature','Police Verification','Education Certificate','Experience Certificate','Medical Certificate','Other') NOT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_salary_structures`
--

CREATE TABLE `employee_salary_structures` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `basic_wage` decimal(12,2) DEFAULT 0.00,
  `da` decimal(12,2) DEFAULT 0.00,
  `hra` decimal(12,2) DEFAULT 0.00,
  `conveyance` decimal(12,2) DEFAULT 0.00,
  `medical_allowance` decimal(12,2) DEFAULT 0.00,
  `special_allowance` decimal(12,2) DEFAULT 0.00,
  `other_allowance` decimal(12,2) DEFAULT 0.00,
  `gross_salary` decimal(12,2) DEFAULT 0.00,
  `pf_applicable` tinyint(1) DEFAULT 1,
  `esi_applicable` tinyint(1) DEFAULT 1,
  `pt_applicable` tinyint(1) DEFAULT 1,
  `lwf_applicable` tinyint(1) DEFAULT 1,
  `bonus_applicable` tinyint(1) DEFAULT 1,
  `gratuity_applicable` tinyint(1) DEFAULT 1,
  `overtime_applicable` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `epfo_members`
--

CREATE TABLE `epfo_members` (
  `uan` varchar(50) NOT NULL,
  `member_id` varchar(50) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `gender` varchar(36) DEFAULT NULL,
  `dob` varchar(20) DEFAULT NULL,
  `doj` varchar(20) DEFAULT NULL,
  `father_husband_name` varchar(150) DEFAULT NULL,
  `relation` varchar(50) DEFAULT NULL,
  `marital_status` varchar(50) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `aadhaar` varchar(20) DEFAULT NULL,
  `pan` varchar(20) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `nomination_filed` varchar(10) DEFAULT NULL,
  `aadhaar_verified` varchar(10) DEFAULT NULL,
  `face_auth_status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `esi_rates`
--

CREATE TABLE `esi_rates` (
  `id` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  `employee_share` decimal(5,2) DEFAULT 0.75,
  `employer_share` decimal(5,2) DEFAULT 3.25,
  `wage_ceiling` decimal(10,2) DEFAULT 21000.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `state_id` int(11) DEFAULT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_type` enum('National','State','Local','Optional') DEFAULT 'National',
  `is_recurring` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `industries`
--

CREATE TABLE `industries` (
  `id` int(11) NOT NULL,
  `industry_name` varchar(255) NOT NULL,
  `industry_code` varchar(20) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `schedule` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lwf_rates`
--

CREATE TABLE `lwf_rates` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  `employee_share` decimal(10,2) DEFAULT 0.00,
  `employer_share` decimal(10,2) DEFAULT 0.00,
  `contribution_frequency` enum('Monthly','Quarterly','Half-Yearly','Yearly') DEFAULT 'Yearly',
  `contribution_months` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `minimum_wages`
--

CREATE TABLE `minimum_wages` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `industry_id` int(11) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
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
  `notification_number` varchar(100) DEFAULT NULL,
  `notification_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `notification_type` enum('Compliance','Payroll','System','Update','Alert') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `payroll_period_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
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
  `status` enum('Draft','Processed','Approved','Paid','Hold','Frozen','Cancelled') NOT NULL DEFAULT 'Draft',
  `salary_hold` tinyint(1) NOT NULL DEFAULT 0,
  `hold_reason` varchar(255) DEFAULT NULL,
  `hold_date` date DEFAULT NULL,
  `released_date` date DEFAULT NULL,
  `payroll_dirty` tinyint(1) NOT NULL DEFAULT 0,
  `dirty_reason` varchar(255) DEFAULT NULL,
  `exception_type` varchar(100) DEFAULT NULL,
  `last_calculated_at` datetime DEFAULT NULL,
  `calculated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_exceptions`
--

CREATE TABLE `payroll_exceptions` (
  `id` int(11) NOT NULL,
  `payroll_period_id` int(11) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `exception_type` enum('Missing Attendance','Missing Bank Details','Undefined Salary','Invalid Data','Other') NOT NULL,
  `exception_message` text DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_history`
--

CREATE TABLE `payroll_history` (
  `id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `id` int(11) NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `pay_days` int(11) DEFAULT 31,
  `status` enum('Draft','Processing','Processed','Approved','Paid','Locked','Frozen') NOT NULL DEFAULT 'Draft',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `frozen_at` datetime DEFAULT NULL,
  `frozen_by` int(11) DEFAULT NULL,
  `hold_count` int(11) NOT NULL DEFAULT 0,
  `exception_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_records`
--

CREATE TABLE `payroll_records` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `paid_days` decimal(5,2) DEFAULT 0.00,
  `basic_wage` decimal(12,2) DEFAULT 0.00,
  `da` decimal(12,2) DEFAULT 0.00,
  `hra` decimal(12,2) DEFAULT 0.00,
  `gross_earnings` decimal(12,2) DEFAULT 0.00,
  `pf_employee` decimal(12,2) DEFAULT 0.00,
  `esi_employee` decimal(12,2) DEFAULT 0.00,
  `pt` decimal(12,2) DEFAULT 0.00,
  `advance_deduction` decimal(12,2) DEFAULT 0.00,
  `other_deductions` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `net_pay` decimal(12,2) DEFAULT 0.00,
  `pf_employer` decimal(12,2) DEFAULT 0.00,
  `esi_employer` decimal(12,2) DEFAULT 0.00,
  `status` enum('Draft','Processed','Paid') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payslip_templates`
--

CREATE TABLE `payslip_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_code` varchar(20) NOT NULL,
  `template_html` text DEFAULT NULL,
  `template_css` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pfdatabase`
--

CREATE TABLE `pfdatabase` (
  `id` int(11) NOT NULL,
  `uan` bigint(20) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `gender` enum('MALE','FEMALE','OTHER') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `doj` date DEFAULT NULL,
  `father_husband_name` varchar(100) DEFAULT NULL,
  `relation` enum('FATHER','HUSBAND') DEFAULT NULL,
  `marital_status` enum('SINGLE','MARRIED','DIVORCED','WIDOW') DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `email_id` varchar(100) DEFAULT NULL,
  `aadhaar` varchar(20) DEFAULT NULL,
  `pan` varchar(20) DEFAULT NULL,
  `bank_account_no` varchar(30) DEFAULT NULL,
  `ifsc_code` varchar(15) DEFAULT NULL,
  `nomination_filed` enum('YES','NO') DEFAULT NULL,
  `is_aadhaar_verified` enum('YES','NO') DEFAULT NULL,
  `face_auth_status` enum('YES','NO') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pf_rates`
--

CREATE TABLE `pf_rates` (
  `id` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  `employee_share` decimal(5,2) DEFAULT 12.00,
  `employer_share_pf` decimal(5,2) DEFAULT 3.67,
  `employer_share_eps` decimal(5,2) DEFAULT 8.33,
  `employer_share_edlis` decimal(5,2) DEFAULT 0.50,
  `epf_admin_charges` decimal(5,2) DEFAULT 0.50,
  `wage_ceiling` decimal(10,2) DEFAULT 15000.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `professional_tax_rates`
--

CREATE TABLE `professional_tax_rates` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  `salary_from` decimal(10,2) NOT NULL,
  `salary_to` decimal(10,2) DEFAULT NULL,
  `pt_amount` decimal(10,2) NOT NULL,
  `gender_specific` enum('All','Male','Female') DEFAULT 'All',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `level` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('general','payroll','compliance','attendance','email') DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `state_code` varchar(10) NOT NULL,
  `zone_type` enum('Zone','Area','None') DEFAULT 'Zone',
  `pt_applicable` tinyint(1) DEFAULT 1,
  `lwf_applicable` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `language` enum('en','hi') DEFAULT 'en',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `password_changed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `zone_code` varchar(20) NOT NULL,
  `districts` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_month_year` (`employee_id`,`month`,`year`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_code` (`client_code`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `compliance_calendar`
--
ALTER TABLE `compliance_calendar`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `compliance_filings`
--
ALTER TABLE `compliance_filings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_number` (`contract_number`);

--
-- Indexes for table `designations`
--
ALTER TABLE `designations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile_number` (`mobile_number`),
  ADD UNIQUE KEY `uniq_employee_code` (`employee_code`),
  ADD KEY `idx_employees_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_employee_client` (`client_id`),
  ADD KEY `idx_employee_unit` (`unit_id`),
  ADD KEY `idx_employee_status` (`status`);

--
-- Indexes for table `employee_advances`
--
ALTER TABLE `employee_advances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_emp_month_year` (`employee_id`,`month`,`year`),
  ADD KEY `idx_unit_month_year` (`unit_id`,`month`,`year`),
  ADD KEY `idx_advance_emp_month_year` (`employee_id`,`month`,`year`);

--
-- Indexes for table `employee_salary_structures`
--
ALTER TABLE `employee_salary_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_salary_emp_effective` (`employee_id`,`effective_from`,`effective_to`);

--
-- Indexes for table `epfo_members`
--
ALTER TABLE `epfo_members`
  ADD PRIMARY KEY (`uan`),
  ADD KEY `idx_member_id` (`member_id`);

--
-- Indexes for table `esi_rates`
--
ALTER TABLE `esi_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `industries`
--
ALTER TABLE `industries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `industry_code` (`industry_code`);

--
-- Indexes for table `lwf_rates`
--
ALTER TABLE `lwf_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `minimum_wages`
--
ALTER TABLE `minimum_wages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `period_employee` (`payroll_period_id`,`employee_id`),
  ADD KEY `idx_payroll_period_emp` (`payroll_period_id`,`employee_id`),
  ADD KEY `idx_payroll_status` (`status`),
  ADD KEY `idx_payroll_hold` (`salary_hold`),
  ADD KEY `idx_payroll_dirty` (`payroll_dirty`),
  ADD KEY `idx_payroll_unit` (`unit_id`),
  ADD KEY `idx_payroll_period_status` (`payroll_period_id`,`status`);

--
-- Indexes for table `payroll_exceptions`
--
ALTER TABLE `payroll_exceptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exception_period` (`payroll_period_id`),
  ADD KEY `idx_exception_type` (`exception_type`),
  ADD KEY `idx_exception_resolved` (`is_resolved`);

--
-- Indexes for table `payroll_history`
--
ALTER TABLE `payroll_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_payroll` (`payroll_id`),
  ADD KEY `idx_history_action` (`action`);

--
-- Indexes for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `month_year` (`month`,`year`),
  ADD KEY `idx_period_month_year` (`month`,`year`),
  ADD KEY `idx_period_status` (`status`);

--
-- Indexes for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_period_emp` (`period_id`,`employee_id`);

--
-- Indexes for table `payslip_templates`
--
ALTER TABLE `payslip_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_code` (`template_code`);

--
-- Indexes for table `pfdatabase`
--
ALTER TABLE `pfdatabase`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uan` (`uan`);

--
-- Indexes for table `pf_rates`
--
ALTER TABLE `pf_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `professional_tax_rates`
--
ALTER TABLE `professional_tax_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_code` (`role_code`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `state_code` (`state_code`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit_code` (`unit_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `state_zone` (`state_id`,`zone_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compliance_calendar`
--
ALTER TABLE `compliance_calendar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compliance_filings`
--
ALTER TABLE `compliance_filings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `designations`
--
ALTER TABLE `designations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_advances`
--
ALTER TABLE `employee_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_salary_structures`
--
ALTER TABLE `employee_salary_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `esi_rates`
--
ALTER TABLE `esi_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `industries`
--
ALTER TABLE `industries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lwf_rates`
--
ALTER TABLE `lwf_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `minimum_wages`
--
ALTER TABLE `minimum_wages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_exceptions`
--
ALTER TABLE `payroll_exceptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_history`
--
ALTER TABLE `payroll_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payslip_templates`
--
ALTER TABLE `payslip_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pfdatabase`
--
ALTER TABLE `pfdatabase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pf_rates`
--
ALTER TABLE `pf_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `professional_tax_rates`
--
ALTER TABLE `professional_tax_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
