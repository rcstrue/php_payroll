<?php
/**
 * RCS HRMS Pro - Main API Endpoint
 * All API requests are routed through this file
 * Updated for new database schema
 * Supports both Session and API Key authentication
 */

// CORS Headers
// NOTE: Permissive CORS policy (*) is used for development. For production,
// configure ALLOWED_ORIGINS in config.php to restrict access.
$allowedOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : '*';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . $allowedOrigins);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Client-Info, X-API-KEY, apikey');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$request = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Check authentication - either API Key or Session
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_APIKEY'] ?? '';
$useApiKey = !empty($apiKey) && isValidApiKey($apiKey);

// Initialize authentication
$auth = new Auth();

// Check if user is authenticated (via session OR API key)
if (!$useApiKey && !$auth->isLoggedIn()) {
    sendError('Authentication required. Provide valid X-API-KEY header or login session.', 401);
}

// Route API requests
try {
    switch ($action) {
        // ==================== AUTH ====================
        case 'login':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            handleLogin($request);
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        case 'me':
            handleGetCurrentUser();
            break;
            
        case 'change-password':
            handleChangePassword($request);
            break;
            
        // ==================== EMPLOYEES ====================
        case 'employees':
            handleEmployees($method, $request);
            break;
            
        case 'employee':
            handleEmployee($method, $request);
            break;
            
        case 'employee-stats':
            handleEmployeeStats($request);
            break;
            
        // ==================== CLIENTS ====================
        case 'clients':
            handleClients($method, $request);
            break;
            
        case 'units':
            handleUnits($method, $request);
            break;
            
        // ==================== ATTENDANCE ====================
        case 'attendance':
            handleAttendance($method, $request);
            break;
            
        case 'attendance-summary':
            handleAttendanceSummary();
            break;
            
        // ==================== PAYROLL ====================
        case 'payroll-periods':
            handlePayrollPeriods($method, $request);
            break;
            
        case 'process-payroll':
            handleProcessPayroll($request);
            break;
            
        case 'payroll':
            handlePayroll($method);
            break;
            
        case 'payslip':
            handlePayslip($request);
            break;
            
        // ==================== COMPLIANCE ====================
        case 'compliance-calendar':
            handleComplianceCalendar();
            break;
            
        case 'compliance-summary':
            handleComplianceSummary();
            break;
            
        case 'minimum-wages':
            handleMinimumWages($method);
            break;
            
        // ==================== DASHBOARD ====================
        case 'dashboard':
            handleDashboard();
            break;
            
        // ==================== SETTINGS ====================
        case 'settings':
            handleSettings($method, $request);
            break;
            
        case 'users':
            handleUsers($method, $request);
            break;
            
        default:
            sendError('Invalid action', 400);
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

// ==================== HANDLERS ====================

// Helper functions
function sendSuccess($message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Check if authenticated via API key
function isAuthenticated($useApiKey, $auth) {
    return $useApiKey || $auth->isLoggedIn();
}

// Auth Handlers
function handleLogin($request) {
    global $auth;
    
    $username = sanitize($request['username'] ?? '');
    $password = $request['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendError('Username and password are required');
    }
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        sendSuccess('Login successful', $result);
    } else {
        sendError($result['error'] ?? $result['message'] ?? 'Login failed', 401);
    }
}

function handleLogout() {
    global $auth;
    $auth->logout();
    sendSuccess('Logged out successfully');
}

// Define constant for authentication error message
define('MSG_NOT_AUTHENTICATED', 'Not authenticated');

define('MSG_AUTH_REQUIRED', 'Authentication required. Provide valid X-API-KEY header or login session.');

function handleGetCurrentUser() {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    sendSuccess('User data', $auth->getUser());
}

function handleChangePassword($request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $result = $auth->changePassword(
        $auth->getUserId(),
        $request['current_password'],
        $request['new_password']
    );
    
    if ($result['success']) {
        sendSuccess($result['message']);
    } else {
        sendError($result['message']);
    }
}

// Employee Handlers
function handleEmployees($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $employee = new Employee();
    
    if ($method === 'GET') {
        $page = intval($_GET['page'] ?? 1);
        $filters = [
            'status' => sanitize($_GET['status'] ?? ''),
            'client_id' => intval($_GET['client_id'] ?? 0) ?: null,
            'unit_id' => intval($_GET['unit_id'] ?? 0) ?: null,
            'worker_category' => sanitize($_GET['worker_category'] ?? ''),
            'search' => sanitize($_GET['search'] ?? '')
        ];
        
        $result = $employee->getAll($filters, $page);
        sendSuccess('Employees retrieved', $result);
    } elseif ($method === 'POST') {
        $data = sanitize($request);
        
        $result = $employee->create($data);
        
        if ($result['success']) {
            sendSuccess($result['message'], ['employee_id' => $result['employee_id']]);
        } else {
            sendError($result['message']);
        }
    }
}

function handleEmployee($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $employee = new Employee();
    $id = $_GET['id'] ?? $request['id'] ?? '';
    
    switch ($method) {
        case 'GET':
            handleEmployeeGet($employee, $id);
            break;
        case 'PUT':
        case 'POST':
            handleEmployeeUpdate($employee, $id, $request);
            break;
        case 'DELETE':
            handleEmployeeDelete($employee, $id);
            break;
        default:
            sendError('Method not allowed', 405);
    }
}

function handleEmployeeGet($employee, $id) {
    if (empty($id)) {
        sendError('Employee ID required');
    }
    $emp = $employee->getById($id);
    if ($emp) {
        sendSuccess('Employee found', $emp);
    } else {
        sendError('Employee not found', 404);
    }
}

function handleEmployeeUpdate($employee, $id, $request) {
    $data = sanitize($request);
    $result = $employee->update($id, $data);
    if ($result['success']) {
        sendSuccess($result['message']);
    } else {
        sendError($result['message']);
    }
}

function handleEmployeeDelete($employee, $id) {
    $result = $employee->delete($id);
    if ($result['success']) {
        sendSuccess($result['message']);
    } else {
        sendError($result['message']);
    }
}

function handleEmployeeStats($request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $employee = new Employee();
    $stats = $employee->getStatistics($request);
    sendSuccess('Statistics retrieved', $stats);
}

// Client Handlers
function handleClients($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $db = Database::getInstance();
    
    if ($method === 'GET') {
        $clients = $db->fetchAll(
            "SELECT * FROM clients WHERE is_active = 1 ORDER BY name"
        );
        sendSuccess('Clients retrieved', $clients);
    } elseif ($method === 'POST') {
        $data = sanitize($request);
        $id = $db->insert('clients', $data);
        sendSuccess('Client created', ['id' => $id]);
    }
}

function handleUnits($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $db = Database::getInstance();
    
    if ($method === 'GET') {
        $clientId = intval($_GET['client_id'] ?? 0);
        
        $sql = "SELECT u.*, c.name as client_name FROM units u JOIN clients c ON u.client_id = c.id WHERE u.is_active = 1";
        $params = [];
        
        if ($clientId) {
            $sql .= " AND u.client_id = :client_id";
            $params['client_id'] = $clientId;
        }
        
        $sql .= " ORDER BY u.name";
        
        $units = $db->fetchAll($sql, $params);
        sendSuccess('Units retrieved', $units);
    } elseif ($method === 'POST') {
        $data = sanitize($request);
        $id = $db->insert('units', $data);
        sendSuccess('Unit created', ['id' => $id]);
    }
}

// Attendance Handlers
function handleAttendance($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $attendanceObj = new Attendance();
    
    if ($method === 'GET') {
        $employeeCode = sanitize($_GET['employee_code'] ?? '');
        $month = intval($_GET['month'] ?? date('n'));
        $year = intval($_GET['year'] ?? date('Y'));
        
        if ($employeeCode) {
            $data = $attendanceObj->getEmployeeAttendance($employeeCode, $month, $year);
        } else {
            $unitId = intval($_GET['unit_id'] ?? 0);
            $data = $attendanceObj->getUnitAttendance($unitId, $month, $year);
        }
        
        sendSuccess('Attendance retrieved', $data);
    } elseif ($method === 'POST') {
        $result = $attendanceObj->saveAttendance(
            $request['employee_code'] ?? '',
            $request['date'] ?? '',
            $request['status'] ?? 'Present',
            $request['unit_id'] ?? null,
            $request['in_time'] ?? null,
            $request['out_time'] ?? null,
            $request['remarks'] ?? null
        );
        
        sendSuccess('Attendance recorded', $result);
    }
}

function handleAttendanceSummary() {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $attendanceObj = new Attendance();
    $employeeCode = sanitize($_GET['employee_code'] ?? '');
    $month = intval($_GET['month'] ?? date('n'));
    $year = intval($_GET['year'] ?? date('Y'));
    
    $summary = $attendanceObj->getEmployeeSummary($employeeCode, $month, $year);
    sendSuccess('Summary retrieved', $summary);
}

// Payroll Handlers
function handlePayrollPeriods($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $payrollObj = new Payroll();
    
    if ($method === 'GET') {
        $periods = $payrollObj->getPeriods();
        sendSuccess('Payroll periods retrieved', $periods);
    } elseif ($method === 'POST') {
        $result = $payrollObj->createPeriod(
            intval($request['month']),
            intval($request['year'])
        );
        
        if ($result['success']) {
            sendSuccess($result['message'], $result);
        } else {
            sendError($result['message']);
        }
    }
}

function handleProcessPayroll($request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $payrollObj = new Payroll();
    $periodId = intval($request['period_id']);
    
    $result = $payrollObj->processPayroll($periodId);
    
    if ($result['success']) {
        sendSuccess($result['message'], $result);
    } else {
        sendError($result['message']);
    }
}

function handlePayroll($method) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $payrollObj = new Payroll();
    $periodId = intval($_GET['period_id'] ?? 0);
    
    if ($method === 'GET') {
        if (!$periodId) {
            sendError('Period ID required');
        }
        
        $data = $payrollObj->getPayrollReport($periodId);
        sendSuccess('Payroll retrieved', $data);
    }
}

function handlePayslip($request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $payrollObj = new Payroll();
    $periodId = $_GET['period_id'] ?? $request['period_id'] ?? null;
    $employeeCode = $_GET['employee_code'] ?? $request['employee_code'] ?? null;
    
    if (!$periodId || !$employeeCode) {
        sendError('Period ID and Employee Code required');
    }
    
    $data = $payrollObj->getPayslip($periodId, $employeeCode);
    
    if ($data) {
        sendSuccess('Payslip retrieved', $data);
    } else {
        sendError('Payslip not found', 404);
    }
}

// Compliance Handlers
function handleComplianceCalendar() {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $complianceObj = new Compliance();
    $month = intval($_GET['month'] ?? date('n'));
    $year = intval($_GET['year'] ?? date('Y'));
    
    $calendar = $complianceObj->getComplianceCalendar($month, $year);
    sendSuccess('Compliance calendar retrieved', $calendar);
}

function handleComplianceSummary() {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $complianceObj = new Compliance();
    $summary = $complianceObj->getSummary();
    sendSuccess('Compliance summary retrieved', $summary);
}

function handleMinimumWages($method) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $complianceObj = new Compliance();
    
    if ($method === 'GET') {
        $stateId = intval($_GET['state_id'] ?? 0) ?: null;
        $zoneId = intval($_GET['zone_id'] ?? 0) ?: null;
        
        $wages = $complianceObj->getMinimumWages($stateId, $zoneId);
        sendSuccess('Minimum wages retrieved', $wages);
    }
}

