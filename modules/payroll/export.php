<?php
/**
 * RCS HRMS Pro - Payroll Export
 * Version: 2.2.0
 * 
 * Exports payroll data in multiple formats:
 * - Excel (XLSX)
 * - PDF
 * - NEFT/Bank Transfer format
 * 
 * IMPORTANT NOTES:
 * - Use client_id and unit_id for filtering
 * - clients table uses 'name' column
 * - AADHAAR NUMBER SHOULD NOT BE HIDDEN IN INTERNAL REPORTS
 *   (Only mask for external/payslip exports)
 */

$pageTitle = 'Export Payroll';

// Check required parameters
$periodId = (int)($_GET['period_id'] ?? 0);
$format = $_GET['format'] ?? 'excel';

if (!$periodId) {
    setFlash('error', 'Payroll period not specified.');
    redirect('index.php?page=payroll/process');
}

// Get period info
$period = $db->fetch(
    "SELECT * FROM payroll_periods WHERE id = :id",
    ['id' => $periodId]
);

if (!$period) {
    setFlash('error', 'Payroll period not found.');
    redirect('index.php?page=payroll/process');
}

// Get payroll data
$payrollData = $payroll->getPayrollReport($periodId);
$totals = $payroll->getPeriodSummary($periodId);

// Export based on format
switch ($format) {
    case 'excel':
        exportExcel($period, $payrollData, $totals);
        break;
    case 'pdf':
        exportPdf($period, $payrollData, $totals);
        break;
    case 'neft':
        exportNEFT($period, $payrollData);
        break;
    default:
        setFlash('error', 'Invalid export format.');
        redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

/**
 * Export to Excel format
 */
function exportExcel($period, $data, $totals) {
    // Include PHPSpreadsheet or use simple CSV
    $filename = 'Payroll_' . $period['period_name'] . '_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header row
    fputcsv($output, [
        'Employee Code',
        'Employee Name',
        'Client',
        'Unit',
        'Designation',
        'Total Days',
        'Paid Days',
        'Unpaid Days',
        'Basic',
        'DA',
        'HRA',
        'Conveyance',
        'Medical',
        'Special Allowance',
        'Other Allowance',
        'Overtime',
        'Gross Earnings',
        'PF (Employee)',
        'ESI (Employee)',
        'Professional Tax',
        'LWF (Employee)',
        'Salary Advance',
        'Total Deductions',
        'Net Pay',
        'PF (Employer)',
        'EPS (Employer)',
        'EDLIS',
        'EPF Admin',
        'ESI (Employer)',
        'Employer Contribution',
        'Bonus Provision',
        'Gratuity Provision',
        'CTC',
        'Payment Mode',
        'Status',
        'Bank Name',
        'Account Number',
        'IFSC Code'
    ]);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['employee_id'] ?? '',
            $row['full_name'] ?? '',
            $row['client_name'] ?? '',
            $row['unit_name'] ?? '',
            $row['designation'] ?? '',
            $row['total_days'] ?? 30,
            $row['paid_days'] ?? 0,
            $row['unpaid_days'] ?? 0,
            number_format($row['basic'] ?? 0, 2, '.', ''),
            number_format($row['da'] ?? 0, 2, '.', ''),
            number_format($row['hra'] ?? 0, 2, '.', ''),
            number_format($row['conveyance'] ?? 0, 2, '.', ''),
            number_format($row['medical_allowance'] ?? 0, 2, '.', ''),
            number_format($row['special_allowance'] ?? 0, 2, '.', ''),
            number_format($row['other_allowance'] ?? 0, 2, '.', ''),
            number_format($row['overtime_amount'] ?? 0, 2, '.', ''),
            number_format($row['gross_earnings'] ?? 0, 2, '.', ''),
            number_format($row['pf_employee'] ?? 0, 2, '.', ''),
            number_format($row['esi_employee'] ?? 0, 2, '.', ''),
            number_format($row['professional_tax'] ?? 0, 2, '.', ''),
            number_format($row['lwf_employee'] ?? 0, 2, '.', ''),
            number_format($row['salary_advance'] ?? 0, 2, '.', ''),
            number_format($row['total_deductions'] ?? 0, 2, '.', ''),
            number_format($row['net_pay'] ?? 0, 2, '.', ''),
            number_format($row['pf_employer'] ?? 0, 2, '.', ''),
            number_format($row['eps_employer'] ?? 0, 2, '.', ''),
            number_format($row['edlis_employer'] ?? 0, 2, '.', ''),
            number_format($row['epf_admin_charges'] ?? 0, 2, '.', ''),
            number_format($row['esi_employer'] ?? 0, 2, '.', ''),
            number_format($row['total_employer_contribution'] ?? 0, 2, '.', ''),
            number_format($row['bonus_provision'] ?? 0, 2, '.', ''),
            number_format($row['gratuity_provision'] ?? 0, 2, '.', ''),
            number_format($row['ctc'] ?? 0, 2, '.', ''),
            $row['payment_mode'] ?? 'Bank Transfer',
            $row['status'] ?? '',
            $row['bank_name'] ?? '',
            $row['account_number'] ?? '',
            $row['ifsc_code'] ?? ''
        ]);
    }
    
    // Totals row
    fputcsv($output, [
        'TOTAL',
        '',
        '',
        '',
        '',
        $totals['total_days'] ?? 0,
        $totals['total_paid_days'] ?? 0,
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        number_format($totals['total_overtime'] ?? 0, 2, '.', ''),
        number_format($totals['total_gross'] ?? 0, 2, '.', ''),
        number_format($totals['total_pf_employee'] ?? 0, 2, '.', ''),
        number_format($totals['total_esi_employee'] ?? 0, 2, '.', ''),
        number_format($totals['total_pt'] ?? 0, 2, '.', ''),
        '',
        number_format($totals['total_advance'] ?? 0, 2, '.', ''),
        number_format($totals['total_deductions'] ?? 0, 2, '.', ''),
        number_format($totals['total_net_pay'] ?? 0, 2, '.', ''),
        number_format($totals['total_pf_employer'] ?? 0, 2, '.', ''),
        number_format($totals['total_eps_employer'] ?? 0, 2, '.', ''),
        number_format($totals['edli_contribution'] ?? 0, 2, '.', ''),
        number_format($totals['epf_admin_charges'] ?? 0, 2, '.', ''),
        number_format($totals['total_esi_employer'] ?? 0, 2, '.', ''),
        number_format($totals['total_employer_contribution'] ?? 0, 2, '.', ''),
        '',
        '',
        number_format($totals['total_ctc'] ?? 0, 2, '.', ''),
        '',
        '',
        '',
        '',
        ''
    ]);
    
    fclose($output);
    exit;
}

