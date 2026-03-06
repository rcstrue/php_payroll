<?php
/**
 * RCS HRMS Pro - Configuration File
 * Company: RCS TRUE FACILITIES PVT LTD
 * 
 * This file loads database credentials from config.local.php
 * config.local.php is NOT tracked in git - your credentials are safe
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load local configuration if exists (this file is NOT in git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Database Settings (set defaults if not defined in config.local.php)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'rcs_hrms');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Application Settings
if (!defined('APP_NAME')) define('APP_NAME', 'RCS HRMS Pro');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) define('APP_URL', '');

// Session Settings
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'rcs_hrms_session');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 7200); // 2 hours

// Pagination
if (!defined('RECORDS_PER_PAGE')) define('RECORDS_PER_PAGE', 50);

// Date Formats
if (!defined('DATE_FORMAT')) define('DATE_FORMAT', 'd-m-Y');
if (!defined('DATETIME_FORMAT')) define('DATETIME_FORMAT', 'd-m-Y H:i:s');

// File Upload Settings
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', APP_ROOT . '/uploads/');

// API Settings
if (!defined('API_TIMEOUT')) define('API_TIMEOUT', 30);

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Autoload Classes
spl_autoload_register(function ($class) {
    // First check includes/class.{name}.php
    $classFile = APP_ROOT . '/includes/class.' . strtolower($class) . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
        return;
    }
    // Then check classes/{Name}.php
    $classFile = APP_ROOT . '/classes/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Helper Functions
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') return '-';
    return date(DATE_FORMAT, strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date(DATETIME_FORMAT, strtotime($datetime));
}

function formatCurrency($amount) {
    return '₹' . number_format($amount ?? 0, 2);
}

function maskAadhaar($aadhaar) {
    if (empty($aadhaar) || strlen($aadhaar) < 4) return $aadhaar;
    return 'XXXX-XXXX-' . substr($aadhaar, -4);
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
    }
    exit;
}

function logActivity($action, $module, $recordId, $description) {
    global $db;
    if (isset($db)) {
        $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, module, record_id, new_values, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'] ?? null, $action, $module, $recordId, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}

// Password Helper Functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}