// Dashboard Handler
function handleDashboard() {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $employeeObj = new Employee();
    $complianceObj = new Compliance();
    $db = Database::getInstance();
    
    $data = [
        'employee_stats' => $employeeObj->getCounts(),
        'compliance' => $complianceObj->getSummary(),
        'recent_payroll' => $db->fetch(
            "SELECT * FROM payroll_periods ORDER BY created_at DESC LIMIT 1"
        )
    ];
    
    sendSuccess('Dashboard data retrieved', $data);
}

// Settings Handler
function handleSettings($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    $db = Database::getInstance();
    
    if ($method === 'GET') {
        $settings = $db->fetchAll("SELECT * FROM settings ORDER BY setting_key");
        $settingsArr = [];
        foreach ($settings as $s) {
            $settingsArr[$s['setting_key']] = $s['setting_value'];
        }
        sendSuccess('Settings retrieved', $settingsArr);
    } elseif ($method === 'POST') {
        foreach ($request as $key => $value) {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                ['value' => sanitize($value), 'key' => sanitize($key)]
            );
        }
        sendSuccess('Settings updated');
    }
}

// Users Handler
function handleUsers($method, $request) {
    global $auth, $useApiKey;
    
    if (!isAuthenticated($useApiKey, $auth)) {
        sendError(MSG_NOT_AUTHENTICATED, 401);
    }
    
    if ($method === 'GET') {
        $users = $auth->getUsers(sanitize($_GET));
        sendSuccess('Users retrieved', $users);
    } elseif ($method === 'POST') {
        $result = $auth->createUser(sanitize($request));
        
        if ($result['success']) {
            sendSuccess($result['message'], ['user_id' => $result['user_id']]);
        } else {
            sendError($result['message']);
        }
    }
}
