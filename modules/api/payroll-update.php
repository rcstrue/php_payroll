<?php
/**
 * RCS HRMS Pro - Payroll Update API
 * AJAX endpoint for inline salary updates
 */

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get input data
$periodId = (int)($_POST['period_id'] ?? 0);
$empCode = sanitize($_POST['emp_code'] ?? '');

if (!$periodId || !$empCode) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get salary components
$basicDA = floatval($_POST['basic_da'] ?? 0);
$hra = floatval($_POST['hra'] ?? 0);
$lww = floatval($_POST['lww'] ?? 0);
$bonus = floatval($_POST['bonus'] ?? 0);
$washing = floatval($_POST['washing'] ?? 0);
$other = floatval($_POST['other'] ?? 0);

// Calculate new values
$newGross = $basicDA + $hra + $lww + $bonus + $washing + $other;

// Calculate deductions
$pfEmp = round(min($basicDA, 15000) * 0.12, 2);
$esiEmp = ($newGross <= 21000) ? round($newGross * 0.0075, 2) : 0;
$pt = 200; // Simplified PT

// Get existing deduction adjustments
$existingPayroll = $db->fetch(
    "SELECT salary_advance, other_deductions FROM payroll WHERE payroll_period_id = :pid AND employee_id = :emp",
    ['pid' => $periodId, 'emp' => $empCode]
);

$salaryAdvance = floatval($existingPayroll['salary_advance'] ?? 0);
$otherDeductions = floatval($existingPayroll['other_deductions'] ?? 0);

$totalDed = $pfEmp + $esiEmp + $pt + $salaryAdvance + $otherDeductions;
$netPay = $newGross - $totalDed;

try {
    // Update payroll record
    $db->update('payroll', [
        'basic_da' => $basicDA,
        'basic' => $basicDA * 0.6,
        'da' => $basicDA * 0.4,
        'hra' => $hra,
        'lww' => $lww,
        'bonus_amount' => $bonus,
        'washing_allowance' => $washing,
        'other_allowance' => $other,
        'gross_earnings' => $newGross,
        'gross_salary' => $newGross,
        'pf_employee' => $pfEmp,
        'esi_employee' => $esiEmp,
        'professional_tax' => $pt,
        'total_deductions' => $totalDed,
        'net_pay' => $netPay,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'payroll_period_id = :pid AND employee_id = :emp', ['pid' => $periodId, 'emp' => $empCode]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Salary updated successfully',
        'gross' => $newGross,
        'deductions' => $totalDed,
        'net_pay' => $netPay
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
