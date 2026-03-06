<?php
/**
 * RCS HRMS Pro - Main Entry Point
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

// Get requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : null;

// Handle AJAX requests
if (isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if (!$isLoggedIn && $page !== 'auth/login') {
        echo json_encode(array('error' => 'Authentication required', 'redirect' => 'index.php?page=auth/login'));
        exit;
    }
    
    $ajaxFile = dirname(__FILE__) . "/modules/{$page}/ajax.php";
    if (file_exists($ajaxFile)) {
        include $ajaxFile;
    } else {
        echo json_encode(array('error' => 'Invalid request'));
    }
    exit;
}

// Handle API requests
if (strpos($page, 'api/') === 0) {
    header('Content-Type: application/json');
    $apiFile = dirname(__FILE__) . "/modules/{$page}.php";
    if (file_exists($apiFile)) {
        include $apiFile;
    } else {
        http_response_code(404);
        echo json_encode(array('error' => 'API endpoint not found'));
    }
    exit;
}

// Route to appropriate page
if (!$isLoggedIn) {
    $allowedPages = array('auth/login', 'auth/forgot-password', 'auth/reset-password');
    
    if (!in_array($page, $allowedPages)) {
        header("Location: index.php?page=auth/login");
        exit;
    }
} else {
    $pageParts = explode('/', $page);
    $module = isset($pageParts[0]) ? $pageParts[0] : '';
    
    $moduleAccess = array(
        'admin' => array('all'),
        'hr_executive' => array('dashboard', 'employees', 'attendance', 'payroll', 'compliance', 'reports', 'settings', 'profile'),
        'manager' => array('dashboard', 'employees', 'attendance', 'payroll', 'reports', 'profile'),
        'supervisor' => array('dashboard', 'employees', 'attendance', 'profile'),
        'worker' => array('dashboard', 'profile', 'payslips')
    );
    
    $roleCode = isset($_SESSION['role_code']) ? $_SESSION['role_code'] : '';
    $allowedModules = isset($moduleAccess[$roleCode]) ? $moduleAccess[$roleCode] : array('dashboard', 'profile');
    
    if (!in_array('all', $allowedModules) && !in_array($module, $allowedModules)) {
        $_SESSION['flash'] = array('type' => 'error', 'message' => 'You do not have access to this module.');
        header("Location: index.php?page=dashboard");
        exit;
    }
}

// Include header template
include dirname(__FILE__) . '/templates/header.php';

// Include page content
$pageFile = dirname(__FILE__) . "/modules/{$page}.php";
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    // Default to dashboard if page not found
    include dirname(__FILE__) . '/modules/dashboard/index.php';
}

// Include footer template
include dirname(__FILE__) . '/templates/footer.php';
