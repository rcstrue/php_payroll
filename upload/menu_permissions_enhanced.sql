-- Enhanced Menu Permissions Table with Submenu and Action Permissions
-- Run this SQL to upgrade the existing role_menu_permissions table

-- Drop the old table if exists (backup data first if needed)
-- DROP TABLE IF EXISTS role_menu_permissions;

-- Create enhanced menu permissions table
CREATE TABLE IF NOT EXISTS role_menu_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    menu_key VARCHAR(50) NOT NULL COMMENT 'Main menu key like employee, attendance',
    submenu_key VARCHAR(100) DEFAULT NULL COMMENT 'Submenu key like employee/list, employee/add',
    is_visible TINYINT(1) DEFAULT 1 COMMENT 'Visibility toggle',
    can_view TINYINT(1) DEFAULT 1 COMMENT 'Can view records',
    can_add TINYINT(1) DEFAULT 0 COMMENT 'Can add/create records',
    can_edit TINYINT(1) DEFAULT 0 COMMENT 'Can edit records',
    can_delete TINYINT(1) DEFAULT 0 COMMENT 'Can delete records',
    can_export TINYINT(1) DEFAULT 0 COMMENT 'Can export data',
    can_import TINYINT(1) DEFAULT 0 COMMENT 'Can import data',
    can_print TINYINT(1) DEFAULT 0 COMMENT 'Can print data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role_menu_submenu (role_id, menu_key, submenu_key),
    INDEX idx_role_menu (role_id, menu_key),
    INDEX idx_role_submenu (role_id, submenu_key),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create menu_definitions table to store all menus and submenus
