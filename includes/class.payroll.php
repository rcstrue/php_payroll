<?php
/**
 * RCS HRMS Pro - Payroll Class
 * Handles payroll processing and management
 * Updated for new database schema with proper statutory calculations
 * Version: 2.2.0 - Enhanced with selective processing, hold/release, freeze, and exceptions
 * 
 * IMPORTANT NOTES FOR DEVELOPERS:
 * =================================
 * 1. Always use client_id and unit_id for filtering (NOT client_name/unit_name)
 * 2. Use JOINs to get client/unit names from their respective tables
 * 3. clients table uses 'name' column (not 'client_name')
 * 4. units table uses 'name' column (not 'unit_name')
 * 5. employees table has client_id and unit_id as foreign keys
 * 6. AADHAAR NUMBER SHOULD NEVER BE HIDDEN IN EMPLOYEE VIEW
 *    - maskAadhaar() should only be used for external reports/payslips
 *    - Internal views must show full Aadhaar number
 * 7. Status Flow: Draft -> Processed -> Approved -> Paid/Frozen
 * 8. Frozen status prevents all modifications
 * 9. salary_hold prevents individual from being paid
 * 10. payroll_dirty flag indicates recalculation needed
 */

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
        $sql = "SELECT pp.*, 
                       COUNT(p.id) as employee_count,
                       SUM(CASE WHEN p.salary_hold = 1 THEN 1 ELSE 0 END) as hold_count
                FROM payroll_periods pp
                LEFT JOIN payroll p ON pp.id = p.payroll_period_id
                WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND pp.status = :status";
            $params['status'] = $status;
        }

        $sql .= " GROUP BY pp.id ORDER BY pp.year DESC, pp.month DESC";

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
                SUM(total_employer_contribution) as total_employer_contribution,
                SUM(CASE WHEN salary_hold = 1 THEN 1 ELSE 0 END) as held_count,
                SUM(CASE WHEN payroll_dirty = 1 THEN 1 ELSE 0 END) as dirty_count
            FROM payroll
            WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        ) ?: [
            'total_employees' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'total_employer_contribution' => 0,
            'held_count' => 0,
            'dirty_count' => 0
        ];
    }

    // Get payroll for period with filters
    public function getPayrollReport($periodId, $filters = []) {
        $sql = "SELECT p.*,
                       e.employee_code, e.full_name, e.designation, e.department,
                       e.bank_name, e.account_number, e.ifsc_code,
                       c.name as client_name, u.name as unit_name
                FROM payroll p
                JOIN employees e ON p.employee_id = e.employee_code
                LEFT JOIN clients c ON e.client_id = c.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE p.payroll_period_id = :period_id";
        $params = ['period_id' => $periodId];

        // Apply filters
        if (!empty($filters['unit_id'])) {
            $sql .= " AND e.unit_id = :unit_id";
            $params['unit_id'] = $filters['unit_id'];
        }

        if (!empty($filters['client_id'])) {
            $sql .= " AND e.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :pstatus";
            $params['pstatus'] = $filters['status'];
        }

        if (!empty($filters['salary_hold'])) {
            $sql .= " AND p.salary_hold = :salary_hold";
            $params['salary_hold'] = $filters['salary_hold'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.full_name LIKE :search OR e.employee_code LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY c.name, u.name, e.employee_code";

        return $this->db->fetchAll($sql, $params);
    }

    // Get employee payroll
    public function getEmployeePayroll($employeeCode, $periodId = null) {
        if ($periodId) {
            return $this->db->fetch(
                "SELECT p.*, e.full_name, e.designation,
                        c.name as client_name, u.name as unit_name
                 FROM payroll p
                 JOIN employees e ON p.employee_id = e.employee_code
                 LEFT JOIN clients c ON e.client_id = c.id
                 LEFT JOIN units u ON e.unit_id = u.id
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

    /**
     * Process payroll for period with selective filters
     * 
     * @param int $periodId The payroll period ID
     * @param array $filters Optional filters for selective processing
     *                       - client_id: Process only this client's employees
     *                       - unit_id: Process only this unit's employees
     *                       - employee_codes: Array of specific employee codes
     *                       - recalculate_dirty: Only recalculate dirty records
     * @return array Result with success status and processed count
     */
    public function processPayroll($periodId, $filters = []) {
        // Get period info
        $period = $this->db->fetch(
            "SELECT * FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if (!$period) {
            return ['success' => false, 'message' => 'Payroll period not found.'];
        }

        // Check if period is frozen
        if ($period['status'] === 'Frozen' || $period['status'] === 'Locked') {
            return ['success' => false, 'message' => 'Payroll period is frozen. Cannot process.'];
        }

        // Ensure payroll table has extra_days_amount column
        try {
            $checkColumn = $this->db->fetch("SHOW COLUMNS FROM payroll LIKE 'extra_days_amount'");
            if (!$checkColumn) {
                $this->db->exec("ALTER TABLE payroll ADD COLUMN extra_days_amount DECIMAL(10,2) DEFAULT 0.00 AFTER overtime_amount");
            }
        } catch (Exception $e) {
            // Column might already exist or table might not have records
        }

        // Build employee query with filters
        $sql = "SELECT e.id, e.employee_code, e.full_name, e.date_of_joining,
                       e.date_of_leaving, e.client_id, e.unit_id, e.worker_category,
                       e.bank_name, e.account_number, e.ifsc_code,
                       c.name as client_name, u.name as unit_name,
                       ess.basic_wage, ess.da, ess.hra, ess.conveyance,
                       ess.medical_allowance, ess.special_allowance, ess.other_allowance,
                       ess.gross_salary, ess.pf_applicable, ess.esi_applicable,
                       ess.pt_applicable, ess.lwf_applicable, ess.overtime_applicable,
                       ess.bonus_applicable, ess.gratuity_applicable
                FROM employees e
                INNER JOIN employee_salary_structures ess ON e.id = ess.employee_id
                    AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                LEFT JOIN clients c ON e.client_id = c.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE e.status = 'approved'";

        $params = [];

        // Apply filters for selective processing
        if (!empty($filters['client_id'])) {
            $sql .= " AND e.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        if (!empty($filters['unit_id'])) {
            $sql .= " AND e.unit_id = :unit_id";
            $params['unit_id'] = $filters['unit_id'];
        }

        if (!empty($filters['employee_codes']) && is_array($filters['employee_codes'])) {
            $placeholders = implode(',', array_fill(0, count($filters['employee_codes']), '?'));
            $sql .= " AND e.employee_code IN ($placeholders)";
            $params = array_merge($params, $filters['employee_codes']);
        }

        // Only recalculate dirty records if specified
        if (!empty($filters['recalculate_dirty'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM payroll p 
                WHERE p.employee_id = e.employee_code 
                AND p.payroll_period_id = :period_id_check
                AND p.payroll_dirty = 1
            )";
            $params['period_id_check'] = $periodId;
        }

        $employees = $this->db->fetchAll($sql, $params);

        $processed = 0;
        $errors = [];
        $exceptions = [];
        $totalGross = 0;
        $totalNet = 0;
        $totalPF = 0;
        $totalESI = 0;

        $this->db->beginTransaction();

        try {
            foreach ($employees as $emp) {
                try {
                    // Check for exceptions
                    $exceptionType = null;
                    
                    // Check if employee joined after period end
                    $periodEndDate = $period['end_date'];
                    if ($emp['date_of_joining'] && strtotime($emp['date_of_joining']) > strtotime($periodEndDate)) {
                        continue;
                    }

                    // Check for missing salary structure
                    if (empty($emp['basic_wage']) && empty($emp['gross_salary'])) {
                        $exceptionType = 'Undefined Salary';
                        $exceptions[] = [
                            'employee_id' => $emp['employee_code'],
                            'employee_name' => $emp['full_name'],
                            'type' => 'Undefined Salary',
                            'message' => 'No salary structure defined'
                        ];
                    }

                    // Check for missing bank details
                    if (empty($emp['bank_name']) || empty($emp['account_number']) || empty($emp['ifsc_code'])) {
                        if (!$exceptionType) {
                            $exceptionType = 'Missing Bank Details';
                        }
                        $exceptions[] = [
                            'employee_id' => $emp['employee_code'],
                            'employee_name' => $emp['full_name'],
                            'type' => 'Missing Bank Details',
                            'message' => 'Bank account details incomplete'
                        ];
                    }

                    // Get attendance summary from attendance_summary table
                    $attendance = $this->db->fetch(
                        "SELECT
                            total_present as present_days,
                            total_extra,
                            overtime_hours,
                            total_wo as weekly_offs
                        FROM attendance_summary
                        WHERE employee_id = :emp_id
                        AND month = :month
                        AND year = :year",
                        ['emp_id' => $emp['id'], 'month' => $period['month'], 'year' => $period['year']]
                    );

                    $totalDays = $period['pay_days'] ?? 30;
                    $extraDaysWorked = floatval($attendance['total_extra'] ?? 0);
                    
                    // attendance_summary has total_present which includes worked days
                    $paidDays = floatval($attendance['total_present'] ?? 0);
                    // Add weekly offs to paid days
                    $paidDays += floatval($attendance['weekly_offs'] ?? 0);
                    
                    // Check if attendance record exists
                    $hasAttendance = $attendance !== false && ($attendance['total_present'] !== null);

                    // Check for missing attendance
                    if (!$hasAttendance) {
                        // Assume full month if no attendance data
                        $paidDays = $totalDays;
                        $extraDaysWorked = 0;
                        // Log this as potential exception but don't block processing
                        $exceptions[] = [
                            'employee_id' => $emp['employee_code'],
                            'employee_name' => $emp['full_name'],
                            'type' => 'Missing Attendance',
                            'message' => 'No attendance data found, assuming full month'
                        ];
                    }

                    $unpaidDays = max(0, $totalDays - $paidDays);
                    $overtimeHours = floatval($attendance['overtime_hours'] ?? 0);

                    // Calculate earnings (pro-rated)
                    $basic = round(($emp['basic_wage'] ?? 0) * $paidDays / $totalDays, 2);
                    $da = round(($emp['da'] ?? 0) * $paidDays / $totalDays, 2);
                    $hra = round(($emp['hra'] ?? 0) * $paidDays / $totalDays, 2);
                    $conveyance = round(($emp['conveyance'] ?? 0) * $paidDays / $totalDays, 2);
                    $medicalAllowance = round(($emp['medical_allowance'] ?? 0) * $paidDays / $totalDays, 2);
                    $specialAllowance = round(($emp['special_allowance'] ?? 0) * $paidDays / $totalDays, 2);
                    $otherAllowance = round(($emp['other_allowance'] ?? 0) * $paidDays / $totalDays, 2);

                    $grossEarnings = $basic + $da + $hra + $conveyance + $medicalAllowance + $specialAllowance + $otherAllowance;

                    // Calculate extra days payment (extra days worked beyond normal days)
                    $extraDaysAmount = 0;
                    if ($extraDaysWorked > 0) {
                        $dailyRate = ($emp['basic_wage'] + $emp['da'] + $emp['hra']) / $totalDays;
                        $extraDaysAmount = round($dailyRate * $extraDaysWorked, 2);
                    }

                    // Calculate overtime amount (based on basic+da per hour * 2)
                    $overtimeAmount = 0;
                    if ($overtimeHours > 0 && ($emp['overtime_applicable'] ?? 0)) {
                        $hourlyRate = ($emp['basic_wage'] + $emp['da']) / 30 / 8;
                        $overtimeAmount = round($hourlyRate * $overtimeHours * 2, 2);
                    }

                    $grossWithOT = $grossEarnings + $overtimeAmount + $extraDaysAmount;

                    // Calculate PF
                    $pfEmployee = 0;
                    $pfEmployer = 0;
                    $epsEmployer = 0;
                    $edlisEmployer = 0;
                    $epfAdmin = 0;

                    if ($emp['pf_applicable'] ?? 0) {
                        $pfBase = min($basic + $da, $this->pfRates['wage_ceiling']);
                        $pfEmployee = round($pfBase * $this->pfRates['employee_share'] / 100, 2);
                        $pfEmployer = round($pfBase * $this->pfRates['employer_pf'] / 100, 2);
                        $epsEmployer = round($pfBase * $this->pfRates['employer_eps'] / 100, 2);
                        $edlisEmployer = round($pfBase * $this->pfRates['employer_edlis'] / 100, 2);
                        $epfAdmin = round($pfBase * $this->pfRates['epf_admin'] / 100, 2);
                    }

                    // Calculate ESI - Check against actual gross salary (not pro-rated)
                    $esiEmployee = 0;
                    $esiEmployer = 0;
                    $actualGrossSalary = floatval($emp['gross_salary'] ?? 0);

                    if (($emp['esi_applicable'] ?? 0) && $actualGrossSalary <= $this->esiRates['wage_ceiling']) {
                        $esiEmployee = round($grossWithOT * $this->esiRates['employee_share'] / 100, 2);
                        $esiEmployer = round($grossWithOT * $this->esiRates['employer_share'] / 100, 2);
                    }

                    // Calculate Professional Tax
                    $pt = 0;
                    if ($emp['pt_applicable'] ?? 0) {
                        $pt = $this->calculatePT($grossWithOT);
                    }

                    // Calculate LWF (Labour Welfare Fund)
                    $lwfEmployee = 0;
                    $lwfEmployer = 0;
                    if ($emp['lwf_applicable'] ?? 0) {
                        // State-specific LWF calculation can be added here
                        $lwfEmployee = 0;
                        $lwfEmployer = 0;
                    }

                    // Get advances for the period
                    $advance = $this->db->fetch(
                        "SELECT COALESCE(SUM(adv1 + adv2 + office_advance + dress_advance), 0) as total_advance
                        FROM employee_advances
                        WHERE employee_id = :emp_id AND month = :month AND year = :year",
                        ['emp_id' => $emp['id'], 'month' => $period['month'], 'year' => $period['year']]
                    );
                    $salaryAdvance = $advance['total_advance'] ?? 0;

                    // Total deductions
                    $totalDeductions = $pfEmployee + $esiEmployee + $pt + $lwfEmployee + $salaryAdvance;

                    // Net pay
                    $netPay = $grossWithOT - $totalDeductions;

                    // Employer contributions
                    $employerContribution = $pfEmployer + $epsEmployer + $edlisEmployer + $epfAdmin + $esiEmployer + $lwfEmployer;

                    // Bonus provision (8.33% of basic, max ₹7000)
                    $bonusProvision = 0;
                    if ($emp['bonus_applicable'] ?? 0) {
                        $bonusBase = min($basic, 7000);
                        $bonusProvision = round($bonusBase * 8.33 / 100, 2);
                    }

                    // Gratuity provision (4.81% of basic)
                    $gratuityProvision = 0;
                    if ($emp['gratuity_applicable'] ?? 0) {
                        $gratuityProvision = round($basic * 4.81 / 100, 2);
                    }

                    // CTC
                    $ctc = $grossWithOT + $employerContribution + $bonusProvision + $gratuityProvision;

                    // Insert or update payroll record
                    $this->db->query(
                        "INSERT INTO payroll (
                            payroll_period_id, employee_id, unit_id,
                            total_days, paid_days, unpaid_days, overtime_hours,
                            basic, da, hra, conveyance, medical_allowance, special_allowance, other_allowance,
                            overtime_amount, extra_days_amount, gross_earnings,
                            pf_employee, esi_employee, professional_tax, lwf_employee,
                            salary_advance, total_deductions,
                            pf_employer, eps_employer, edlis_employer, epf_admin_charges, esi_employer, lwf_employer,
                            bonus_provision, gratuity_provision,
                            total_employer_contribution,
                            net_pay, gross_salary, ctc, status, 
                            salary_hold, exception_type, payroll_dirty,
                            last_calculated_at, calculated_by, created_at
                        ) VALUES (
                            :period_id, :emp_code, :unit_id,
                            :total_days, :paid_days, :unpaid_days, :ot_hours,
                            :basic, :da, :hra, :conveyance, :medical, :special, :other,
                            :ot_amount, :extra_days_amount, :gross,
                            :pf_emp, :esi_emp, :pt, :lwf_emp,
                            :salary_adv, :total_ded,
                            :pf_empr, :eps_empr, :edlis_empr, :epf_admin, :esi_empr, :lwf_empr,
                            :bonus, :gratuity,
                            :employer_contrib,
                            :net_pay, :gross_salary, :ctc, 'Processed',
                            0, :exception_type, 0,
                            NOW(), :user_id, NOW()
                        )
                        ON DUPLICATE KEY UPDATE
                            paid_days = VALUES(paid_days),
                            overtime_hours = VALUES(overtime_hours),
                            overtime_amount = VALUES(overtime_amount),
                            extra_days_amount = VALUES(extra_days_amount),
                            gross_earnings = VALUES(gross_earnings),
                            gross_salary = VALUES(gross_salary),
                            total_deductions = VALUES(total_deductions),
                            net_pay = VALUES(net_pay),
                            ctc = VALUES(ctc),
                            payroll_dirty = 0,
                            exception_type = VALUES(exception_type),
                            last_calculated_at = NOW(),
                            calculated_by = VALUES(calculated_by),
                            updated_at = NOW()",
                        [
                            'period_id' => $periodId,
                            'emp_code' => $emp['employee_code'],
                            'unit_id' => $emp['unit_id'],
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
                            'extra_days_amount' => $extraDaysAmount,
                            'gross' => $grossEarnings,
                            'pf_emp' => $pfEmployee,
                            'esi_emp' => $esiEmployee,
                            'pt' => $pt,
                            'lwf_emp' => $lwfEmployee,
                            'salary_adv' => $salaryAdvance,
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
                            'ctc' => $ctc,
                            'exception_type' => $exceptionType,
                            'user_id' => $_SESSION['user_id'] ?? null
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

            // Store exceptions
            foreach ($exceptions as $exception) {
                $this->logException($periodId, $exception['employee_id'], $exception['type'], $exception['message']);
            }

            // Update period status
            $this->db->update('payroll_periods', [
                'status' => 'Processed',
                'processed_by' => $_SESSION['user_id'] ?? null,
                'processed_at' => date('Y-m-d H:i:s'),
                'exception_count' => count($exceptions)
            ], SQL_WHERE_ID, ['id' => $periodId]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Payroll processed for $processed employees.",
                'processed' => $processed,
                'total_gross' => $totalGross,
                'total_net' => $totalNet,
                'total_pf' => $totalPF,
                'total_esi' => $totalESI,
                'exceptions' => $exceptions,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Payroll processing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Recalculate payroll for specific employees
     * 
     * @param int $periodId The payroll period ID
     * @param array $employeeCodes Array of employee codes to recalculate
     * @return array Result with success status
     */
    public function recalculatePayroll($periodId, $employeeCodes = []) {
        // If no specific employees, recalculate all dirty records
        if (empty($employeeCodes)) {
            $dirtyRecords = $this->db->fetchAll(
                "SELECT employee_id FROM payroll 
                WHERE payroll_period_id = :period_id AND payroll_dirty = 1",
                ['period_id' => $periodId]
            );
            $employeeCodes = array_column($dirtyRecords, 'employee_id');
        }

        if (empty($employeeCodes)) {
            return ['success' => true, 'message' => 'No records to recalculate.', 'processed' => 0];
        }

        return $this->processPayroll($periodId, ['employee_codes' => $employeeCodes]);
    }

    /**
     * Hold salary for specific employees
     * 
     * @param int $periodId The payroll period ID
     * @param array $employeeCodes Array of employee codes
     * @param string $reason Reason for holding salary
     * @return array Result with success status
     */
    public function holdSalary($periodId, $employeeCodes, $reason = '') {
        // Check if period is frozen
        $period = $this->db->fetch(
            "SELECT status FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if (!$period || in_array($period['status'], ['Frozen', 'Locked', 'Paid'])) {
            return ['success' => false, 'message' => 'Cannot hold salary for this period.'];
        }

        $placeholders = implode(',', array_fill(0, count($employeeCodes), '?'));
        $params = array_merge([$periodId], $employeeCodes);

        $this->db->query(
            "UPDATE payroll SET 
                salary_hold = 1, 
                hold_reason = ?, 
                hold_date = CURDATE(),
                status = 'Hold'
            WHERE payroll_period_id = ? AND employee_id IN ($placeholders)",
            array_merge([$reason], $params)
        );

        // Update hold count in period
        $holdCount = $this->db->fetch(
            "SELECT COUNT(*) as count FROM payroll WHERE payroll_period_id = ? AND salary_hold = 1",
            [$periodId]
        );

        $this->db->update('payroll_periods', [
            'hold_count' => $holdCount['count']
        ], SQL_WHERE_ID, ['id' => $periodId]);

        return ['success' => true, 'message' => 'Salary hold applied successfully.'];
    }

    /**
     * Release held salary for specific employees
     * 
     * @param int $periodId The payroll period ID
     * @param array $employeeCodes Array of employee codes
     * @return array Result with success status
     */
    public function releaseSalary($periodId, $employeeCodes) {
        // Check if period is frozen
        $period = $this->db->fetch(
            "SELECT status FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if (!$period || in_array($period['status'], ['Frozen', 'Locked'])) {
            return ['success' => false, 'message' => 'Cannot release salary for this period.'];
        }

        $placeholders = implode(',', array_fill(0, count($employeeCodes), '?'));
        $params = array_merge([$periodId], $employeeCodes);

        $this->db->query(
            "UPDATE payroll SET 
                salary_hold = 0, 
                released_date = CURDATE(),
                status = 'Processed'
            WHERE payroll_period_id = ? AND employee_id IN ($placeholders)",
            $params
        );

        // Update hold count in period
        $holdCount = $this->db->fetch(
            "SELECT COUNT(*) as count FROM payroll WHERE payroll_period_id = ? AND salary_hold = 1",
            [$periodId]
        );

        $this->db->update('payroll_periods', [
            'hold_count' => $holdCount['count']
        ], SQL_WHERE_ID, ['id' => $periodId]);

        return ['success' => true, 'message' => 'Salary released successfully.'];
    }

    /**
     * Freeze payroll period - prevents all modifications
     * 
     * @param int $periodId The payroll period ID
     * @return array Result with success status
     */
    public function freezePeriod($periodId) {
        $period = $this->db->fetch(
            "SELECT status FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if (!$period) {
            return ['success' => false, 'message' => 'Payroll period not found.'];
        }

        if (in_array($period['status'], ['Frozen', 'Locked'])) {
            return ['success' => false, 'message' => 'Payroll period is already frozen.'];
        }

        // Update period status
        $this->db->update('payroll_periods', [
            'status' => 'Frozen',
            'frozen_at' => date('Y-m-d H:i:s'),
            'frozen_by' => $_SESSION['user_id'] ?? null
        ], SQL_WHERE_ID, ['id' => $periodId]);

        // Update all payroll records
        $this->db->query(
            "UPDATE payroll SET status = 'Frozen' WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        );

        return ['success' => true, 'message' => 'Payroll period frozen successfully.'];
    }

    /**
     * Unfreeze payroll period
     * 
     * @param int $periodId The payroll period ID
     * @return array Result with success status
     */
    public function unfreezePeriod($periodId) {
        $period = $this->db->fetch(
            "SELECT status FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if (!$period) {
            return ['success' => false, 'message' => 'Payroll period not found.'];
        }

        // Update period status back to Approved
        $this->db->update('payroll_periods', [
            'status' => 'Approved',
            'frozen_at' => null,
            'frozen_by' => null
        ], SQL_WHERE_ID, ['id' => $periodId]);

        // Update all payroll records
        $this->db->query(
            "UPDATE payroll SET status = 'Approved' WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        );

        return ['success' => true, 'message' => 'Payroll period unfrozen successfully.'];
    }

    /**
     * Mark payroll as paid
     * 
     * @param int $periodId The payroll period ID
     * @param string $paymentDate The payment date
     * @param array $excludeHeld Whether to exclude held salaries
     * @return array Result with success status
     */
    public function markAsPaid($periodId, $paymentDate = null, $excludeHeld = true) {
        if (!$paymentDate) {
            $paymentDate = date('Y-m-d');
        }

        // Check if period is frozen
        $period = $this->db->fetch(
            "SELECT status FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if ($period['status'] === 'Frozen' || $period['status'] === 'Locked') {
            return ['success' => false, 'message' => 'Cannot modify frozen payroll period.'];
        }

        // Update period
        $this->db->update('payroll_periods', [
            'status' => 'Paid',
            'payment_date' => $paymentDate
        ], SQL_WHERE_ID, ['id' => $periodId]);

        // Update payroll records (exclude held if requested)
        $whereClause = $excludeHeld 
            ? "payroll_period_id = :period_id AND salary_hold = 0"
            : "payroll_period_id = :period_id";

        $this->db->query(
            "UPDATE payroll SET status = 'Paid', payment_status = 'Paid' WHERE $whereClause",
            ['period_id' => $periodId]
        );

        return ['success' => true, 'message' => 'Payroll marked as paid.'];
    }

    /**
     * Get payroll exceptions for a period
     * 
     * @param int $periodId The payroll period ID
     * @return array List of exceptions
     */
    public function getExceptions($periodId) {
        return $this->db->fetchAll(
            "SELECT pe.*, e.full_name, e.employee_code, c.name as client_name, u.name as unit_name
             FROM payroll_exceptions pe
             JOIN employees e ON pe.employee_id = e.employee_code
             LEFT JOIN clients c ON e.client_id = c.id
             LEFT JOIN units u ON e.unit_id = u.id
             WHERE pe.payroll_period_id = :period_id AND pe.is_resolved = 0
             ORDER BY pe.exception_type, e.full_name",
            ['period_id' => $periodId]
        );
    }

    /**
     * Log an exception
     */
    private function logException($periodId, $employeeId, $type, $message) {
        try {
            $this->db->insert('payroll_exceptions', [
                'payroll_period_id' => $periodId,
                'employee_id' => $employeeId,
                'exception_type' => $type,
                'exception_message' => $message,
                'is_resolved' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Ignore duplicate exceptions
        }
    }

    /**
     * Resolve an exception
     */
    public function resolveException($exceptionId) {
        return $this->db->update('payroll_exceptions', [
            'is_resolved' => 1,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $_SESSION['user_id'] ?? null
        ], SQL_WHERE_ID, ['id' => $exceptionId]);
    }

    /**
     * Mark payroll as dirty (needs recalculation)
     */
    public function markDirty($employeeCode, $periodId, $reason = '') {
        return $this->db->query(
            "UPDATE payroll SET payroll_dirty = 1, dirty_reason = ? 
            WHERE employee_id = ? AND payroll_period_id = ?",
            [$reason, $employeeCode, $periodId]
        );
    }

    /**
     * Get client-wise payroll summary
     */
    public function getClientWiseSummary($periodId) {
        return $this->db->fetchAll(
            "SELECT c.id, c.name as client_name,
                    COUNT(p.id) as employee_count,
                    SUM(p.gross_earnings) as total_gross,
                    SUM(p.total_deductions) as total_deductions,
                    SUM(p.net_pay) as total_net,
                    SUM(p.total_employer_contribution) as total_employer_contribution,
                    SUM(CASE WHEN p.salary_hold = 1 THEN 1 ELSE 0 END) as hold_count
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             JOIN clients c ON e.client_id = c.id
             WHERE p.payroll_period_id = :period_id
             GROUP BY c.id, c.name
             ORDER BY total_net DESC",
            ['period_id' => $periodId]
        );
    }

    /**
     * Get unit-wise payroll summary
     */
    public function getUnitWiseSummary($periodId) {
        return $this->db->fetchAll(
            "SELECT u.id, u.name as unit_name, c.name as client_name,
                    COUNT(p.id) as employee_count,
                    SUM(p.gross_earnings) as total_gross,
                    SUM(p.total_deductions) as total_deductions,
                    SUM(p.net_pay) as total_net,
                    SUM(p.total_employer_contribution) as total_employer_contribution,
                    SUM(CASE WHEN p.salary_hold = 1 THEN 1 ELSE 0 END) as hold_count
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             JOIN units u ON e.unit_id = u.id
             LEFT JOIN clients c ON e.client_id = c.id
             WHERE p.payroll_period_id = :period_id
             GROUP BY u.id, u.name, c.name
             ORDER BY c.name, total_net DESC",
            ['period_id' => $periodId]
        );
    }

    /**
     * Get bank advice data
     */
    public function getBankAdvice($periodId) {
        return $this->db->fetchAll(
            "SELECT p.employee_id, p.net_pay, e.full_name, e.bank_name, e.account_number, e.ifsc_code, e.account_holder_name
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             WHERE p.payroll_period_id = :period_id
             AND p.payment_mode = 'Bank Transfer'
             AND p.net_pay > 0
             AND p.salary_hold = 0
             ORDER BY e.bank_name, e.full_name",
            ['period_id' => $periodId]
        );
    }

    /**
     * Get NEFT format data for bank transfer
     */
    public function getNEFTData($periodId) {
        return $this->db->fetchAll(
            "SELECT 
                e.account_number as 'Beneficiary Account No',
                e.ifsc_code as 'IFSC Code',
                e.full_name as 'Beneficiary Name',
                p.net_pay as 'Amount',
                'NEFT' as 'Payment Mode',
                CONCAT('SAL-', DATE_FORMAT(CURDATE(), '%m%Y'), '-', p.employee_id) as 'Reference No',
                '' as 'Remarks'
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             WHERE p.payroll_period_id = :period_id
             AND p.payment_mode = 'Bank Transfer'
             AND p.net_pay > 0
             AND p.salary_hold = 0
             AND e.account_number IS NOT NULL
             AND e.ifsc_code IS NOT NULL
             ORDER BY e.ifsc_code, e.full_name",
            ['period_id' => $periodId]
        );
    }

    /**
     * Get payslip data
     */
    public function getPayslip($periodId, $employeeCode) {
        return $this->db->fetch(
            "SELECT p.*, pp.period_name, pp.month, pp.year, pp.start_date, pp.end_date,
                    e.full_name, e.employee_code, e.designation, e.department,
                    c.name as client_name, u.name as unit_name, e.date_of_joining,
                    e.uan_number, e.esic_number,
                    e.bank_name, e.account_number, e.ifsc_code
             FROM payroll p
             JOIN payroll_periods pp ON p.payroll_period_id = pp.id
             JOIN employees e ON p.employee_id = e.employee_code
             LEFT JOIN clients c ON e.client_id = c.id
             LEFT JOIN units u ON e.unit_id = u.id
             WHERE p.payroll_period_id = :period_id AND p.employee_id = :emp_code",
            ['period_id' => $periodId, 'emp_code' => $employeeCode]
        );
    }

    /**
     * Get payroll summary for period
     */
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
                SUM(salary_advance) as total_advance,
                SUM(total_deductions) as total_deductions,
                SUM(pf_employer) as total_pf_employer,
                SUM(eps_employer) as total_eps_employer,
                SUM(esi_employer) as total_esi_employer,
                SUM(edlis_employer) as edli_contribution,
                SUM(epf_admin_charges) as epf_admin_charges,
                SUM(total_employer_contribution) as total_employer_contribution,
                SUM(net_pay) as total_net_pay,
                SUM(ctc) as total_ctc,
                SUM(CASE WHEN salary_hold = 1 THEN 1 ELSE 0 END) as held_count,
                SUM(CASE WHEN payroll_dirty = 1 THEN 1 ELSE 0 END) as dirty_count
             FROM payroll
             WHERE payroll_period_id = :period_id",
            ['period_id' => $periodId]
        );
    }

    /**
     * Get salary register data
     */
    public function getSalaryRegister($periodId, $filters = []) {
        $sql = "SELECT p.*, e.full_name, e.designation, e.worker_category,
                       c.name as client_name, u.name as unit_name
                FROM payroll p
                JOIN employees e ON p.employee_id = e.employee_code
                LEFT JOIN clients c ON e.client_id = c.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE p.payroll_period_id = :period_id";
        $params = ['period_id' => $periodId];

        if (!empty($filters['unit_id'])) {
            $sql .= " AND e.unit_id = :unit_id";
            $params['unit_id'] = $filters['unit_id'];
        }

        if (!empty($filters['client_id'])) {
            $sql .= " AND e.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        $sql .= " ORDER BY c.name, u.name, e.employee_code";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Calculate Professional Tax (State-wise slabs)
     * Default slabs - can be overridden based on employee's work state
     * Common PT slabs across India (monthly)
     */
    private function calculatePT($gross, $state = 'MH') {
        // Maharashtra slabs (most common)
        if ($state === 'MH' || $state === 'Maharashtra') {
            if ($gross > 10000) {
                return 200;  // Fixed ₹200 for salary above ₹10,000
            }
            return 0;
        }
        
        // Karnataka slabs
        if ($state === 'KA' || $state === 'Karnataka') {
            if ($gross > 15000) {
                return 200;
            } elseif ($gross > 10000) {
                return 150;
            }
            return 0;
        }
        
        // Tamil Nadu slabs
        if ($state === 'TN' || $state === 'Tamil Nadu') {
            if ($gross > 75000) { // Half yearly threshold
                return 1250 / 6; // Approx monthly
            } elseif ($gross > 50000) {
                return 833 / 6;
            }
            return 0;
        }
        
        // Delhi slabs (no PT for most)
        if ($state === 'DL' || $state === 'Delhi') {
            if ($gross > 25000) {
                return 200;
            }
            return 0;
        }
        
        // Gujarat slabs
        if ($state === 'GJ' || $state === 'Gujarat') {
            if ($gross > 12000) {
                return 200;
            }
            return 0;
        }
        
        // Default: Maharashtra-style slab
        if ($gross > 10000) {
            return 200;
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
        // Check if frozen
        $period = $this->db->fetch(
            "SELECT status FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if (!$period || in_array($period['status'], ['Frozen', 'Locked'])) {
            return ['success' => false, 'message' => 'Cannot approve frozen payroll.'];
        }

        $this->db->update('payroll_periods', [
            'status' => 'Approved',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s')
        ], SQL_WHERE_ID, ['id' => $periodId]);

        // Update all payroll records status (except held ones)
        $this->db->query(
            "UPDATE payroll SET status = 'Approved' 
            WHERE payroll_period_id = :period_id AND salary_hold = 0",
            ['period_id' => $periodId]
        );

        return ['success' => true, 'message' => 'Payroll approved.'];
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
        // Check if frozen
        $period = $this->db->fetch(
            "SELECT status FROM payroll_periods WHERE id = :id",
            ['id' => $periodId]
        );

        if (!$period) {
            return ['success' => false, 'message' => 'Payroll period not found.'];
        }

        if (in_array($period['status'], ['Frozen', 'Locked', 'Paid'])) {
            return ['success' => false, 'message' => 'Cannot delete frozen or paid payroll.'];
        }

        // Delete payroll records
        $this->db->query("DELETE FROM payroll WHERE payroll_period_id = :period_id", ['period_id' => $periodId]);

        // Reset period status
        $this->db->update('payroll_periods', [
            'status' => 'Draft',
            'processed_by' => null,
            'processed_at' => null,
            'hold_count' => 0,
            'exception_count' => 0
        ], SQL_WHERE_ID, ['id' => $periodId]);

        return ['success' => true, 'message' => 'Payroll deleted.'];
    }

    /**
     * Get payroll detail for single employee (drill-down)
     */
    public function getPayrollDetail($periodId, $employeeCode) {
        $payroll = $this->db->fetch(
            "SELECT p.*, 
                    e.employee_code, e.full_name, e.designation, e.department,
                    e.date_of_joining, e.date_of_leaving, e.worker_category,
                    e.bank_name, e.account_number, e.ifsc_code, e.account_holder_name,
                    e.uan_number, e.esic_number, e.aadhaar_number,
                    c.name as client_name, u.name as unit_name,
                    pp.period_name, pp.month, pp.year, pp.start_date, pp.end_date
             FROM payroll p
             JOIN payroll_periods pp ON p.payroll_period_id = pp.id
             JOIN employees e ON p.employee_id = e.employee_code
             LEFT JOIN clients c ON e.client_id = c.id
             LEFT JOIN units u ON e.unit_id = u.id
             WHERE p.payroll_period_id = :period_id AND p.employee_id = :emp_code",
            ['period_id' => $periodId, 'emp_code' => $employeeCode]
        );

        if (!$payroll) {
            return null;
        }

        // Get attendance summary
        $payroll['attendance'] = $this->db->fetch(
            "SELECT
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'Weekly Off' THEN 1 ELSE 0 END) as weekly_offs,
                SUM(CASE WHEN status = 'Holiday' THEN 1 ELSE 0 END) as holidays,
                SUM(CASE WHEN status IN ('Paid Leave', 'Sick Leave', 'Casual Leave') THEN 1 ELSE 0 END) as paid_leaves,
                SUM(CASE WHEN status = 'Half Day' THEN 0.5 ELSE 0 END) as half_days
            FROM attendance
            WHERE employee_id = :emp_code
            AND MONTH(attendance_date) = :month
            AND YEAR(attendance_date) = :year",
            ['emp_code' => $employeeCode, 'month' => $payroll['month'], 'year' => $payroll['year']]
        );

        // Get advances
        $payroll['advances'] = $this->db->fetch(
            "SELECT adv1, adv2, office_advance, dress_advance, remarks
            FROM employee_advances
            WHERE employee_id = (SELECT id FROM employees WHERE employee_code = :emp_code)
            AND month = :month AND year = :year",
            ['emp_code' => $employeeCode, 'month' => $payroll['month'], 'year' => $payroll['year']]
        );

        // Get salary structure
        $payroll['salary_structure'] = $this->db->fetch(
            "SELECT * FROM employee_salary_structures
            WHERE employee_id = (SELECT id FROM employees WHERE employee_code = :emp_code)
            AND effective_from <= :end_date
            AND (effective_to IS NULL OR effective_to >= :start_date)
            ORDER BY effective_from DESC LIMIT 1",
            ['emp_code' => $employeeCode, 'start_date' => $payroll['start_date'], 'end_date' => $payroll['end_date']]
        );

        return $payroll;
    }
}
