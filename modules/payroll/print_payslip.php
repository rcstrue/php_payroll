<?php
/**
 * RCS HRMS Pro - Print Payslip Page
 * Standalone page for printing individual payslips
 */

define('APP_ROOT', dirname(__DIR__, 2));
define('RCS_HRMS', true);
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/database.php';

$payrollId = $_GET['id'] ?? null;

if (!$payrollId) {
    die('Payslip ID required');
}

// Get payroll record
$stmt = $db->prepare("
    SELECT pr.*, pp.period_name, pp.month, pp.year, pp.start_date, pp.end_date, pp.pay_days,
           e.full_name, e.employee_code, e.designation, e.department,
           e.client_name, e.unit_name, e.date_of_joining,
           e.uan_number, e.esic_number,
           e.bank_name, e.account_number, e.ifsc_code
    FROM payroll_records pr
    JOIN payroll_periods pp ON pr.period_id = pp.id
    LEFT JOIN employees e ON e.employee_code = pr.employee_id
    WHERE pr.id = ?
");
$stmt->execute([$payrollId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('Payslip not found');
}

// Get company settings
$company = $db->fetch("SELECT * FROM company_settings LIMIT 1") ?? [
    'company_name' => 'RCS TRUE FACILITIES PVT LTD',
    'address' => '110, Someswar Square, Vesu, Surat - 395007, Gujarat',
    'gst_number' => '24AAICR1390M1Z3',
    'pan_number' => 'AAICR1390M'
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?php echo sanitize($data['full_name'] ?? 'Employee'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            padding: 20px;
            background: #fff;
        }
        
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #000;
        }
        
        .payslip-header {
            background: #1a1a2e;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
        }
        
        .company-address {
            font-size: 10px;
            opacity: 0.8;
        }
        
        .payslip-period {
            text-align: right;
        }
        
        .payslip-period .label {
            font-size: 10px;
            opacity: 0.8;
        }
        
        .payslip-period .value {
            font-size: 16px;
            font-weight: bold;
        }
        
        .employee-info {
            padding: 15px 20px;
            background: #f8f9fa;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .info-item .label {
            font-size: 10px;
            color: #666;
        }
        
        .info-item .value {
            font-weight: 500;
        }
        
        .payslip-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        
        .payslip-section {
            padding: 15px 20px;
        }
        
        .payslip-section:first-child {
            border-right: 1px solid #ddd;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 11px;
            color: #333;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        
        .payslip-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        .payslip-row.total {
            border-top: 1px solid #000;
            margin-top: 10px;
            padding-top: 10px;
            font-weight: bold;
        }
        
        .payslip-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #ddd;
        }
        
        .net-pay-label {
            font-size: 10px;
            color: #666;
        }
        
        .net-pay-value {
            font-size: 20px;
            font-weight: bold;
            color: #10b981;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding: 0 20px 20px;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <?php if (!isset($_GET['print'])): ?>
    <div class="no-print mb-3">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
    <?php endif; ?>
    
    <div class="payslip-container">
        <!-- Header -->
        <div class="payslip-header">
            <div>
                <div class="company-name"><?php echo sanitize($company['company_name']); ?></div>
                <div class="company-address"><?php echo sanitize($company['address']); ?></div>
                <div class="company-address">GST: <?php echo sanitize($company['gst_number']); ?> | PAN: <?php echo sanitize($company['pan_number']); ?></div>
            </div>
            <div class="payslip-period">
                <div class="label">PAYSLIP FOR</div>
                <div class="value"><?php echo sanitize($data['period_name'] ?? date('F Y', mktime(0, 0, 0, $data['month'], 1, $data['year']))); ?></div>
            </div>
        </div>
        
        <!-- Employee Info -->
        <div class="employee-info">
            <div class="info-item">
                <div class="label">Employee Code</div>
                <div class="value"><?php echo sanitize($data['employee_code'] ?? $data['employee_id']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Employee Name</div>
                <div class="value"><?php echo sanitize($data['full_name'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Department</div>
                <div class="value"><?php echo sanitize($data['department'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Designation</div>
                <div class="value"><?php echo sanitize($data['designation'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Client / Unit</div>
                <div class="value"><?php echo sanitize(($data['client_name'] ?? '-') . ' / ' . ($data['unit_name'] ?? '-')); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Paid Days</div>
                <div class="value"><?php echo $data['paid_days'] ?? 0; ?> / <?php echo $data['pay_days'] ?? 30; ?></div>
            </div>
            <div class="info-item">
                <div class="label">UAN Number</div>
                <div class="value"><?php echo sanitize($data['uan_number'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="label">ESIC Number</div>
                <div class="value"><?php echo sanitize($data['esic_number'] ?? '-'); ?></div>
            </div>
        </div>
        
        <!-- Body -->
        <div class="payslip-body">
            <!-- Earnings -->
            <div class="payslip-section">
                <div class="section-title">EARNINGS</div>
                <div class="payslip-row">
                    <span>Basic</span>
                    <span><?php echo number_format($data['basic_wage'] ?? 0); ?></span>
                </div>
                <?php if (($data['da'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Dearness Allowance</span>
                    <span><?php echo number_format($data['da']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['hra'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>House Rent Allowance</span>
                    <span><?php echo number_format($data['hra']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['other_allowances'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Other Allowances</span>
                    <span><?php echo number_format($data['other_allowances']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['overtime_amount'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Overtime</span>
                    <span><?php echo number_format($data['overtime_amount']); ?></span>
                </div>
                <?php endif; ?>
                <div class="payslip-row total">
                    <span>GROSS EARNINGS</span>
                    <span><?php echo number_format($data['gross_earnings'] ?? 0); ?></span>
                </div>
            </div>
            
            <!-- Deductions -->
            <div class="payslip-section">
                <div class="section-title">DEDUCTIONS</div>
                <?php if (($data['pf_employee'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Provident Fund</span>
                    <span><?php echo number_format($data['pf_employee']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['esi_employee'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>ESI Contribution</span>
                    <span><?php echo number_format($data['esi_employee']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['pt'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Professional Tax</span>
                    <span><?php echo number_format($data['pt']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['advance_deduction'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Advance</span>
                    <span><?php echo number_format($data['advance_deduction']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['other_deductions'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Other Deductions</span>
                    <span><?php echo number_format($data['other_deductions']); ?></span>
                </div>
                <?php endif; ?>
                <div class="payslip-row total">
                    <span>TOTAL DEDUCTIONS</span>
                    <span><?php echo number_format($data['total_deductions'] ?? 0); ?></span>
                </div>
                
                <div class="section-title mt-4">EMPLOYER CONTRIBUTION</div>
                <?php if (($data['pf_employer'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>PF (Employer)</span>
                    <span><?php echo number_format($data['pf_employer']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['esi_employer'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>ESI (Employer)</span>
                    <span><?php echo number_format($data['esi_employer']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['employer_contribution'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span><strong>Total Employer Contribution</strong></span>
                    <span><strong><?php echo number_format($data['employer_contribution']); ?></strong></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="payslip-footer">
            <div>
                <div class="net-pay-label">NET PAY</div>
                <div class="net-pay-value">₹<?php echo number_format($data['net_pay'] ?? 0); ?></div>
            </div>
            <div class="text-end">
                <div class="small text-muted">
                    Bank: <?php echo sanitize($data['bank_name'] ?? '-'); ?><br>
                    A/C: <?php echo sanitize($data['account_number'] ?? '-'); ?>
                </div>
            </div>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Employee Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Authorized Signatory</div>
            </div>
        </div>
    </div>
    
    <?php if (isset($_GET['print'])): ?>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
    <?php endif; ?>
</body>
</html>
