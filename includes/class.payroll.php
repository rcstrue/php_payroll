<?php
/**
 * RCS HRMS Pro - Payroll Class
 * Handles payroll processing and management
 * Updated for new database schema with proper statutory calculations
 */

// SQL clause constants to avoid string duplication
define('SQL_FILTER_UNIT_NAME', ' AND e.unit_name = :unit_name');
define('SQL_FILTER_CLIENT_NAME', ' AND e.client_name = :client_name');
define('SQL_WHERE_ID', 'id = :id');

class Payroll {
    private $db;
    
    // PF Rates (current)
    private $pfRates = [
        'employee_share' => 12.00,
        'employer_pf' => 3.67,
        'employer_eps' => 8.33,
        'employer_edlis' => 0.50,
        'epf_admin' => 0.50,
        'wage_ceiling' => 15000
    ];
    
    // ESI Rates (current)
    private $esiRates = [
        'employee_share' => 0.75,
        'employer_share' => 3.25,
        'wage_ceiling' => 21000
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadStatutoryRates();
    }
    
    // Load current statutory rates from database
    private function loadStatutoryRates() {
        try {
            // Load PF rates
            $pfRate = $this->db->fetch(
                "SELECT * FROM pf_rates WHERE is_active = 1 ORDER BY effective_from DESC LIMIT 1"
            );
            if ($pfRate) {
                $this->pfRates = [
                    'employee_share' => (float)$pfRate['employee_share'],
                    'employer_pf' => (float)$pfRate['employer_share_pf'],
                    'employer_eps' => (float)$pfRate['employer_share_eps'],
                    'employer_edlis' => (float)$pfRate['employer_share_edlis'],
                    'epf_admin' => (float)$pfRate['epf_admin_charges'],
                    'wage_ceiling' => (float)$pfRate['wage_ceiling']
                ];
            }
            
            // Load ESI rates
            $esiRate = $this->db->fetch(
                "SELECT * FROM esi_rates WHERE is_active = 1 ORDER BY effective_from DESC LIMIT 1"
            );
            if ($esiRate) {
                $this->esiRates = [
                    'employee_share' => (float)$esiRate['employee_share'],
                    'employer_share' => (float)$esiRate['employer_share'],
                    'wage_ceiling' => (float)$esiRate['wage_ceiling']
                ];
            }
        } catch (Exception $e) {
            // Use default rates if tables don't exist
        }
    }
    
