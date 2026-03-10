<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RCS HRMS Pro - Human Resource Management System for Labour Contractors">
    <meta name="author" content="RCS TRUE FACILITIES PVT LTD">
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>RCS HRMS Pro</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
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
    <link href="assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($extraCSS)) echo $extraCSS; ?>
</head>
<body class="<?php echo $isLoggedIn ? '' : 'login-page'; ?>">
    
    <?php if ($isLoggedIn): ?>
    <!-- Sidebar -->
    <nav id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <img src="assets/images/logo.png" alt="RCS HRMS" class="sidebar-logo">
                <span class="sidebar-brand-text">RCS HRMS Pro</span>
            </a>
            <button type="button" class="sidebar-close d-lg-none" id="sidebar-close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-body">
            <ul class="sidebar-nav">
                <!-- Dashboard -->
                <li class="sidebar-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                    <a href="index.php?page=dashboard" class="sidebar-link">
                        <i class="bi bi-grid-1x2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <!-- Employees -->
                <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive', 'manager', 'supervisor'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'employee') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-people"></i>
                        <span>Employees</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=employee/list" class="<?php echo $page == 'employee/list' ? 'active' : ''; ?>">All Employees</a></li>
                        <li><a href="index.php?page=employee/add" class="<?php echo $page == 'employee/add' ? 'active' : ''; ?>">Add Employee</a></li>
                        <li><a href="index.php?page=employee/import" class="<?php echo $page == 'employee/import' ? 'active' : ''; ?>">Import Employees</a></li>
                        <li><a href="index.php?page=employee/documents" class="<?php echo $page == 'employee/documents' ? 'active' : ''; ?>">Documents</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Clients & Units -->
                <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'client') === 0 || strpos($page, 'unit') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-building"></i>
                        <span>Clients & Units</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=client/list" class="<?php echo $page == 'client/list' ? 'active' : ''; ?>">Clients</a></li>
                        <li><a href="index.php?page=unit/list" class="<?php echo $page == 'unit/list' ? 'active' : ''; ?>">Units</a></li>
                        <li><a href="index.php?page=contract/list" class="<?php echo $page == 'contract/list' ? 'active' : ''; ?>">Contracts</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Attendance -->
                <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive', 'manager', 'supervisor'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'attendance') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-calendar-check"></i>
                        <span>Attendance</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=attendance/add" class="<?php echo $page == 'attendance/add' ? 'active' : ''; ?>">Add Attendance</a></li>
                        <li><a href="index.php?page=attendance/upload" class="<?php echo $page == 'attendance/upload' ? 'active' : ''; ?>">Upload Attendance</a></li>
                        <li><a href="index.php?page=attendance/view" class="<?php echo $page == 'attendance/view' ? 'active' : ''; ?>">View Attendance</a></li>
                        <li><a href="index.php?page=attendance/report" class="<?php echo $page == 'attendance/report' ? 'active' : ''; ?>">Attendance Report</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Advance -->
                <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive', 'manager'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'advance') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-wallet2"></i>
                        <span>Advance</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=advance/add" class="<?php echo $page == 'advance/add' ? 'active' : ''; ?>">Add Advance</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Payroll -->
                <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive', 'manager'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'payroll') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-cash-stack"></i>
                        <span>Payroll</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=payroll/process" class="<?php echo $page == 'payroll/process' ? 'active' : ''; ?>">Process Payroll</a></li>
                        <li><a href="index.php?page=payroll/view" class="<?php echo $page == 'payroll/view' ? 'active' : ''; ?>">View Payroll</a></li>
                        <li><a href="index.php?page=payroll/payslips" class="<?php echo $page == 'payroll/payslips' ? 'active' : ''; ?>">Payslips</a></li>
                        <li><a href="index.php?page=payroll/bank-advice" class="<?php echo $page == 'payroll/bank-advice' ? 'active' : ''; ?>">Bank Advice</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Compliance -->
                <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'compliance') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-shield-check"></i>
                        <span>Compliance</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=compliance/dashboard" class="<?php echo $page == 'compliance/dashboard' ? 'active' : ''; ?>">Compliance Dashboard</a></li>
                        <li><a href="index.php?page=compliance/pf" class="<?php echo $page == 'compliance/pf' ? 'active' : ''; ?>">PF Returns</a></li>
                        <li><a href="index.php?page=compliance/esi" class="<?php echo $page == 'compliance/esi' ? 'active' : ''; ?>">ESI Returns</a></li>
                        <li><a href="index.php?page=compliance/pt" class="<?php echo $page == 'compliance/pt' ? 'active' : ''; ?>">PT Returns</a></li>
                        <li><a href="index.php?page=compliance/minimum-wages" class="<?php echo $page == 'compliance/minimum-wages' ? 'active' : ''; ?>">Minimum Wages</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Forms -->
                <?php if (in_array($_SESSION['role_code'], ['admin', 'hr_executive'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'forms') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Forms</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=forms/appointment" class="<?php echo $page == 'forms/appointment' ? 'active' : ''; ?>">Appointment Letter</a></li>
                        <li><a href="index.php?page=forms/form-v" class="<?php echo $page == 'forms/form-v' ? 'active' : ''; ?>">Form V</a></li>
                        <li><a href="index.php?page=forms/form-xvi" class="<?php echo $page == 'forms/form-xvi' ? 'active' : ''; ?>">Form XVI</a></li>
                        <li><a href="index.php?page=forms/form-xvii" class="<?php echo $page == 'forms/form-xvii' ? 'active' : ''; ?>">Form XVII</a></li>
                        <li><a href="index.php?page=forms/form-f2" class="<?php echo $page == 'forms/form-f2' ? 'active' : ''; ?>">Form F2</a></li>
                        <li><a href="index.php?page=forms/nomination" class="<?php echo $page == 'forms/nomination' ? 'active' : ''; ?>">Nomination Forms</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Reports -->
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'report') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-bar-chart-line"></i>
                        <span>Reports</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=report/employee" class="<?php echo $page == 'report/employee' ? 'active' : ''; ?>">Employee Reports</a></li>
                        <li><a href="index.php?page=report/attendance" class="<?php echo $page == 'report/attendance' ? 'active' : ''; ?>">Attendance Reports</a></li>
                        <li><a href="index.php?page=report/payroll" class="<?php echo $page == 'report/payroll' ? 'active' : ''; ?>">Payroll Reports</a></li>
                        <li><a href="index.php?page=report/compliance" class="<?php echo $page == 'report/compliance' ? 'active' : ''; ?>">Compliance Reports</a></li>
                        <li><a href="index.php?page=report/custom" class="<?php echo $page == 'report/custom' ? 'active' : ''; ?>">Custom Report Builder</a></li>
                    </ul>
                </li>
                
                <!-- Settings -->
                <?php if (in_array($_SESSION['role_code'], ['admin'])): ?>
                <li class="sidebar-item has-submenu <?php echo strpos($page, 'settings') === 0 ? 'open' : ''; ?>">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                        <i class="bi bi-chevron-down sidebar-arrow"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="index.php?page=settings/company" class="<?php echo $page == 'settings/company' ? 'active' : ''; ?>">Company</a></li>
                        <li><a href="index.php?page=settings/users" class="<?php echo $page == 'settings/users' ? 'active' : ''; ?>">Users</a></li>
                        <li><a href="index.php?page=settings/roles" class="<?php echo $page == 'settings/roles' ? 'active' : ''; ?>">Roles</a></li>
                        <li><a href="index.php?page=settings/payslip-templates" class="<?php echo $page == 'settings/payslip-templates' ? 'active' : ''; ?>">Payslip Templates</a></li>
                        <li><a href="index.php?page=settings/statutory" class="<?php echo $page == 'settings/statutory' ? 'active' : ''; ?>">Statutory Rates</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <?php
            // Get last update time - try git first, then file modification
            $lastUpdate = '';
            $gitDir = dirname(__FILE__) . '/../.git';
            if (is_dir($gitDir)) {
                $gitLog = @shell_exec('cd ' . escapeshellarg(dirname(__FILE__) . '/..') . ' && git log -1 --format="%ci" 2>/dev/null');
                if ($gitLog) {
                    $lastUpdate = trim($gitLog);
                }
            }
            if (empty($lastUpdate)) {
                // Fallback to current file modification
                $lastUpdate = date('Y-m-d H:i:s', filemtime(__FILE__));
            }
            ?>
            <div class="sidebar-footer-version">Version 1.2.0</div>
            <div class="sidebar-footer-version" style="font-size: 10px; opacity: 0.8;">Last Update: <?php echo date('d M Y H:i', strtotime($lastUpdate)); ?></div>
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
                
                <!-- Notifications -->
                <div class="topbar-item dropdown">
                    <a href="#" class="topbar-link" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php 
                        // Get notification count
                        $notifStmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $notifStmt->execute([$_SESSION['user_id']]);
                        $notifCount = $notifStmt->fetch(PDO::FETCH_ASSOC)['count'];
                        if ($notifCount > 0):
                        ?>
                        <span class="notification-badge"><?php echo $notifCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <h6 class="dropdown-header">Notifications</h6>
                        <?php
                        $notifStmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                        $notifStmt->execute([$_SESSION['user_id']]);
                        $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($notifications)):
                        ?>
                        <div class="dropdown-item text-muted">No notifications</div>
                        <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                        <a href="<?php echo $notif['link'] ?? '#'; ?>" class="dropdown-item notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-title"><?php echo sanitize($notif['title']); ?></div>
                            <div class="notification-time"><?php echo date('d M H:i', strtotime($notif['created_at'])); ?></div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="index.php?page=notifications" class="dropdown-item text-center">View All</a>
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
