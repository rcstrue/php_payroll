<?php
/**
 * RCS HRMS Pro - Print Payslip Page
 * Standalone page for printing individual payslips
 * Updated for new database schema
 */

define('APP_ROOT', dirname(__DIR__, 2));
define('RCS_HRMS', true);
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/database.php';

// Initialize classes
$payrollObj = new Payroll();

$payrollId = $_GET['id'] ?? null;

if (!$payrollId) {
    die('Payslip ID required');
}

$data = $payrollObj->getPayslipById($payrollId);

if (!$data) {
    die('Payslip not found');
}

// Get period info
$period = $db->prepare("SELECT * FROM payroll_periods WHERE id = ?");
$period->execute([$data['payroll_period_id'] ?? 0]);
$periodData = $period->fetch(PDO::FETCH_ASSOC);
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
    <div class="no-print mb-3">
        <button onclick="window.print()" class="btn btn-primary">
            Print
        </button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
    
    <div class="payslip-container">
        <!-- Header -->
        <div class="payslip-header">
            <div>
                <div class="company-name">RCS TRUE FACILITIES PVT LTD</div>
                <div class="company-address">110, Someswar Square, Vesu, Surat - 395007, Gujarat</div>
                <div class="company-address">GST: 24AAICR1390M1Z3 | PAN: AAICR1390M</div>
            </div>
            <div class="payslip-period">
                <div class="label">PAYSLIP FOR</div>
                <div class="value"><?php echo sanitize($periodData['period_name'] ?? date('F Y')); ?></div>
            </div>
        </div>
        
        <!-- Employee Info -->
        <div class="employee-info">
            <div class="info-item">
                <div class="label">Employee Code</div>
                <div class="value"><?php echo sanitize($data['employee_id'] ?? '-'); ?></div>
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
                <div class="value"><?php echo $data['paid_days'] ?? 0; ?> / <?php echo $data['total_days'] ?? 30; ?></div>
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
                    <span><?php echo formatCurrency($data['basic'] ?? 0); ?></span>
                </div>
                <div class="payslip-row">
                    <span>Dearness Allowance</span>
                    <span><?php echo formatCurrency($data['da'] ?? 0); ?></span>
                </div>
                <div class="payslip-row">
                    <span>House Rent Allowance</span>
                    <span><?php echo formatCurrency($data['hra'] ?? 0); ?></span>
                </div>
                <div class="payslip-row">
                    <span>Conveyance</span>
                    <span><?php echo formatCurrency($data['conveyance'] ?? 0); ?></span>
                </div>
                <div class="payslip-row">
                    <span>Medical Allowance</span>
                    <span><?php echo formatCurrency($data['medical_allowance'] ?? 0); ?></span>
                </div>
                <div class="payslip-row">
                    <span>Special Allowance</span>
                    <span><?php echo formatCurrency($data['special_allowance'] ?? 0); ?></span>
                </div>
                <?php if (($data['overtime_amount'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Overtime (<?php echo $data['overtime_hours'] ?? 0; ?> hrs)</span>
                    <span><?php echo formatCurrency($data['overtime_amount']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['extra_days_amount'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Extra Days Payment</span>
                    <span><?php echo formatCurrency($data['extra_days_amount']); ?></span>
                </div>
                <?php endif; ?>
                <div class="payslip-row total">
                    <span>GROSS EARNINGS</span>
                    <span><?php echo formatCurrency($data['gross_earnings'] ?? 0); ?></span>
                </div>
            </div>
            
            <!-- Deductions -->
            <div class="payslip-section">
                <div class="section-title">DEDUCTIONS</div>
                <?php if (($data['pf_employee'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Provident Fund</span>
                    <span><?php echo formatCurrency($data['pf_employee']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['esi_employee'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>ESI Contribution</span>
                    <span><?php echo formatCurrency($data['esi_employee']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['professional_tax'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Professional Tax</span>
                    <span><?php echo formatCurrency($data['professional_tax']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['salary_advance'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>Salary Advance</span>
                    <span><?php echo formatCurrency($data['salary_advance']); ?></span>
                </div>
                <?php endif; ?>
                <div class="payslip-row total">
                    <span>TOTAL DEDUCTIONS</span>
                    <span><?php echo formatCurrency($data['total_deductions'] ?? 0); ?></span>
                </div>
                
                <div class="section-title mt-4">EMPLOYER CONTRIBUTION</div>
                <?php if (($data['pf_employer'] ?? 0) > 0 || ($data['eps_employer'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>PF + EPS</span>
                    <span><?php echo formatCurrency(($data['pf_employer'] ?? 0) + ($data['eps_employer'] ?? 0)); ?></span>
                </div>
                <?php endif; ?>
                <?php if (($data['esi_employer'] ?? 0) > 0): ?>
                <div class="payslip-row">
                    <span>ESI</span>
                    <span><?php echo formatCurrency($data['esi_employer']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="payslip-footer">
            <div>
                <div class="net-pay-label">NET PAY</div>
                <div class="net-pay-value"><?php echo formatCurrency($data['net_pay'] ?? 0); ?></div>
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
</body>
</html>
