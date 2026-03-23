-- ============================================
-- RCS HRMS Pro - Hybrid Payroll Migration
-- Version: 4.0.0 - Unit-wise Processing + Bulk Upload
-- ============================================

-- 1. Update employee_salary_structures table for new salary components
ALTER TABLE employee_salary_structures 
ADD COLUMN IF NOT EXISTS basic_da DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Basic + DA combined' AFTER employee_id,
ADD COLUMN IF NOT EXISTS lww DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Labour Welfare Wages' AFTER hra,
ADD COLUMN IF NOT EXISTS bonus_amount DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Bonus amount' AFTER lww,
ADD COLUMN IF NOT EXISTS washing_allowance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Washing allowance' AFTER bonus_amount,
ADD COLUMN IF NOT EXISTS other_allowance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Other allowances' AFTER washing_allowance;

-- Update gross_salary calculation to include new components
-- UPDATE employee_salary_structures SET gross_salary = COALESCE(basic_da,0) + COALESCE(hra,0) + COALESCE(lww,0) + COALESCE(bonus_amount,0) + COALESCE(washing_allowance,0) + COALESCE(other_allowance,0);

-- Migrate existing data to new structure (basic_da = basic_wage + da)
UPDATE employee_salary_structures 
SET basic_da = COALESCE(basic_wage,0) + COALESCE(da,0),
    lww = COALESCE(lww,0),
    bonus_amount = COALESCE(bonus_amount,0),
    washing_allowance = COALESCE(washing_allowance,0),
    other_allowance = COALESCE(other_allowance,0) + COALESCE(conveyance,0) + COALESCE(medical_allowance,0) + COALESCE(special_allowance,0)
WHERE basic_da IS NULL OR basic_da = 0;

-- 2. Update payroll table for new salary components
ALTER TABLE payroll
ADD COLUMN IF NOT EXISTS basic_da DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Basic + DA' AFTER basic,
ADD COLUMN IF NOT EXISTS lww DECIMAL(12,2) DEFAULT 0.00 AFTER hra,
ADD COLUMN IF NOT EXISTS bonus_amount DECIMAL(12,2) DEFAULT 0.00 AFTER lww,
ADD COLUMN IF NOT EXISTS washing_allowance DECIMAL(12,2) DEFAULT 0.00 AFTER bonus_amount,
ADD COLUMN IF NOT EXISTS other_allowance DECIMAL(12,2) DEFAULT 0.00 AFTER washing_allowance;

-- 3. Add unit-wise processing tracking to payroll_periods
ALTER TABLE payroll_periods
ADD COLUMN IF NOT EXISTS processing_mode ENUM('bulk', 'unit_wise', 'hybrid') DEFAULT 'hybrid' AFTER status,
ADD COLUMN IF NOT EXISTS total_units INT DEFAULT 0 AFTER processing_mode,
ADD COLUMN IF NOT EXISTS processed_units INT DEFAULT 0 AFTER total_units,
ADD COLUMN IF NOT EXISTS finalized_units INT DEFAULT 0 AFTER processed_units;

-- 4. Create unit-wise payroll status tracking table
CREATE TABLE IF NOT EXISTS payroll_unit_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_period_id INT NOT NULL,
    client_id INT NULL,
    unit_id INT NOT NULL,
    status ENUM('pending', 'attendance_uploaded', 'processed', 'approved', 'finalized') DEFAULT 'pending',
    employee_count INT DEFAULT 0,
    total_gross DECIMAL(12,2) DEFAULT 0.00,
    total_net DECIMAL(12,2) DEFAULT 0.00,
    attendance_uploaded_at DATETIME NULL,
    attendance_uploaded_by INT NULL,
    processed_at DATETIME NULL,
    processed_by INT NULL,
    approved_at DATETIME NULL,
    approved_by INT NULL,
    finalized_at DATETIME NULL,
    finalized_by INT NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_period_unit (payroll_period_id, unit_id),
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create bulk upload log table
CREATE TABLE IF NOT EXISTS bulk_upload_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_type ENUM('attendance', 'salary_structure', 'salary_update', 'employee_master') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NULL,
    total_rows INT DEFAULT 0,
    processed_rows INT DEFAULT 0,
    error_rows INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_details TEXT NULL,
    period_id INT NULL,
    client_id INT NULL,
    unit_id INT NULL,
    uploaded_by INT NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create salary revision history table (if not exists)
CREATE TABLE IF NOT EXISTS salary_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    old_basic_da DECIMAL(12,2) DEFAULT 0.00,
    new_basic_da DECIMAL(12,2) DEFAULT 0.00,
    old_hra DECIMAL(12,2) DEFAULT 0.00,
    new_hra DECIMAL(12,2) DEFAULT 0.00,
    old_lww DECIMAL(12,2) DEFAULT 0.00,
    new_lww DECIMAL(12,2) DEFAULT 0.00,
    old_bonus DECIMAL(12,2) DEFAULT 0.00,
    new_bonus DECIMAL(12,2) DEFAULT 0.00,
    old_washing DECIMAL(12,2) DEFAULT 0.00,
    new_washing DECIMAL(12,2) DEFAULT 0.00,
    old_other DECIMAL(12,2) DEFAULT 0.00,
    new_other DECIMAL(12,2) DEFAULT 0.00,
    old_gross DECIMAL(12,2) DEFAULT 0.00,
    new_gross DECIMAL(12,2) DEFAULT 0.00,
    revision_type ENUM('percentage', 'fixed', 'daily_rate', 'monthly_rate', 'bulk_update') DEFAULT 'fixed',
    percentage DECIMAL(5,2) NULL,
    daily_rate DECIMAL(10,2) NULL,
    effective_from DATE NOT NULL,
    revision_month TINYINT NULL,
    revision_year YEAR NULL,
    reason VARCHAR(255) NULL,
    bulk_upload_id INT NULL,
    revision_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (revision_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Add bulk upload permissions to menu
INSERT IGNORE INTO menu_permissions (role_id, menu_key, submenu_key, can_view, can_add, can_edit, can_delete, created_at)
SELECT r.id, 'bulk_upload', 'bulk_upload_salary', 1, 1, 1, 1, NOW()
FROM roles r WHERE r.code IN ('admin', 'hr_executive');

INSERT IGNORE INTO menu_permissions (role_id, menu_key, submenu_key, can_view, can_add, can_edit, can_delete, created_at)
SELECT r.id, 'bulk_upload', 'bulk_upload_attendance', 1, 1, 1, 1, NOW()
FROM roles r WHERE r.code IN ('admin', 'hr_executive');

-- 8. Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_payroll_unit_status_period ON payroll_unit_status(payroll_period_id);
CREATE INDEX IF NOT EXISTS idx_payroll_unit_status_unit ON payroll_unit_status(unit_id);
CREATE INDEX IF NOT EXISTS idx_payroll_unit_status_status ON payroll_unit_status(status);
CREATE INDEX IF NOT EXISTS idx_bulk_upload_logs_type ON bulk_upload_logs(upload_type);
CREATE INDEX IF NOT EXISTS idx_bulk_upload_logs_status ON bulk_upload_logs(status);

-- Done!
SELECT 'Hybrid Payroll Migration completed successfully!' as message;
