<?php
/**
 * RCS HRMS Pro - Main Entry Point
 * Security: User inputs are sanitized and validated before use
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define application constant
define('RCS_HRMS', true);

// Include configuration (this also starts session and has autoloader)
require_once dirname(__FILE__) . '/config/config.php';

// Include database connection
require_once dirname(__FILE__) . '/includes/database.php';

// Initialize all classes
try {
    $auth = new Auth();
    $employee = new Employee();
    $attendance = new Attendance();
    $payroll = new Payroll();
    $compliance = new Compliance();
    $client = new Client();
    $unit = new Unit();
} catch (Exception $e) {
    die("Error initializing application: " . $e->getMessage());
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

/**
 * Sanitize page parameter to prevent path traversal and injection attacks
 * @param string $page The page parameter to sanitize
 * @return string|null Sanitized page or null if invalid
 */
function sanitizePageParam($page) {
    if (empty($page)) {
        return null;
    }
    
    // Remove any null bytes
    $page = str_replace("\0", '', $page);
    
    // Only allow alphanumeric characters, forward slash, underscore, and hyphen
    if (!preg_match('/^[a-zA-Z0-9\/_-]+$/', $page)) {
        return null;
    }
    
    // Prevent path traversal
    if (strpos($page, '..') !== false || strpos($page, '//') !== false) {
        return null;
    }
    
    // Must start with a letter
    if (!preg_match('/^[a-zA-Z]/', $page)) {
        return null;
    }
    
    return $page;
}

/**
 * Validate and get safe file path for module
 * @param string $page The sanitized page parameter
 * @return string|null Safe file path or null if invalid
 */
function getSafeModulePath($page) {
    if ($page === null) {
        return null;
    }
    
    // Define allowed modules (whitelist)
    $allowedModules = [
        'dashboard', 'auth', 'employee', 'attendance', 'payroll', 'compliance',
        'report', 'settings', 'profile', 'client', 'unit', 'forms', 'helpdesk',
        'assets', 'recruitment', 'billing', 'ratecard', 'contract', 'deployment',
        'announcement', 'requisition', 'advance', 'timesheet', 'leave', 'settlement',
        'audit', 'notifications', 'portal', 'api'
    ];
    
    // Extract module name
    $pageParts = explode('/', $page);
    $module = isset($pageParts[0]) ? $pageParts[0] : '';
    
    // Check if module is allowed
    if (!in_array($module, $allowedModules)) {
        return null;
    }
    
    // Build the file path
    $basePath = dirname(__FILE__) . '/modules/';
    $filePath = $basePath . $page . '.php';
    
    // Resolve the real path and verify it's within the modules directory
    $realPath = realpath($basePath);
    $resolvedPath = realpath(dirname($filePath));
    
    if ($resolvedPath === false || strpos($resolvedPath, $realPath) !== 0) {
        return null;
    }
    
    // Check if file exists
    if (!file_exists($filePath)) {
        return null;
    }
    
    return $filePath;
}

// Get and sanitize requested page
$rawPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$page = sanitizePageParam($rawPage);

// If page is invalid, redirect to dashboard
if ($page === null) {
    if ($isLoggedIn) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid page request.'];
        header("Location: index.php?page=dashboard");
    } else {
        header("Location: index.php?page=auth/login");
    }
    exit;
}

$action = isset($_GET['action']) ? sanitizePageParam($_GET['action']) : null;

// Handle AJAX requests
if (isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if (!$isLoggedIn && $page !== 'auth/login') {
        echo json_encode(['error' => 'Authentication required', 'redirect' => 'index.php?page=auth/login']);
        exit;
    }
    
    $ajaxFile = dirname(__FILE__) . "/modules/{$page}/ajax.php";
    $ajaxFile = getSafeModulePath($page . '/ajax');
    
    if ($ajaxFile !== null && file_exists(dirname(__FILE__) . "/modules/{$page}/ajax.php")) {
        include dirname(__FILE__) . "/modules/{$page}/ajax.php";
    } else {
        echo json_encode(['error' => 'Invalid request']);
    }
    exit;
}

// Handle API requests
if (strpos($page, 'api/') === 0) {
    header('Content-Type: application/json');
    
    // Validate API path
    $apiPath = getSafeModulePath($page);
    if ($apiPath !== null) {
        include $apiPath;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
    }
    exit;
}

// Handle Export requests (before header is included to avoid "headers already sent" errors)
if (isset($_GET['export']) && $isLoggedIn) {
    $exportPath = getSafeModulePath($page);
    if ($exportPath !== null) {
        $isExportRequest = true;
        include $exportPath;
    }
    exit;
}

// Handle Delete/Remove requests (before header is included)
if (strpos($page, '/delete') !== false && $isLoggedIn) {
    $deletePath = getSafeModulePath($page);
    if ($deletePath !== null) {
        include $deletePath;
    }
    exit;
}

// Route to appropriate page
if (!$isLoggedIn) {
    $allowedPages = ['auth/login', 'auth/forgot-password', 'auth/reset-password'];
    
    if (!in_array($page, $allowedPages)) {
        header("Location: index.php?page=auth/login");
        exit;
    }
} else {
    $pageParts = explode('/', $page);
    $module = isset($pageParts[0]) ? $pageParts[0] : '';
    
    $moduleAccess = [
        'admin' => ['all'],
        'hr_executive' => ['dashboard', 'employees', 'attendance', 'payroll', 'compliance', 'reports', 'settings', 'profile', 'auth'],
        'hr' => ['dashboard', 'employees', 'attendance', 'payroll', 'compliance', 'reports', 'settings', 'profile', 'auth'],
        'manager' => ['dashboard', 'employees', 'attendance', 'payroll', 'reports', 'profile', 'auth'],
        'supervisor' => ['dashboard', 'employees', 'attendance', 'profile', 'auth'],
        'worker' => ['dashboard', 'profile', 'payslips', 'auth']
    ];
    
    $roleCode = isset($_SESSION['role_code']) ? $_SESSION['role_code'] : '';
    $allowedModules = isset($moduleAccess[$roleCode]) ? $moduleAccess[$roleCode] : ['dashboard', 'profile', 'auth'];
    
    // Always allow auth module (for logout, password change, etc.)
    if (!in_array('all', $allowedModules) && !in_array('auth', $allowedModules)) {
        $allowedModules[] = 'auth';
    }
    
    if (!in_array('all', $allowedModules) && !in_array($module, $allowedModules)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'You do not have access to this module.'];
        header("Location: index.php?page=dashboard");
        exit;
    }
}

// Include header template
include dirname(__FILE__) . '/templates/header.php';

// Include page content with validated path
$pagePath = getSafeModulePath($page);
if ($pagePath !== null) {
    include $pagePath;
} else {
    // Default to dashboard if page not found
    include dirname(__FILE__) . '/modules/dashboard/index.php';
}

// Include footer template
include dirname(__FILE__) . '/templates/footer.php';