/**
 * Export to PDF format
 */
function exportPdf($period, $data, $totals) {
    // Use TCPDF or mPDF if available, otherwise simple HTML
    require_once APP_ROOT . '/includes/SimpleXLSX.php'; // For any dependencies
    
    $filename = 'Payroll_' . str_replace(' ', '_', $period['period_name']) . '.pdf';
    
    // Simple HTML to PDF (will open in browser for printing)
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll Report - <?php echo sanitize($period['period_name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 20px; }
        h1 { font-size: 16px; margin-bottom: 5px; }
        h2 { font-size: 12px; color: #666; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { background: #f0f0f0; font-weight: bold; }
        .summary { margin-top: 20px; }
        .summary table { width: auto; }
        @media print {
            body { margin: 0; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <h1>RCS HRMS Pro - Payroll Report</h1>
    <h2><?php echo sanitize($period['period_name']); ?> | Generated: <?php echo date('d-m-Y H:i'); ?></h2>
    
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Employee Name</th>
                <th>Client/Unit</th>
                <th class="text-center">Days</th>
                <th class="text-right">Gross</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net Pay</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
            <tr>
                <td><?php echo sanitize($row['employee_id']); ?></td>
                <td><?php echo sanitize($row['full_name']); ?></td>
                <td><?php echo sanitize(($row['client_name'] ?? '-') . '/' . ($row['unit_name'] ?? '-')); ?></td>
                <td class="text-center"><?php echo $row['paid_days'] ?? 0; ?></td>
                <td class="text-right"><?php echo number_format($row['gross_earnings'] ?? 0, 2); ?></td>
                <td class="text-right"><?php echo number_format($row['total_deductions'] ?? 0, 2); ?></td>
                <td class="text-right"><strong><?php echo number_format($row['net_pay'] ?? 0, 2); ?></strong></td>
                <td><?php echo sanitize($row['status'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3"><strong>TOTAL (<?php echo count($data); ?> employees)</strong></td>
                <td class="text-center"><?php echo $totals['total_paid_days'] ?? 0; ?></td>
                <td class="text-right"><?php echo number_format($totals['total_gross'] ?? 0, 2); ?></td>
                <td class="text-right"><?php echo number_format($totals['total_deductions'] ?? 0, 2); ?></td>
                <td class="text-right"><?php echo number_format($totals['total_net_pay'] ?? 0, 2); ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    
    <div class="summary">
        <h3>Statutory Summary</h3>
        <table>
            <tr><td>PF (Employee)</td><td class="text-right"><?php echo number_format($totals['total_pf_employee'] ?? 0, 2); ?></td></tr>
            <tr><td>PF (Employer)</td><td class="text-right"><?php echo number_format($totals['total_pf_employer'] ?? 0, 2); ?></td></tr>
            <tr><td>EPS (Employer)</td><td class="text-right"><?php echo number_format($totals['total_eps_employer'] ?? 0, 2); ?></td></tr>
            <tr><td>ESI (Employee)</td><td class="text-right"><?php echo number_format($totals['total_esi_employee'] ?? 0, 2); ?></td></tr>
            <tr><td>ESI (Employer)</td><td class="text-right"><?php echo number_format($totals['total_esi_employer'] ?? 0, 2); ?></td></tr>
            <tr><td>Professional Tax</td><td class="text-right"><?php echo number_format($totals['total_pt'] ?? 0, 2); ?></td></tr>
            <tr><td><strong>Total Employer Contribution</strong></td><td class="text-right"><strong><?php echo number_format($totals['total_employer_contribution'] ?? 0, 2); ?></strong></td></tr>
        </table>
    </div>
    
    <script>window.print();</script>
</body>
</html>
    <?php
    exit;
}

/**
 * Export to NEFT/Bank Transfer format
 */
function exportNEFT($period, $data) {
    // Filter only bank transfer employees with valid bank details
    $bankTransfers = array_filter($data, function($row) {
        return ($row['payment_mode'] ?? 'Bank Transfer') === 'Bank Transfer'
            && ($row['net_pay'] ?? 0) > 0
            && !($row['salary_hold'] ?? 0)
            && !empty($row['account_number'])
            && !empty($row['ifsc_code']);
    });
    
    $filename = 'NEFT_' . str_replace(' ', '_', $period['period_name']) . '_' . date('Ymd') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // NEFT Format Header
    fputcsv($output, [
        'Beneficiary Account Number',
        'Beneficiary Name',
        'IFSC Code',
        'Amount',
        'Debit Account Number',  // Company account (to be filled)
        'Beneficiary Reference No',
        'Payment Type',
        'Transaction Reference',
        'Remarks'
    ]);
    
    $srNo = 1;
    foreach ($bankTransfers as $row) {
        fputcsv($output, [
            $row['account_number'],
            $row['account_holder_name'] ?: $row['full_name'],
            $row['ifsc_code'],
            number_format($row['net_pay'], 2, '.', ''),
            '',  // Company account to be filled
            'SAL' . $period['month'] . $period['year'] . str_pad($srNo, 4, '0', STR_PAD_LEFT),
            'NEFT',
            $row['employee_id'],
            'Salary for ' . $period['period_name']
        ]);
        $srNo++;
    }
    
    // Total row
    $totalAmount = array_sum(array_column($bankTransfers, 'net_pay'));
    fputcsv($output, [
        '',
        'TOTAL',
        '',
        number_format($totalAmount, 2, '.', ''),
        '',
        'Count: ' . count($bankTransfers),
        '',
        '',
        ''
    ]);
    
    fclose($output);
    exit;
}
