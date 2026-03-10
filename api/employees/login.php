<?php
/**
 * RCS HRMS Pro - Employee Login API
 * Endpoint: /api/employees/login
 * Method: POST
 * Headers: X-API-KEY, Content-Type: application/json
 * 
 * Request Body:
 * {
 *   "mobile_number": "9876543210",
 *   "employee_code": "1001"
 * }
 */

// Define constant for included files
define('RCS_HRMS', true);

// Set headers for CORS and JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://sid.rcsfacility.com');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Client-Info, X-API-KEY, apikey');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Validate API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_APIKEY'] ?? '';

// Valid API keys (in production, store these in database or config)
$validApiKeys = [
    'RCS_HRMS_SECURE_KEY_982374982374',
    'RCS_HRMS_API_KEY_2024',
    'RCS_EMPLOYEE_PORTAL_KEY',
    'RCS_HRMS_SECURE_KEY_2024_XYZ'
];

if (empty($apiKey) || !in_array($apiKey, $validApiKeys)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid or missing API key'
    ]);
    exit;
}

// Include required files
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

// Validate required fields
$mobileNumber = trim($input['mobile_number'] ?? '');
$employeeCode = trim($input['employee_code'] ?? '');

if (empty($mobileNumber) && empty($employeeCode)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Mobile number or Employee code is required'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Build query based on provided credentials
    $sql = "SELECT e.*, 
                   ess.basic_wage, ess.da, ess.hra, ess.gross_salary,
                   ess.pf_applicable, ess.esi_applicable
            FROM employees e
            LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
            WHERE e.status = 'approved'";
    
    $params = [];
    
    if (!empty($mobileNumber)) {
        $sql .= " AND e.mobile_number = :mobile_number";
        $params['mobile_number'] = $mobileNumber;
    }
    
    if (!empty($employeeCode)) {
        $sql .= " AND e.employee_code = :employee_code";
        $params['employee_code'] = $employeeCode;
    }
    
    $sql .= " LIMIT 1";
    
    $employee = $db->fetch($sql, $params);
    
    if (!$employee) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Employee not found or not active'
        ]);
        exit;
    }
    
    // Generate a simple token (in production, use JWT)
    $token = bin2hex(random_bytes(32));
    
    // Build response data
    $responseData = [
        'id' => $employee['id'],
        'employee_code' => $employee['employee_code'],
        'full_name' => trim(($employee['salutation'] ?? '') . ' ' . $employee['full_name'] . ' ' . ($employee['father_name'] ?? '')),
        'mobile_number' => $employee['mobile_number'],
        'email' => $employee['email'] ?? '',
        'designation' => $employee['designation'] ?? '',
        'department' => $employee['department'] ?? '',
        'client_name' => $employee['client_name'] ?? '',
        'unit_name' => $employee['unit_name'] ?? '',
        'date_of_joining' => $employee['date_of_joining'],
        'worker_category' => $employee['worker_category'] ?? '',
        'gross_salary' => floatval($employee['gross_salary'] ?? 0),
        'uan_number' => $employee['uan_number'] ?? '',
        'esic_number' => $employee['esic_number'] ?? '',
        'pf_applicable' => (bool)($employee['pf_applicable'] ?? false),
        'esi_applicable' => (bool)($employee['esi_applicable'] ?? false),
    ];
    
    // Add profile picture if exists
    if (!empty($employee['profile_pic_cropped_url'])) {
        $responseData['profile_pic_url'] = $employee['profile_pic_cropped_url'];
    } elseif (!empty($employee['profile_pic_url'])) {
        $responseData['profile_pic_url'] = $employee['profile_pic_url'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log('Employee login API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred. Please try again.'
    ]);
}
