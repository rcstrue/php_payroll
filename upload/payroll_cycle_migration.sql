-- Migration: Add Payroll Cycle Support to Units
-- Some units have payroll from 26th to 25th of next month
-- Run this SQL to add the necessary columns

-- Add payroll cycle columns to units table
ALTER TABLE `units` 
ADD COLUMN `payroll_cycle_start` INT(2) DEFAULT 1 COMMENT 'Day of month when payroll cycle starts (e.g., 26)',
ADD COLUMN `payroll_cycle_end` INT(2) DEFAULT 0 COMMENT 'Day of month when payroll cycle ends (0 = last day, e.g., 25 means 25th)';

-- Add total_days column to attendance_summary for unit-specific total days
ALTER TABLE `attendance_summary`
ADD COLUMN `total_days_in_cycle` INT(2) DEFAULT 30 COMMENT 'Total days in payroll cycle for this period';

-- Example: Update units that follow 26-25 cycle
-- UPDATE `units` SET payroll_cycle_start = 26, payroll_cycle_end = 25 WHERE id IN (1, 2, 3);