    // Get payroll periods
    public function getPeriods($status = null) {
        $sql = "SELECT * FROM payroll_periods WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY year DESC, month DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get current period
    public function getCurrentPeriod() {
        $month = date('n');
        $year = date('Y');
        
        return $this->db->fetch(
            "SELECT * FROM payroll_periods WHERE month = :month AND year = :year",
            ['month' => $month, 'year' => $year]
        );
    }
    
    // Get payroll totals for dashboard
    public function getPayrollTotals($periodId = null) {
        if (!$periodId) {
            $period = $this->getCurrentPeriod();
            if ($period) {
                $periodId = $period['id'];
            }
        }
        
        if (!$periodId) {
            return [
                'total_employees' => 0,
                'total_gross' => 0,
                'total_deductions' => 0,
                'total_net' => 0,
                'total_employer_contribution' => 0
            ];
        }
        
        return $this->db->fetch(
            "SELECT 
                COUNT(*) as total_employees,
                SUM(gross_earnings) as total_gross,
                SUM(total_deductions) as total_deductions,
                SUM(net_pay) as total_net,
                SUM(total_employer_contribution) as total_employer_contribution
            FROM payroll 
            WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        ) ?: [
            'total_employees' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'total_employer_contribution' => 0
        ];
    }
    
    // Get payroll for period
    public function getPayrollReport($periodId, $filters = []) {
        $sql = "SELECT p.*, 
                       e.employee_code, e.full_name, e.designation, e.client_name, e.unit_name
                FROM payroll p
                JOIN employees e ON p.employee_id = e.employee_code
                WHERE p.payroll_period_id = :period_id";
        $params = ['period_id' => $periodId];
        
        if (!empty($filters['unit_id'])) {
            $unit = $this->db->fetch("SELECT name FROM units WHERE id = :id", ['id' => $filters['unit_id']]);
            if ($unit) {
                $sql .= SQL_FILTER_UNIT_NAME;
                $params['unit_name'] = $unit['name'];
            }
        }
        
        if (!empty($filters['client_id'])) {
            $client = $this->db->fetch("SELECT name FROM clients WHERE id = :id", ['id' => $filters['client_id']]);
            if ($client) {
                $sql .= SQL_FILTER_CLIENT_NAME;
                $params['client_name'] = $client['name'];
            }
        }
        
        $sql .= " ORDER BY e.employee_code";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get employee payroll
    public function getEmployeePayroll($employeeCode, $periodId = null) {
        if ($periodId) {
            return $this->db->fetch(
                "SELECT p.*, e.full_name, e.designation, e.client_name, e.unit_name
                 FROM payroll p
                 JOIN employees e ON p.employee_id = e.employee_code
                 WHERE p.employee_id = :emp_code AND p.payroll_period_id = :period_id",
                ['emp_code' => $employeeCode, 'period_id' => $periodId]
            );
        }
        
        return $this->db->fetchAll(
            "SELECT p.*, pp.period_name, pp.month, pp.year, e.full_name, e.designation
             FROM payroll p 
             JOIN payroll_periods pp ON p.payroll_period_id = pp.id
             JOIN employees e ON p.employee_id = e.employee_code
             WHERE p.employee_id = :emp_code 
             ORDER BY pp.year DESC, pp.month DESC",
            ['emp_code' => $employeeCode]
        );
    }
    
    // Process payroll for period
    public function processPayroll($periodId, $filters = []) {
        // Get period info
        $period = $this->db->fetch(
            "SELECT * FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );
        
        if (!$period) {
            return ['success' => false, 'message' => 'Payroll period not found.'];
        }
        
        // Get all active employees with salary structure
        $sql = "SELECT e.id, e.employee_code, e.full_name, e.date_of_joining, 
                       e.date_of_leaving, e.client_name, e.unit_name, e.worker_category,
                       ess.basic_wage, ess.da, ess.hra, ess.conveyance, 
                       ess.medical_allowance, ess.special_allowance, ess.other_allowance,
                       ess.gross_salary, ess.pf_applicable, ess.esi_applicable, 
                       ess.pt_applicable, ess.lwf_applicable, ess.overtime_applicable
                FROM employees e
                INNER JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                    AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                WHERE e.status = 'approved'";
        
        $params = [];
        
        if (!empty($filters['unit_name'])) {
            $sql .= SQL_FILTER_UNIT_NAME;
            $params['unit_name'] = $filters['unit_name'];
        }
        
        if (!empty($filters['client_name'])) {
            $sql .= SQL_FILTER_CLIENT_NAME;
            $params['client_name'] = $filters['client_name'];
        }
        
        $employees = $this->db->fetchAll($sql, $params);
        
        $processed = 0;
        $errors = [];
        $totalGross = 0;
        $totalNet = 0;
        $totalPF = 0;
        $totalESI = 0;
        
        // Get unit_id for payroll records
        $getUnitId = function($unitName) {
            $unit = $this->db->fetch("SELECT id FROM units WHERE name = :name", ['name' => $unitName]);
            return $unit ? $unit['id'] : null;
        };
        
        foreach ($employees as $emp) {
            try {
                // Check if employee joined after period end
                $periodEndDate = $period['end_date'];
                if ($emp['date_of_joining'] && strtotime($emp['date_of_joining']) > strtotime($periodEndDate)) {
                    continue;
                }
                
                // Calculate attendance summary
                $attendance = $this->db->fetch(
                    "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(CASE WHEN status = 'Weekly Off' THEN 1 ELSE 0 END) as weekly_offs,
                        SUM(CASE WHEN status = 'Holiday' THEN 1 ELSE 0 END) as holidays,
                        SUM(CASE WHEN status IN ('Paid Leave', 'Sick Leave', 'Casual Leave') THEN 1 ELSE 0 END) as paid_leaves,
                        SUM(CASE WHEN status = 'Half Day' THEN 0.5 ELSE 0 END) as half_days,
                        SUM(overtime_hours) as overtime_hours
                    FROM attendance 
                    WHERE employee_id = :emp_code 
                    AND MONTH(attendance_date) = :month 
                    AND YEAR(attendance_date) = :year",
                    ['emp_code' => $emp['employee_code'], 'month' => $period['month'], 'year' => $period['year']]
                );
                
                $totalDays = $period['pay_days'] ?? 30;
                $paidDays = ($attendance['present_days'] ?? 0) + 
                           ($attendance['weekly_offs'] ?? 0) + 
                           ($attendance['holidays'] ?? 0) + 
                           ($attendance['paid_leaves'] ?? 0) +
                           ($attendance['half_days'] ?? 0);
                
                // If no attendance, assume full month
                if ($paidDays == 0) {
                    $paidDays = $totalDays;
                }
                
                $unpaidDays = $totalDays - $paidDays;
                $overtimeHours = $attendance['overtime_hours'] ?? 0;
                
                // Calculate earnings (pro-rated)
                $basic = round(($emp['basic_wage'] ?? 0) * $paidDays / $totalDays, 2);
                $da = round(($emp['da'] ?? 0) * $paidDays / $totalDays, 2);
                $hra = round(($emp['hra'] ?? 0) * $paidDays / $totalDays, 2);
                $conveyance = round(($emp['conveyance'] ?? 0) * $paidDays / $totalDays, 2);
                $medicalAllowance = round(($emp['medical_allowance'] ?? 0) * $paidDays / $totalDays, 2);
                $specialAllowance = round(($emp['special_allowance'] ?? 0) * $paidDays / $totalDays, 2);
                $otherAllowance = round(($emp['other_allowance'] ?? 0) * $paidDays / $totalDays, 2);
                
                $grossEarnings = $basic + $da + $hra + $conveyance + $medicalAllowance + $specialAllowance + $otherAllowance;
                
                // Calculate overtime amount (based on basic+da per hour * 2)
                $overtimeAmount = 0;
                if ($overtimeHours > 0 && ($emp['overtime_applicable'] ?? 0)) {
                    $hourlyRate = ($emp['basic_wage'] + $emp['da']) / 30 / 8;
                    $overtimeAmount = round($hourlyRate * $overtimeHours * 2, 2);
                }
                
                $grossWithOT = $grossEarnings + $overtimeAmount;
                
                // Calculate PF
                $pfEmployee = 0;
                $pfEmployer = 0;
                $epsEmployer = 0;
                $edlisEmployer = 0;
                $epfAdmin = 0;
                
                if ($emp['pf_applicable'] ?? 0) {
                    // PF is on Basic + DA (or up to ceiling)
                    $pfBase = min($basic + $da, $this->pfRates['wage_ceiling']);
                    $pfEmployee = round($pfBase * $this->pfRates['employee_share'] / 100, 2);
                    $pfEmployer = round($pfBase * $this->pfRates['employer_pf'] / 100, 2);
                    $epsEmployer = round($pfBase * $this->pfRates['employer_eps'] / 100, 2);
                    $edlisEmployer = round($pfBase * $this->pfRates['employer_edlis'] / 100, 2);
                    $epfAdmin = round($pfBase * $this->pfRates['epf_admin'] / 100, 2);
                }
                
                // Calculate ESI
                $esiEmployee = 0;
                $esiEmployer = 0;
                
                if (($emp['esi_applicable'] ?? 0) && $grossWithOT <= $this->esiRates['wage_ceiling']) {
                    $esiEmployee = round($grossWithOT * $this->esiRates['employee_share'] / 100, 2);
                    $esiEmployer = round($grossWithOT * $this->esiRates['employer_share'] / 100, 2);
                }
                
                // Calculate Professional Tax
                $pt = 0;
                if ($emp['pt_applicable'] ?? 0) {
                    $pt = $this->calculatePT($grossWithOT);
                }
                
                // Calculate LWF (Labour Welfare Fund) - simplified
                $lwfEmployee = 0;
                $lwfEmployer = 0;
                if ($emp['lwf_applicable'] ?? 0) {
                    // This is state-specific, simplified here
                    $lwfEmployee = 0; // Varies by state
                    $lwfEmployer = 0;
                }
                
                // Total deductions
                $totalDeductions = $pfEmployee + $esiEmployee + $pt + $lwfEmployee;
                
                // Net pay
                $netPay = $grossWithOT - $totalDeductions;
                
                // Employer contributions
                $employerContribution = $pfEmployer + $epsEmployer + $edlisEmployer + $epfAdmin + $esiEmployer + $lwfEmployer;
                
                // Bonus provision (8.33% of basic, max ₹7000)
                $bonusProvision = 0;
                if ($emp['pf_applicable'] ?? 0) {
                    $bonusBase = min($basic, 7000);
                    $bonusProvision = round($bonusBase * 8.33 / 100, 2);
                }
                
                // Gratuity provision (4.81% of basic)
                $gratuityProvision = round($basic * 4.81 / 100, 2);
                
                // CTC
                $ctc = $grossWithOT + $employerContribution + $bonusProvision + $gratuityProvision;
                
                // Get unit_id
                $unitId = $getUnitId($emp['unit_name']);
                
                // Insert payroll record
                $this->db->query(
                    "INSERT INTO payroll (
                        payroll_period_id, employee_id, unit_id,
                        total_days, paid_days, unpaid_days, overtime_hours,
                        basic, da, hra, conveyance, medical_allowance, special_allowance, other_allowance,
                        overtime_amount, gross_earnings,
                        pf_employee, esi_employee, professional_tax, lwf_employee,
                        total_deductions,
                        pf_employer, eps_employer, edlis_employer, epf_admin_charges, esi_employer, lwf_employer,
                        bonus_provision, gratuity_provision,
                        total_employer_contribution,
                        net_pay, gross_salary, ctc, status, created_at
                    ) VALUES (
                        :period_id, :emp_code, :unit_id,
                        :total_days, :paid_days, :unpaid_days, :ot_hours,
                        :basic, :da, :hra, :conveyance, :medical, :special, :other,
                        :ot_amount, :gross,
                        :pf_emp, :esi_emp, :pt, :lwf_emp,
                        :total_ded,
                        :pf_empr, :eps_empr, :edlis_empr, :epf_admin, :esi_empr, :lwf_empr,
                        :bonus, :gratuity,
                        :employer_contrib,
                        :net_pay, :gross_salary, :ctc, 'Processed', NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        paid_days = VALUES(paid_days),
                        gross_earnings = VALUES(gross_earnings),
                        total_deductions = VALUES(total_deductions),
                        net_pay = VALUES(net_pay),
                        ctc = VALUES(ctc),
                        updated_at = NOW()",
                    [
                        'period_id' => $periodId,
                        'emp_code' => $emp['employee_code'],
                        'unit_id' => $unitId,
                        'total_days' => $totalDays,
                        'paid_days' => $paidDays,
                        'unpaid_days' => $unpaidDays,
                        'ot_hours' => $overtimeHours,
                        'basic' => $basic,
                        'da' => $da,
                        'hra' => $hra,
                        'conveyance' => $conveyance,
                        'medical' => $medicalAllowance,
                        'special' => $specialAllowance,
                        'other' => $otherAllowance,
                        'ot_amount' => $overtimeAmount,
                        'gross' => $grossEarnings,
                        'pf_emp' => $pfEmployee,
                        'esi_emp' => $esiEmployee,
                        'pt' => $pt,
                        'lwf_emp' => $lwfEmployee,
                        'total_ded' => $totalDeductions,
                        'pf_empr' => $pfEmployer,
                        'eps_empr' => $epsEmployer,
                        'edlis_empr' => $edlisEmployer,
                        'epf_admin' => $epfAdmin,
                        'esi_empr' => $esiEmployer,
                        'lwf_empr' => $lwfEmployer,
                        'bonus' => $bonusProvision,
                        'gratuity' => $gratuityProvision,
                        'employer_contrib' => $employerContribution,
                        'net_pay' => $netPay,
                        'gross_salary' => $grossWithOT,
                        'ctc' => $ctc
                    ]
                );
                
                $processed++;
                $totalGross += $grossWithOT;
                $totalNet += $netPay;
                $totalPF += $pfEmployee + $pfEmployer;
                $totalESI += $esiEmployee + $esiEmployer;
                
            } catch (Exception $e) {
                $errors[] = "Error processing employee {$emp['employee_code']}: " . $e->getMessage();
            }
        }
        
        // Update period status
        $this->db->update('payroll_periods', [
            'status' => 'Processed',
            'processed_by' => $_SESSION['user_id'] ?? null,
            'processed_at' => date('Y-m-d H:i:s')
        ], SQL_WHERE_ID, ['id' => $periodId]);
        
        return [
            'success' => true,
            'message' => "Payroll processed for $processed employees.",
            'processed' => $processed,
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'total_pf' => $totalPF,
            'total_esi' => $totalESI,
            'errors' => $errors
        ];
    }
    
    // Calculate Professional Tax (simplified for common states)
    private function calculatePT($gross) {
        // Standard PT slabs (this should ideally come from database)
        if ($gross >= 15000) {
            return 200; // Most states have ₹200/month for salary above ₹15000
        } elseif ($gross >= 12000) {
            return 150;
        } elseif ($gross >= 9000) {
            return 100;
        } elseif ($gross >= 6000) {
            return 80;
        }
        return 0;
    }
    
    // Create payroll period
    public function createPeriod($month, $year) {
        $exists = $this->db->fetch(
            "SELECT id FROM payroll_periods WHERE month = :month AND year = :year",
            ['month' => $month, 'year' => $year]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Payroll period already exists.'];
        }
        
        $startDate = date('Y-m-01', strtotime("$year-$month-01"));
        $endDate = date('Y-m-t', strtotime("$year-$month-01"));
        $periodName = date('F Y', strtotime("$year-$month-01"));
        
        $id = $this->db->insert('payroll_periods', [
            'period_name' => $periodName,
            'month' => $month,
            'year' => $year,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pay_days' => date('t', strtotime("$year-$month-01")),
            'status' => 'Draft'
        ]);
        
        return ['success' => true, 'message' => 'Payroll period created.', 'period_id' => $id];
    }
    
    // Approve payroll period
    public function approvePayroll($periodId, $approvedBy) {
        $result = $this->db->update('payroll_periods', [
            'status' => 'Approved',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s')
        ], SQL_WHERE_ID, ['id' => $periodId]);
        
        // Update all payroll records status
        $this->db->query(
            "UPDATE payroll SET status = 'Approved' WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        );
        
        return ['success' => true, 'message' => 'Payroll approved.'];
    }
    
    // Mark payroll as paid
    public function markAsPaid($periodId, $paymentDate) {
        $this->db->update('payroll_periods', [
            'status' => 'Paid',
            'payment_date' => $paymentDate
        ], SQL_WHERE_ID, ['id' => $periodId]);
        
        $this->db->query(
            "UPDATE payroll SET status = 'Paid', payment_status = 'Paid' WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        );
        
        return ['success' => true, 'message' => 'Payroll marked as paid.'];
    }
    
    // Get bank advice data
    public function getBankAdvice($periodId) {
        return $this->db->fetchAll(
            "SELECT p.employee_id, p.net_pay, e.full_name, e.bank_name, e.account_number, e.ifsc_code, e.account_holder_name
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             WHERE p.payroll_period_id = :period_id 
             AND p.payment_mode = 'Bank Transfer'
             AND p.net_pay > 0
             ORDER BY e.bank_name, e.full_name",
            ['period_id' => $periodId]
        );
    }
    
    // Get payslip data
    public function getPayslip($periodId, $employeeCode) {
        $payslip = $this->db->fetch(
            "SELECT p.*, pp.period_name, pp.month, pp.year, pp.start_date, pp.end_date,
                    e.full_name, e.employee_code, e.designation, e.department,
                    e.client_name, e.unit_name, e.date_of_joining,
                    e.pf_account as uan_number, e.esic_number,
                    e.bank_name, e.account_number, e.ifsc_code
             FROM payroll p
             JOIN payroll_periods pp ON p.payroll_period_id = pp.id
             JOIN employees e ON p.employee_id = e.employee_code
             WHERE p.payroll_period_id = :period_id AND p.employee_id = :emp_code",
            ['period_id' => $periodId, 'emp_code' => $employeeCode]
        );
        
        return $payslip;
    }
    
    // Get payroll summary for period
    public function getPeriodSummary($periodId) {
        return $this->db->fetch(
            "SELECT 
                COUNT(*) as employee_count,
                SUM(total_days) as total_days,
                SUM(paid_days) as total_paid_days,
                SUM(gross_earnings) as total_gross,
                SUM(overtime_amount) as total_overtime,
                SUM(pf_employee) as total_pf_employee,
                SUM(esi_employee) as total_esi_employee,
                SUM(professional_tax) as total_pt,
                SUM(total_deductions) as total_deductions,
                SUM(pf_employer) as total_pf_employer,
                SUM(eps_employer) as total_eps_employer,
                SUM(esi_employer) as total_esi_employer,
                SUM(total_employer_contribution) as total_employer_contribution,
                SUM(net_pay) as total_net_pay,
                SUM(ctc) as total_ctc
             FROM payroll 
             WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        );
    }
    
    // Get salary register data
    public function getSalaryRegister($periodId, $filters = []) {
        $sql = "SELECT p.*, e.full_name, e.designation, e.client_name, e.unit_name, e.worker_category
                FROM payroll p
                JOIN employees e ON p.employee_id = e.employee_code
                WHERE p.payroll_period_id = :period_id";
        $params = ['period_id' => $periodId];
        
        if (!empty($filters['unit_name'])) {
            $sql .= SQL_FILTER_UNIT_NAME;
            $params['unit_name'] = $filters['unit_name'];
        }
        
        if (!empty($filters['client_name'])) {
            $sql .= SQL_FILTER_CLIENT_NAME;
            $params['client_name'] = $filters['client_name'];
        }
        
        $sql .= " ORDER BY e.client_name, e.unit_name, e.employee_code";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get PF return data (ECR format)
    public function getPFReturnData($periodId) {
        return $this->db->fetchAll(
            "SELECT e.employee_code as member_id, e.full_name, 
                    e.aadhaar_number, e.date_of_joining,
                    p.basic + p.da as epf_wages,
                    p.basic + p.da as eps_wages,
                    p.pf_employee as epf_contribution_remitted,
                    p.eps_employer as eps_contribution_remitted,
                    p.pf_employer + p.eps_employer as epf_eps_diff_remitted,
                    p.edlis_employer as edli_contribution,
                    p.epf_admin_charges as epf_admin_charges
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             JOIN employee_salary_structures ess ON e.id = ess.employee_id
             WHERE p.payroll_period_id = :period_id 
             AND ess.pf_applicable = 1
             ORDER BY e.employee_code",
            ['period_id' => $periodId]
        );
    }
    
    // Get ESI return data
    public function getESIReturnData($periodId) {
        return $this->db->fetchAll(
            "SELECT e.employee_code as ip_number, e.full_name, 
                    e.aadhaar_number, e.date_of_joining,
                    p.gross_earnings as total_wages,
                    p.esi_employee as employee_contribution,
                    p.esi_employer as employer_contribution,
                    e.esic_number
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             JOIN employee_salary_structures ess ON e.id = ess.employee_id
             WHERE p.payroll_period_id = :period_id 
             AND ess.esi_applicable = 1
             AND p.gross_earnings <= 21000
             ORDER BY e.employee_code",
            ['period_id' => $periodId]
        );
    }
    
    // Delete payroll for period
    public function deletePayroll($periodId) {
        // Delete payroll records
        $this->db->query("DELETE FROM payroll WHERE payroll_period_id = :period_id", ['period_id' => $periodId]);
        
        // Reset period status
        $this->db->update('payroll_periods', [
            'status' => 'Draft',
            'processed_by' => null,
            'processed_at' => null
        ], SQL_WHERE_ID, ['id' => $periodId]);
        
        return ['success' => true, 'message' => 'Payroll deleted.'];
    }
}
?>