CREATE TABLE IF NOT EXISTS menu_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_key VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique menu identifier',
    menu_label VARCHAR(100) NOT NULL COMMENT 'Display label',
    menu_icon VARCHAR(50) DEFAULT NULL COMMENT 'Bootstrap icon class',
    parent_key VARCHAR(50) DEFAULT NULL COMMENT 'Parent menu key for submenus',
    menu_order INT DEFAULT 0 COMMENT 'Display order',
    menu_type ENUM('main', 'submenu', 'action') DEFAULT 'main' COMMENT 'Type of menu item',
    action_type VARCHAR(20) DEFAULT NULL COMMENT 'Action type: view, add, edit, delete, export, import, print',
    page_url VARCHAR(255) DEFAULT NULL COMMENT 'URL path for the menu',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default menu definitions
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
-- Main Menus
('dashboard', 'Dashboard', 'bi-grid-1x2', NULL, 1, 'main', 'dashboard'),
('employee', 'Employees', 'bi-people', NULL, 2, 'main', NULL),
('client', 'Clients & Units', 'bi-building', NULL, 3, 'main', NULL),
('attendance', 'Attendance', 'bi-calendar-check', NULL, 4, 'main', NULL),
('advance', 'Advance', 'bi-wallet2', NULL, 5, 'main', NULL),
('payroll', 'Payroll', 'bi-cash-stack', NULL, 6, 'main', NULL),
('compliance', 'Compliance', 'bi-shield-check', NULL, 7, 'main', NULL),
('forms', 'Forms', 'bi-file-earmark-text', NULL, 8, 'main', NULL),
('deployment', 'Deployments', 'bi-geo-alt', NULL, 9, 'main', NULL),
('requisition', 'Requisitions', 'bi-person-plus', NULL, 10, 'main', NULL),
('recruitment', 'Recruitment', 'bi-person-lines-fill', NULL, 11, 'main', NULL),
('billing', 'Billing', 'bi-receipt', NULL, 12, 'main', NULL),
('timesheet', 'Timesheets', 'bi-table', NULL, 13, 'main', NULL),
('feedback', 'Client Feedback', 'bi-star', NULL, 14, 'main', 'feedback/list'),
('assets', 'Assets', 'bi-box-seam', NULL, 15, 'main', NULL),
('helpdesk', 'Helpdesk', 'bi-headset', NULL, 16, 'main', 'helpdesk/list'),
('leave', 'Leave Management', 'bi-calendar-x', NULL, 17, 'main', 'leave/balance'),
('report', 'Reports', 'bi-bar-chart-line', NULL, 18, 'main', NULL),
('settlement', 'F&F Settlement', 'bi-cash-coin', NULL, 19, 'main', 'settlement/list'),
('notifications', 'Notifications', 'bi-bell', NULL, 20, 'main', 'notifications/center'),
('settings', 'Settings', 'bi-gear', NULL, 21, 'main', NULL)
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Employee Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('employee_list', 'All Employees', 'bi-people', 'employee', 1, 'submenu', 'employee/list'),
('employee_add', 'Add Employee', 'bi-person-plus', 'employee', 2, 'submenu', 'employee/add'),
('employee_import', 'Import Employees', 'bi-upload', 'employee', 3, 'submenu', 'employee/import'),
('employee_documents', 'Documents', 'bi-file-earmark', 'employee', 4, 'submenu', 'employee/documents')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Client & Units Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('client_list', 'Clients', 'bi-building', 'client', 1, 'submenu', 'client/list'),
('unit_list', 'Units', 'bi-geo', 'client', 2, 'submenu', 'unit/list'),
('contract_list', 'Contracts', 'bi-file-text', 'client', 3, 'submenu', 'contract/list')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Attendance Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('attendance_add', 'Add Attendance', 'bi-plus-circle', 'attendance', 1, 'submenu', 'attendance/add'),
('attendance_upload', 'Upload Attendance', 'bi-upload', 'attendance', 2, 'submenu', 'attendance/upload'),
('attendance_view', 'View Attendance', 'bi-eye', 'attendance', 3, 'submenu', 'attendance/view'),
('attendance_report', 'Attendance Report', 'bi-bar-chart', 'attendance', 4, 'submenu', 'attendance/report')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Advance Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('advance_add', 'Add Advance', 'bi-plus-circle', 'advance', 1, 'submenu', 'advance/add')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Payroll Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('payroll_process', 'Process Payroll', 'bi-calculator', 'payroll', 1, 'submenu', 'payroll/process'),
('payroll_view', 'View Payroll', 'bi-eye', 'payroll', 2, 'submenu', 'payroll/view'),
('payroll_salary_revision', 'Salary Revision', 'bi-graph-up', 'payroll', 3, 'submenu', 'payroll/salary-revision'),
('payroll_payslips', 'Payslips', 'bi-file-earmark-text', 'payroll', 4, 'submenu', 'payroll/payslips'),
('payroll_bank_advice', 'Bank Advice', 'bi-bank', 'payroll', 5, 'submenu', 'payroll/bank-advice')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Compliance Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('compliance_dashboard', 'Compliance Dashboard', 'bi-speedometer2', 'compliance', 1, 'submenu', 'compliance/dashboard'),
('compliance_pf', 'PF ECR Generator', 'bi-file-earmark-code', 'compliance', 2, 'submenu', 'compliance/pf'),
('compliance_esi', 'ESI Returns', 'bi-hospital', 'compliance', 3, 'submenu', 'compliance/esi'),
('compliance_pt', 'PT Returns', 'bi-cash', 'compliance', 4, 'submenu', 'compliance/pt'),
('compliance_minimum_wages', 'Minimum Wages', 'bi-cash-stack', 'compliance', 5, 'submenu', 'compliance/minimum-wages'),
('compliance_mw_validation', 'MW Validation', 'bi-check-circle', 'compliance', 6, 'submenu', 'compliance/minimum-wage-check')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Forms Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('forms_appointment', 'Appointment Letter', 'bi-file-text', 'forms', 1, 'submenu', 'forms/appointment'),
('forms_form_v', 'Form V', 'bi-file-earmark', 'forms', 2, 'submenu', 'forms/form-v'),
('forms_form_xvi', 'Form XVI', 'bi-file-earmark', 'forms', 3, 'submenu', 'forms/form-xvi'),
('forms_form_xvii', 'Form XVII', 'bi-file-earmark', 'forms', 4, 'submenu', 'forms/form-xvii'),
('forms_form_f2', 'Form F2', 'bi-file-earmark', 'forms', 5, 'submenu', 'forms/form-f2'),
('forms_nomination', 'Nomination Forms', 'bi-file-earmark-check', 'forms', 6, 'submenu', 'forms/nomination')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Deployment Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('deployment_list', 'All Deployments', 'bi-geo-alt', 'deployment', 1, 'submenu', 'deployment/list'),
('deployment_add', 'New Deployment', 'bi-plus-circle', 'deployment', 2, 'submenu', 'deployment/add')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Requisition Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('requisition_list', 'All Requisitions', 'bi-list', 'requisition', 1, 'submenu', 'requisition/list'),
('requisition_add', 'New Requisition', 'bi-plus-circle', 'requisition', 2, 'submenu', 'requisition/add')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Recruitment Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('recruitment_list', 'All Candidates', 'bi-people', 'recruitment', 1, 'submenu', 'recruitment/list'),
('recruitment_add', 'Add Candidate', 'bi-person-plus', 'recruitment', 2, 'submenu', 'recruitment/add')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Billing Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('billing_list', 'Invoices', 'bi-receipt', 'billing', 1, 'submenu', 'billing/list'),
('billing_gst_invoice', 'GST Invoice', 'bi-file-earmark-text', 'billing', 2, 'submenu', 'billing/gst-invoice')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Timesheet Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('timesheet_list', 'All Timesheets', 'bi-table', 'timesheet', 1, 'submenu', 'timesheet/list'),
('timesheet_create', 'Create Timesheet', 'bi-plus-circle', 'timesheet', 2, 'submenu', 'timesheet/create')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Assets Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('assets_list', 'All Assets', 'bi-box-seam', 'assets', 1, 'submenu', 'assets/list'),
('assets_issue', 'Issue Asset', 'bi-arrow-right-circle', 'assets', 2, 'submenu', 'assets/issue')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Reports Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('report_employee', 'Employee Reports', 'bi-people', 'report', 1, 'submenu', 'report/employee'),
('report_attendance', 'Attendance Reports', 'bi-calendar', 'report', 2, 'submenu', 'report/attendance'),
('report_payroll', 'Payroll Reports', 'bi-cash-stack', 'report', 3, 'submenu', 'report/payroll'),
('report_compliance', 'Compliance Reports', 'bi-shield-check', 'report', 4, 'submenu', 'report/compliance'),
('report_custom', 'Custom Report Builder', 'bi-tools', 'report', 5, 'submenu', 'report/custom')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Settings Submenus
INSERT INTO menu_definitions (menu_key, menu_label, menu_icon, parent_key, menu_order, menu_type, page_url) VALUES
('settings_company', 'Company', 'bi-building', 'settings', 1, 'submenu', 'settings/company'),
('settings_users', 'Users', 'bi-people', 'settings', 2, 'submenu', 'settings/users'),
('settings_roles', 'Roles', 'bi-shield-lock', 'settings', 3, 'submenu', 'settings/roles'),
('settings_menu_permissions', 'Menu Permissions', 'bi-list-check', 'settings', 4, 'submenu', 'settings/menu-permissions'),
('settings_payslip_templates', 'Payslip Templates', 'bi-file-earmark-text', 'settings', 5, 'submenu', 'settings/payslip-templates'),
('settings_statutory', 'Statutory Rates', 'bi-percent', 'settings', 6, 'submenu', 'settings/statutory')
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label);

-- Insert default permissions for admin role (role_id = 1 for admin)
-- This grants full access to admin
INSERT INTO role_menu_permissions (role_id, menu_key, submenu_key, is_visible, can_view, can_add, can_edit, can_delete, can_export, can_import, can_print)
SELECT 1, menu_key, menu_key as submenu_key, 1, 1, 1, 1, 1, 1, 1, 1
FROM menu_definitions WHERE menu_type = 'main'
ON DUPLICATE KEY UPDATE is_visible = 1, can_view = 1, can_add = 1, can_edit = 1, can_delete = 1, can_export = 1, can_import = 1, can_print = 1;
