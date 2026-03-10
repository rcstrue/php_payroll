-- =====================================================
-- Fix Duplicate IDs and TINYINT columns
-- Run these SQL commands one by one in order
-- =====================================================

-- STEP 1: Find duplicate IDs in employees table
SELECT id, COUNT(*) as count FROM employees GROUP BY id HAVING count > 1;

-- STEP 2: If duplicates exist, fix them by assigning new IDs
-- First, create a temporary column to store new IDs
ALTER TABLE `employees` ADD COLUMN `new_id` INT(11) NULL;

-- Update the new_id column with sequential values
SET @row = 0;
UPDATE `employees` SET `new_id` = (@row := @row + 1) ORDER BY `id`;

-- STEP 3: Drop the old id column and rename new_id to id
-- WARNING: This may break foreign key relationships
-- First, make sure to backup your data!

-- Option A: If employees.id is referenced by other tables, update those first
-- Check payroll table
UPDATE payroll p JOIN employees e ON p.employee_id = e.id SET p.employee_id = e.new_id;

-- Check attendance table  
UPDATE attendance a JOIN employees e ON a.employee_id = e.id SET a.employee_id = e.new_id;

-- Check employee_documents table
UPDATE employee_documents d JOIN employees e ON d.employee_id = e.id SET d.employee_id = e.new_id;

-- Check employee_salary_structures if exists
UPDATE employee_salary_structures s JOIN employees e ON s.employee_id = e.id SET s.employee_id = e.new_id;

-- Now drop old id and use new_id
ALTER TABLE `employees` DROP PRIMARY KEY;
ALTER TABLE `employees` DROP COLUMN `id`;
ALTER TABLE `employees` CHANGE `new_id` `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;

-- =====================================================
-- STEP 4: Now fix all other tables' id columns
-- =====================================================

ALTER TABLE `clients` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `units` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `roles` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `companies` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `settings` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `payroll` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `payroll_periods` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `attendance` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `attendance_summary` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `compliance_calendar` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `compliance_filings` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `notifications` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `payslip_templates` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `employee_documents` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `contracts` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `holidays` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `audit_log` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_sessions` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `minimum_wages` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `pf_rates` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `esi_rates` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `professional_tax_rates` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lwf_rates` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `states` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `zones` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `industries` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
