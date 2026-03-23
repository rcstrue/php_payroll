<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RCS HRMS Pro - Human Resource Management System for Labour Contractors">
    <meta name="author" content="RCS TRUE FACILITIES PVT LTD">
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>RCS HRMS Pro</title>
    
    <?php
    // Detect base path for subdirectory installations (e.g., /hrms/)
    $basePath = '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // Find the base path by removing 'index.php' from the script name
    if (strpos($scriptName, 'index.php') !== false) {
        $basePath = dirname($scriptName);
        // Normalize - remove trailing slash issues
        $basePath = rtrim($basePath, '/\\');
        if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
            $basePath = '';
        }
    }
    // Define a global constant for asset URLs
    if (!defined('ASSET_URL')) {
        define('ASSET_URL', $basePath);
    }
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo ASSET_URL; ?>/assets/images/favicon.svg">
    <link rel="alternate icon" type="image/png" href="<?php echo ASSET_URL; ?>/assets/images/logo.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- Datepicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <!-- Custom CSS -->
    <link href="<?php echo ASSET_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($extraCSS)) {
        echo $extraCSS;
    } ?>
</head>
<body class="<?php echo $isLoggedIn ? '' : 'login-page'; ?>">
    
    <?php if ($isLoggedIn): ?>
    <!-- Sidebar -->
    <nav id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <img src="<?php echo ASSET_URL; ?>/assets/images/logo.png" alt="RCS HRMS" class="sidebar-logo">
                <span class="sidebar-brand-text">RCS HRMS Pro</span>
            </a>
            <button type="button" class="sidebar-close d-lg-none" id="sidebar-close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-body">
            <ul class="sidebar-nav">
                <!-- Dashboard - Always visible to logged in users -->
                <li class="sidebar-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                    <a href="index.php?page=dashboard" class="sidebar-link">
                        <i class="bi bi-grid-1x2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php 
                // Helper function to check menu permission
                function showMenu($auth, $menuKey) {
                    if ($_SESSION['role_code'] === 'admin') {
                        return true;
                    }
                    return $auth->canSeeMenu($menuKey);
                }
                
                // Helper function to check submenu permission
                function showSubmenu($auth, $submenuKey) {
                    if ($_SESSION['role_code'] === 'admin') {
                        return true;
                    }
                    return $auth->canSeeSubmenu($submenuKey);
                }
                ?>
                
                <!-- Employees -->
                <?php if (showMenu($auth, 'employee')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'employee') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-people"></i>
                        <span>Employees</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'employee_list')): ?>
                        <li><a href="index.php?page=employee/list" class="<?php echo $page == 'employee/list' ? 'active' : ''; ?>">All Employees</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'employee_add')): ?>
                        <li><a href="index.php?page=employee/add" class="<?php echo $page == 'employee/add' ? 'active' : ''; ?>">Add Employee</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'employee_import')): ?>
                        <li><a href="index.php?page=employee/import" class="<?php echo $page == 'employee/import' ? 'active' : ''; ?>">Import Employees</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'employee_documents')): ?>
                        <li><a href="index.php?page=employee/documents" class="<?php echo $page == 'employee/documents' ? 'active' : ''; ?>">Documents</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Clients & Units -->
                <?php if (showMenu($auth, 'client')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'client') === 0 || strpos($page, 'unit') === 0 || strpos($page, 'contract') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-building"></i>
                        <span>Clients & Units</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'client_list')): ?>
                        <li><a href="index.php?page=client/list" class="<?php echo $page == 'client/list' ? 'active' : ''; ?>">Clients</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'unit_list')): ?>
                        <li><a href="index.php?page=unit/list" class="<?php echo $page == 'unit/list' ? 'active' : ''; ?>">Units</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'contract_list')): ?>
                        <li><a href="index.php?page=contract/list" class="<?php echo $page == 'contract/list' ? 'active' : ''; ?>">Contracts</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Attendance -->
                <?php if (showMenu($auth, 'attendance')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'attendance') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-calendar-check"></i>
                        <span>Attendance</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'attendance_add')): ?>
                        <li><a href="index.php?page=attendance/add" class="<?php echo $page == 'attendance/add' ? 'active' : ''; ?>">Add Attendance</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'attendance_upload')): ?>
                        <li><a href="index.php?page=attendance/upload" class="<?php echo $page == 'attendance/upload' ? 'active' : ''; ?>">Upload Attendance</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'attendance_view')): ?>
                        <li><a href="index.php?page=attendance/view" class="<?php echo $page == 'attendance/view' ? 'active' : ''; ?>">View Attendance</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'attendance_report')): ?>
                        <li><a href="index.php?page=attendance/report" class="<?php echo $page == 'attendance/report' ? 'active' : ''; ?>">Attendance Report</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Advance -->
                <?php if (showMenu($auth, 'advance')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'advance') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-wallet2"></i>
                        <span>Advance</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'advance_add')): ?>
                        <li><a href="index.php?page=advance/add" class="<?php echo $page == 'advance/add' ? 'active' : ''; ?>">Add Advance</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Payroll -->
                <?php if (showMenu($auth, 'payroll')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'payroll') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-cash-stack"></i>
                        <span>Payroll</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'payroll_process')): ?>
                        <li><a href="index.php?page=payroll/process" class="<?php echo $page == 'payroll/process' ? 'active' : ''; ?>">Process Payroll</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'payroll_view')): ?>
                        <li><a href="index.php?page=payroll/view" class="<?php echo $page == 'payroll/view' ? 'active' : ''; ?>">View Payroll</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'payroll_salary_revision')): ?>
                        <li><a href="index.php?page=payroll/salary-revision" class="<?php echo $page == 'payroll/salary-revision' ? 'active' : ''; ?>">Salary Revision</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'payroll_payslips')): ?>
                        <li><a href="index.php?page=payroll/payslips" class="<?php echo $page == 'payroll/payslips' ? 'active' : ''; ?>">Payslips</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'payroll_bank_advice')): ?>
                        <li><a href="index.php?page=payroll/bank-advice" class="<?php echo $page == 'payroll/bank-advice' ? 'active' : ''; ?>">Bank Advice</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Compliance -->
                <?php if (showMenu($auth, 'compliance')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'compliance') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-shield-check"></i>
                        <span>Compliance</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'compliance_dashboard')): ?>
                        <li><a href="index.php?page=compliance/dashboard" class="<?php echo $page == 'compliance/dashboard' ? 'active' : ''; ?>">Compliance Dashboard</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'compliance_pf')): ?>
                        <li><a href="index.php?page=compliance/pf" class="<?php echo $page == 'compliance/pf' ? 'active' : ''; ?>">PF ECR Generator</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'compliance_esi')): ?>
                        <li><a href="index.php?page=compliance/esi" class="<?php echo $page == 'compliance/esi' ? 'active' : ''; ?>">ESI Returns</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'compliance_pt')): ?>
                        <li><a href="index.php?page=compliance/pt" class="<?php echo $page == 'compliance/pt' ? 'active' : ''; ?>">PT Returns</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'compliance_minimum_wages')): ?>
                        <li><a href="index.php?page=compliance/minimum-wages" class="<?php echo $page == 'compliance/minimum-wages' ? 'active' : ''; ?>">Minimum Wages</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'compliance_mw_validation')): ?>
                        <li><a href="index.php?page=compliance/minimum-wage-check" class="<?php echo $page == 'compliance/minimum-wage-check' ? 'active' : ''; ?>">MW Validation</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Forms -->
                <?php if (showMenu($auth, 'forms')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'forms') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Forms</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'forms_appointment')): ?>
                        <li><a href="index.php?page=forms/appointment" class="<?php echo $page == 'forms/appointment' ? 'active' : ''; ?>">Appointment Letter</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'forms_form_v')): ?>
                        <li><a href="index.php?page=forms/form-v" class="<?php echo $page == 'forms/form-v' ? 'active' : ''; ?>">Form V</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'forms_form_xvi')): ?>
                        <li><a href="index.php?page=forms/form-xvi" class="<?php echo $page == 'forms/form-xvi' ? 'active' : ''; ?>">Form XVI</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'forms_form_xvii')): ?>
                        <li><a href="index.php?page=forms/form-xvii" class="<?php echo $page == 'forms/form-xvii' ? 'active' : ''; ?>">Form XVII</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'forms_form_f2')): ?>
                        <li><a href="index.php?page=forms/form-f2" class="<?php echo $page == 'forms/form-f2' ? 'active' : ''; ?>">Form F2</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'forms_nomination')): ?>
                        <li><a href="index.php?page=forms/nomination" class="<?php echo $page == 'forms/nomination' ? 'active' : ''; ?>">Nomination Forms</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Deployments -->
                <?php if (showMenu($auth, 'deployment')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'deployment') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-geo-alt"></i>
                        <span>Deployments</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'deployment_list')): ?>
                        <li><a href="index.php?page=deployment/list" class="<?php echo $page == 'deployment/list' ? 'active' : ''; ?>">All Deployments</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'deployment_add')): ?>
                        <li><a href="index.php?page=deployment/add" class="<?php echo $page == 'deployment/add' ? 'active' : ''; ?>">New Deployment</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Requisitions -->
                <?php if (showMenu($auth, 'requisition')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'requisition') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-person-plus"></i>
                        <span>Requisitions</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'requisition_list')): ?>
                        <li><a href="index.php?page=requisition/list" class="<?php echo $page == 'requisition/list' ? 'active' : ''; ?>">All Requisitions</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'requisition_add')): ?>
                        <li><a href="index.php?page=requisition/add" class="<?php echo $page == 'requisition/add' ? 'active' : ''; ?>">New Requisition</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Recruitment -->
                <?php if (showMenu($auth, 'recruitment')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'recruitment') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-person-lines-fill"></i>
                        <span>Recruitment</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'recruitment_list')): ?>
                        <li><a href="index.php?page=recruitment/list" class="<?php echo $page == 'recruitment/list' ? 'active' : ''; ?>">All Candidates</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'recruitment_add')): ?>
                        <li><a href="index.php?page=recruitment/add" class="<?php echo $page == 'recruitment/add' ? 'active' : ''; ?>">Add Candidate</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Billing -->
                <?php if (showMenu($auth, 'billing')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'billing') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-receipt"></i>
                        <span>Billing</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'billing_list')): ?>
                        <li><a href="index.php?page=billing/list" class="<?php echo $page == 'billing/list' ? 'active' : ''; ?>">Invoices</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'billing_gst_invoice')): ?>
                        <li><a href="index.php?page=billing/gst-invoice" class="<?php echo $page == 'billing/gst-invoice' ? 'active' : ''; ?>">GST Invoice</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Bulk Upload -->
                <?php if (in_array($_SESSION['role_code'] ?? '', ['admin', 'hr_executive'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'bulk-upload') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <span>Bulk Upload</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=bulk-upload/salary" class="<?php echo $page == 'bulk-upload/salary' ? 'active' : ''; ?>">Salary Upload</a></li>
                        <li><a href="index.php?page=attendance/upload" class="<?php echo $page == 'attendance/upload' ? 'active' : ''; ?>">Attendance Upload</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Timesheets -->
                <?php if (showMenu($auth, 'timesheet')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'timesheet') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-table"></i>
                        <span>Timesheets</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'timesheet_list')): ?>
                        <li><a href="index.php?page=timesheet/list" class="<?php echo $page == 'timesheet/list' ? 'active' : ''; ?>">All Timesheets</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'timesheet_create')): ?>
                        <li><a href="index.php?page=timesheet/create" class="<?php echo $page == 'timesheet/create' ? 'active' : ''; ?>">Create Timesheet</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Client Feedback -->
                <?php if (showMenu($auth, 'feedback')): ?>
                <li class="sidebar-item <?php echo $page == 'feedback/list' ? 'active' : ''; ?>">
                    <a href="index.php?page=feedback/list" class="sidebar-link">
                        <i class="bi bi-star"></i>
                        <span>Client Feedback</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Assets -->
                <?php if (showMenu($auth, 'assets')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'assets') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-box-seam"></i>
                        <span>Assets</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'assets_list')): ?>
                        <li><a href="index.php?page=assets/list" class="<?php echo $page == 'assets/list' ? 'active' : ''; ?>">All Assets</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'assets_issue')): ?>
                        <li><a href="index.php?page=assets/issue" class="<?php echo $page == 'assets/issue' ? 'active' : ''; ?>">Issue Asset</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Helpdesk -->
                <?php if (showMenu($auth, 'helpdesk')): ?>
                <li class="sidebar-item <?php echo strpos($page, 'helpdesk') === 0 ? 'active' : ''; ?>">
                    <a href="index.php?page=helpdesk/list" class="sidebar-link">
                        <i class="bi bi-headset"></i>
                        <span>Helpdesk</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Leave Management -->
                <?php if (showMenu($auth, 'leave')): ?>
                <li class="sidebar-item <?php echo strpos($page, 'leave') === 0 ? 'active' : ''; ?>">
                    <a href="index.php?page=leave/balance" class="sidebar-link">
                        <i class="bi bi-calendar-x"></i>
                        <span>Leave Management</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Reports -->
                <?php if (showMenu($auth, 'report')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'report') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-bar-chart-line"></i>
                        <span>Reports</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'report_employee')): ?>
                        <li><a href="index.php?page=report/employee" class="<?php echo $page == 'report/employee' ? 'active' : ''; ?>">Employee Reports</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'report_attendance')): ?>
                        <li><a href="index.php?page=report/attendance" class="<?php echo $page == 'report/attendance' ? 'active' : ''; ?>">Attendance Reports</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'report_payroll')): ?>
                        <li><a href="index.php?page=report/payroll" class="<?php echo $page == 'report/payroll' ? 'active' : ''; ?>">Payroll Reports</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'report_compliance')): ?>
                        <li><a href="index.php?page=report/compliance" class="<?php echo $page == 'report/compliance' ? 'active' : ''; ?>">Compliance Reports</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'report_custom')): ?>
                        <li><a href="index.php?page=report/custom" class="<?php echo $page == 'report/custom' ? 'active' : ''; ?>">Custom Report Builder</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Settlement (F&F) -->
                <?php if (showMenu($auth, 'settlement')): ?>
                <li class="sidebar-item <?php echo strpos($page, 'settlement') === 0 ? 'active' : ''; ?>">
                    <a href="index.php?page=settlement/list" class="sidebar-link">
                        <i class="bi bi-cash-coin"></i>
                        <span>F&F Settlement</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Notifications -->
                <?php if (showMenu($auth, 'notifications')): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'notifications') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-bell"></i>
                        <span>Notifications</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=notifications" class="<?php echo $page == 'notifications' ? 'active' : ''; ?>">View Notifications</a></li>
                        <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive'])): ?>
                        <li><a href="index.php?page=notifications/center" class="<?php echo $page == 'notifications/center' ? 'active' : ''; ?>">Notification Center</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Settings - Admin Only -->
                <?php if (showMenu($auth, 'settings') && $_SESSION['role_code'] === 'admin'): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'settings') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <?php if (showSubmenu($auth, 'settings_company')): ?>
                        <li><a href="index.php?page=settings/company" class="<?php echo $page == 'settings/company' ? 'active' : ''; ?>">Company</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'settings_users')): ?>
                        <li><a href="index.php?page=settings/users" class="<?php echo $page == 'settings/users' ? 'active' : ''; ?>">Users</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'settings_roles')): ?>
                        <li><a href="index.php?page=settings/roles" class="<?php echo $page == 'settings/roles' ? 'active' : ''; ?>">Roles</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'settings_menu_permissions')): ?>
                        <li><a href="index.php?page=settings/menu-permissions" class="<?php echo $page == 'settings/menu-permissions' ? 'active' : ''; ?>">
                            <i class="bi bi-check2-square me-1"></i>Menu Permissions
                        </a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'settings_payslip_templates')): ?>
                        <li><a href="index.php?page=settings/payslip-templates" class="<?php echo $page == 'settings/payslip-templates' ? 'active' : ''; ?>">Payslip Templates</a></li>
                        <?php endif; ?>
                        <?php if (showSubmenu($auth, 'settings_statutory')): ?>
                        <li><a href="index.php?page=settings/statutory" class="<?php echo $page == 'settings/statutory' ? 'active' : ''; ?>">Statutory Rates</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <div class="sidebar-footer-version" style="font-size:11px; line-height:1.4;">
                <?php
                // Try multiple possible locations for build-info.txt
                $file = APP_ROOT . '/build-info.txt';
                if (!file_exists($file)) {
                    $file = dirname(__DIR__) . '/build-info.txt';
                }
                if (!file_exists($file)) {
                    $file = $_SERVER['DOCUMENT_ROOT'] . '/hrms/build-info.txt';
                }

                if (file_exists($file)) {
                    $lines = file($file);
                    echo htmlspecialchars(trim($lines[0])) . "<br>"; // Version
                    echo htmlspecialchars(trim($lines[1])); // Last Update
                } else {
                    echo "Version 2.3.0<br>Menu Permissions Enhanced";
                }
                ?>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main id="main-content" class="main-content">
        <!-- Top Navbar -->
        <nav class="topbar">
            <div class="topbar-left">
                <button id="sidebar-toggle" class="sidebar-toggle">
                    <i class="bi bi-list"></i>
                </button>
                <nav aria-label="breadcrumb" class="topbar-breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active"><?php echo $pageTitle ?? 'Dashboard'; ?></li>
                    </ol>
                </nav>
            </div>
            
            <div class="topbar-right">
                <!-- Language Selector -->
                <div class="topbar-item dropdown">
                    <a href="#" class="topbar-link" data-bs-toggle="dropdown">
                        <i class="bi bi-translate"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?lang=en">English</a></li>
                        <li><a class="dropdown-item" href="?lang=hi">हिंदी</a></li>
                    </ul>
                </div>
                
                <!-- Pending Employees Alert -->
                <?php 
                // Get pending employees count
                try {
                    $pendingStmt = $db->query("SELECT COUNT(*) as count FROM employees WHERE status LIKE 'pending%'");
                    $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];
                    if ($pendingCount > 0):
                ?>
                <div class="topbar-item">
                    <a href="index.php?page=employee/list&status=pending" class="topbar-link text-warning" title="Pending Employee Approvals">
                        <i class="bi bi-person-plus-fill"></i>
                        <span class="badge bg-warning text-dark"><?php echo $pendingCount; ?></span>
                    </a>
                </div>
                <?php 
                    endif;
                } catch (Exception $e) {
                    // Table may not exist yet
                }
                ?>
                
                <!-- Notifications -->
                <div class="topbar-item dropdown">
                    <a href="#" class="topbar-link" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php 
                        // Get notification count + pending employees
                        $totalNotifCount = 0;
                        try {
                            $notifStmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                            $notifStmt->execute([$_SESSION['user_id']]);
                            $totalNotifCount = (int)$notifStmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            // Add pending employees count
                            $pendingEmpStmt = $db->query("SELECT COUNT(*) FROM employees WHERE status LIKE 'pending%'");
                            $pendingEmpCount = $pendingEmpStmt ? (int)$pendingEmpStmt->fetchColumn() : 0;
                            $totalNotifCount += $pendingEmpCount;
                        } catch (Exception $e) {}
                        
                        if ($totalNotifCount > 0):
                        ?>
                            <span class="notification-badge"><?php echo $totalNotifCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="min-width: 320px; max-height: 400px; overflow-y: auto;">
                        <h6 class="dropdown-header">Notifications</h6>
                        
                        <?php
                        // Show pending employees first
                        try {
                            $pendingEmpStmt = $db->query("SELECT id, full_name, employee_code, created_at 
                                 FROM employees 
                                 WHERE status LIKE 'pending%' 
                                 ORDER BY created_at DESC LIMIT 5");
                            $pendingEmps = $pendingEmpStmt ? $pendingEmpStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                            
                            foreach ($pendingEmps as $emp):
                        ?>
                        <a href="index.php?page=employee/view&id=<?php echo $emp['id']; ?>" class="dropdown-item notification-item unread">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-person-plus text-warning me-2"></i>
                                <div>
                                    <div class="notification-title">
                                        <strong><?php echo sanitize($emp['full_name']); ?></strong>
                                        <span class="badge bg-warning text-dark ms-1">Pending</span>
                                    </div>
                                    <div class="notification-time small text-muted">
                                        <?php echo sanitize($emp['employee_code']); ?> - Awaiting approval
                                    </div>
                                </div>
                            </div>
                        </a>
                        <?php 
                            endforeach;
                        } catch (Exception $e) {}
                        
                        // Show other notifications
                        try {
                            $notifStmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                            $notifStmt->execute([$_SESSION['user_id']]);
                            $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($notifications as $notif):
                        ?>
                        <a href="<?php echo $notif['link'] ?? '#'; ?>" class="dropdown-item notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-title"><?php echo sanitize($notif['title']); ?></div>
                            <div class="notification-time"><?php echo date('d M H:i', strtotime($notif['created_at'])); ?></div>
                        </a>
                        <?php 
                            endforeach;
                        } catch (Exception $e) {}
                        
                        // If no notifications at all
                        if (empty($pendingEmps) && empty($notifications)):
                        ?>
                            <div class="dropdown-item text-muted text-center py-3">
                                <i class="bi bi-bell-slash d-block fs-4 mb-2"></i>
                                No new notifications
                            </div>
                        <?php endif; ?>
                        
                        <div class="dropdown-divider"></div>
                        <a href="index.php?page=notifications" class="dropdown-item text-center text-primary">
                            <i class="bi bi-eye me-1"></i>View All Notifications
                        </a>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="topbar-item dropdown">
                    <a href="#" class="topbar-link user-menu" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php echo substr($_SESSION['first_name'] ?? 'U', 0, 1); ?>
                        </div>
                        <span class="user-name d-none d-md-inline">
                            <?php echo sanitize($_SESSION['first_name'] ?? 'User'); ?>
                        </span>
                        <i class="bi bi-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <div class="user-info">
                                <strong><?php echo sanitize($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>
                                <small><?php echo sanitize($_SESSION['role_name']); ?></small>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?page=profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="index.php?page=profile/settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="index.php?page=auth/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="page-content">
    <?php else: ?>
    <!-- Non-authenticated page content -->
    <div class="login-wrapper">
    <?php endif; ?>
    
    <!-- Flash Messages -->
    <?php 
    $flash = getFlash();
    if ($flash):
    ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo sanitize($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
