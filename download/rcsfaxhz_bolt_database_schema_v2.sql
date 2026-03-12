-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 07, 2026 at 03:07 PM
-- Server version: 10.3.39-MariaDB
-- PHP Version: 7.4.33
-- Database: `rcsfaxhz_bolt`
-- Company: RCS TRUE FACILITIES PVT LTD

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- IMPORTANT SCHEMA NOTES FOR DEVELOPERS
-- ============================================================
-- 1. employees table uses:
--    - id: VARCHAR(36) UUID (not INT)
--    - full_name: VARCHAR(255) - direct column
--    - father_name: VARCHAR(255) - replaces middle_name
--    - client_id: INT(11) FK to clients.id
--    - client_name: VARCHAR(255) - for display/backup
--    - unit_id: INT(11) FK to units.id
--    - unit_name: VARCHAR(255) - for display/backup
--    - employee_code: INT(10) UNSIGNED
--
-- 2. employee_salary_structures table:
--    - employee_id: VARCHAR(36) matches employees.id
--    - Columns: basic_wage, da, hra, conveyance, medical_allowance, 
--      special_allowance, other_allowance, gross_salary
--
-- 3. attendance/attendance_summary tables use employee_id as INT(11)
--    - This is a MISMATCH with employees.id (UUID)
--    - May need data migration or code handling
--
-- 4. payroll table uses employee_id as INT(11)
--    - Same mismatch issue
--
-- 5. Foreign Keys on employees:
--    - fk_client: client_id -> clients.id
--    - fk_unit: unit_id -> units.id
-- ============================================================

-- --------------------------------------------------------
-- Table: employees (CRITICAL - Uses UUID and has FKs)
-- --------------------------------------------------------
CREATE TABLE `employees` (
  `id` varchar(36) NOT NULL DEFAULT uuid(),
  `mobile_number` varchar(15) NOT NULL,
  `alternate_mobile` varchar(15) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `father_name` varchar(255) DEFAULT NULL,  -- Father's name
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
  `client_name` varchar(255) DEFAULT NULL,  -- For display
  `client_id` int(11) DEFAULT NULL,         -- FK to clients.id
  `unit_name` varchar(255) DEFAULT NULL,    -- For display
  `unit_id` int(11) DEFAULT NULL,           -- FK to units.id
  `date_of_joining` date DEFAULT NULL,
  `confirmation_date` date DEFAULT NULL,
  `probation_period` int(11) DEFAULT 3,
  `date_of_leaving` date DEFAULT NULL,
  `profile_pic_url` text DEFAULT NULL,
  `profile_pic_cropped_url` text DEFAULT NULL,
  `aadhaar_front_url` text DEFAULT NULL,
  `aadhaar_back_url` text DEFAULT NULL,
  `bank_document_url` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending_hr_verification',
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
  `designation` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employment_type` enum('Permanent','Temporary','Contract','Daily Wages') DEFAULT 'Contract',
  `worker_category` enum('Skilled','Semi-Skilled','Unskilled','Supervisor','Manager','Other') DEFAULT 'Unskilled',
  `employee_code` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table: employee_salary_structures
-- --------------------------------------------------------
CREATE TABLE `employee_salary_structures` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` varchar(36) NOT NULL,  -- Matches employees.id (UUID)
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
-- Table: clients
-- --------------------------------------------------------
CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
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
-- Table: units
-- --------------------------------------------------------
CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
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
-- Table: attendance
-- --------------------------------------------------------
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `status` enum('Present','Absent','Weekly Off','Holiday','Paid Leave','Unpaid Leave','Sick Leave','Casual Leave','Half Day','Overtime Only') DEFAULT 'Present',
  `in_time` time DEFAULT NULL,
  `out_time` time DEFAULT NULL,
  `working_hours` decimal(4,2) DEFAULT 0.00,
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `overtime_approved` tinyint(1) DEFAULT 0,
  `remarks` varchar(255) DEFAULT NULL,
  `source` enum('Manual','Excel Upload','Biometric','Mobile App') DEFAULT 'Manual',
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: payroll
-- --------------------------------------------------------
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
  `status` enum('Draft','Processed','Approved','Paid','Hold') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for employees
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile_number` (`mobile_number`),
  ADD UNIQUE KEY `uniq_employee_code` (`employee_code`),
  ADD KEY `idx_employees_status` (`status`),
  ADD KEY `idx_employees_client` (`client_name`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_mobile` (`mobile_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_client` (`client_id`),
  ADD KEY `fk_unit` (`unit_id`);

-- Foreign Keys for employees
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

-- Indexes for employee_salary_structures
ALTER TABLE `employee_salary_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee` (`employee_id`);

-- Full schema continues with all other tables...
-- See the complete schema in the original export
