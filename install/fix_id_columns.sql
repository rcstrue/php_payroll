-- =====================================================
-- Fix for: Out of range value for column 'id'
-- Run this SQL on your database to fix the id column type
-- =====================================================

-- Fix clients table id column (change from TINYINT to INT)
ALTER TABLE `clients` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Fix units table id column
ALTER TABLE `units` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Fix employees table id column (if using INT, not UUID)
ALTER TABLE `employees` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Fix other tables that might have the same issue
ALTER TABLE `payroll` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `payroll_periods` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `attendance` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `attendance_summary` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `roles` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `companies` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `settings` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Show current table structures
SHOW CREATE TABLE `clients`;
SHOW CREATE TABLE `units`;
SHOW CREATE TABLE `employees`;
