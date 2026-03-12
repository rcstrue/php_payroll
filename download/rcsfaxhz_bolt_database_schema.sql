-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 07, 2026 at 02:46 PM
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
--    - client_name: VARCHAR(255) (not client_id)
--    - unit_name: VARCHAR(255) (not unit_id)
--    - full_name: VARCHAR(255) (computed from middle_name or direct)
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
-- ============================================================

-- --------------------------------------------------------
-- Table: admin_users
-- --------------------------------------------------------
CREATE TABLE `admin_users` (
  `id` varchar(36) NOT NULL DEFAULT uuid(),
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager') NOT NULL DEFAULT 'manager',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table: employees (CRITICAL - Uses UUID and string columns)
-- --------------------------------------------------------
CREATE TABLE `employees` (
  `id` varchar(36) NOT NULL DEFAULT uuid(),
  `mobile_number` varchar(15) NOT NULL,
  `alternate_mobile` varchar(15) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `middle_name` varchar(100) NOT NULL,
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
  `client_name` varchar(255) DEFAULT NULL,  -- NOTE: This is a STRING, not client_id FK
  `unit_name` varchar(255) DEFAULT NULL,    -- NOTE: This is a STRING, not unit_id FK
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
-- Table: employee_salary_structures (CORRECT TABLE NAME)
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

-- Full schema continues with all other tables...
-- See the complete schema in the original export
