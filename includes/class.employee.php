<?php
/**
 * RCS HRMS Pro - Employee Class
 * Handles all employee-related operations
 * 
 * Database Schema (Updated):
 * - employees.id: VARCHAR(36) UUID
 * - employees.full_name: VARCHAR(255) - direct column
 * - employees.father_name: VARCHAR(255) - father's name
 * - employees.client_id: INT(11) FK to clients.id
 * - employees.unit_id: INT(11) FK to units.id
 * - employee_salary_structures.employee_id: VARCHAR(36) matches employees.id
 */

class Employee {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get employee counts for dashboard
    public function getCounts() {
        $counts = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'left' => 0,
            'pending' => 0
        ];
        
        try {
            // Total employees
            $result = $this->db->fetch("SELECT COUNT(*) as count FROM employees");
            $counts['total'] = (int)($result['count'] ?? 0);
            
            // Active employees (approved)
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employees WHERE status = 'approved'"
            );
            $counts['active'] = (int)($result['count'] ?? 0);
            
            // Pending employees
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employees WHERE status LIKE 'pending%'"
            );
            $counts['pending'] = (int)($result['count'] ?? 0);
            
            // Inactive employees
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employees WHERE status = 'inactive'"
            );
            $counts['inactive'] = (int)($result['count'] ?? 0);
            
            // Left employees
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employees WHERE date_of_leaving IS NOT NULL"
            );
            $counts['left'] = (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            // Table might not exist yet
            error_log('Employee getCounts error: ' . $e->getMessage());
        }
        
        return $counts;
    }

    // Get all employees with filters
    public function getAll($filters = [], $page = 1, $perPage = 50) {
        // Check if employee_salary_structures table exists
        $useSalaryTable = $this->checkSalaryTableExists();
        $baseColumns = $this->getBaseColumns();
        
        if ($useSalaryTable) {
            $sql = "SELECT $baseColumns,
                           ess.basic_wage, ess.da, ess.hra, ess.gross_salary,
                           ess.pf_applicable, ess.esi_applicable, ess.pt_applicable,
                           COALESCE(c.name, e.client_name) as client_name_display, 
                           COALESCE(u.name, e.unit_name) as unit_name_display
                    FROM employees e
                    LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                        AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                    LEFT JOIN clients c ON e.client_id = c.id
                    LEFT JOIN units u ON e.unit_id = u.id
                    WHERE 1=1";
        } else {
            $sql = "SELECT $baseColumns,
                           COALESCE(c.name, e.client_name) as client_name_display, 
                           COALESCE(u.name, e.unit_name) as unit_name_display
                    FROM employees e
                    LEFT JOIN clients c ON e.client_id = c.id
                    LEFT JOIN units u ON e.unit_id = u.id
                    WHERE 1=1";
        }
        $params = [];

        // Status filter
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'Active') {
                $sql .= " AND e.status = 'approved'";
            } elseif ($filters['status'] === 'Pending') {
                $sql .= " AND e.status LIKE 'pending%'";
            } else {
                $sql .= " AND e.status = :status";
                $params['status'] = $filters['status'];
            }
        }

        // Client filter
        if (!empty($filters['client_id'])) {
            $sql .= " AND e.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        // Unit filter
        if (!empty($filters['unit_id'])) {
            $sql .= " AND e.unit_id = :unit_id";
            $params['unit_id'] = $filters['unit_id'];
        }

        // Worker category filter
        if (!empty($filters['worker_category'])) {
            $sql .= " AND e.worker_category = :worker_category";
            $params['worker_category'] = $filters['worker_category'];
        }

        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (e.employee_code LIKE :search
                      OR e.full_name LIKE :search
                      OR e.mobile_number LIKE :search
                      OR e.aadhaar_number LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM ($sql) as t";
        $total = $this->db->fetchColumn($countSql, $params);

        // Pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " ORDER BY e.created_at DESC LIMIT $perPage OFFSET $offset";

        $employees = $this->db->fetchAll($sql, $params);

        return [
            'data' => $employees,
            'total' => $total ?: 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil(($total ?: 0) / $perPage)
        ];
    }

    // Check if employee_salary_structures table exists
    private function checkSalaryTableExists() {
        try {
            $this->db->fetch("SELECT 1 FROM employee_salary_structures LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // Get base columns for SELECT (excluding client_name/unit_name to avoid duplicates with JOINs)
    private function getBaseColumns() {
        return "e.id, e.mobile_number, e.alternate_mobile, e.full_name, e.father_name,
                e.date_of_birth, e.gender, e.aadhaar_number, e.email,
                e.uan_number, e.esic_number, e.marital_status, e.blood_group,
                e.address, e.pin_code, e.state, e.district,
                e.bank_name, e.account_number, e.ifsc_code, e.account_holder_name,
                e.client_id, e.client_name, e.unit_id, e.unit_name,
                e.date_of_joining, e.confirmation_date,
                e.probation_period, e.date_of_leaving, e.status, e.profile_completion,
                e.employee_role, e.designation, e.department, e.employment_type, 
                e.worker_category, e.employee_code, e.created_at, e.updated_at,
                e.nominee_name, e.nominee_relationship, e.nominee_dob, e.nominee_contact,
                e.emergency_contact_name, e.emergency_contact_relation,
                e.profile_pic_url, e.profile_pic_cropped_url, e.aadhaar_front_url, 
                e.aadhaar_back_url, e.bank_document_url";
    }

    // Get single employee by ID (UUID)
    public function getById($id) {
        $useSalaryTable = $this->checkSalaryTableExists();
        $baseColumns = $this->getBaseColumns();
        
        if ($useSalaryTable) {
            $employee = $this->db->fetch(
                "SELECT $baseColumns,
                        ess.basic_wage, ess.da, ess.hra, ess.conveyance, ess.medical_allowance,
                        ess.special_allowance, ess.other_allowance, ess.gross_salary,
                        ess.pf_applicable, ess.esi_applicable, 
                        ess.pt_applicable, ess.lwf_applicable,
                        ess.bonus_applicable, ess.gratuity_applicable, 
                        ess.overtime_applicable,
                        COALESCE(c.name, e.client_name) as client_name_display, 
                        COALESCE(u.name, e.unit_name) as unit_name_display
                 FROM employees e
                 LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                    AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                 LEFT JOIN clients c ON e.client_id = c.id
                 LEFT JOIN units u ON e.unit_id = u.id
                 WHERE e.id = :id",
                ['id' => $id]
            );
        } else {
            // Fallback without employee_salary_structures table
            $employee = $this->db->fetch(
                "SELECT $baseColumns,
                        COALESCE(c.name, e.client_name) as client_name_display, 
                        COALESCE(u.name, e.unit_name) as unit_name_display
                 FROM employees e
                 LEFT JOIN clients c ON e.client_id = c.id
                 LEFT JOIN units u ON e.unit_id = u.id
                 WHERE e.id = :id",
                ['id' => $id]
            );
        }

        if ($employee) {
            // Get documents
            try {
                $employee['documents'] = $this->db->fetchAll(
                    "SELECT * FROM employee_documents WHERE employee_id = :id",
                    ['id' => $id]
                );
            } catch (Exception $e) {
                $employee['documents'] = [];
            }
        }

        return $employee;
    }

    // Get employee by employee_code
    public function getByCode($code) {
        $useSalaryTable = $this->checkSalaryTableExists();
        
        if ($useSalaryTable) {
            return $this->db->fetch(
                "SELECT e.*, ess.basic_wage, ess.da, ess.hra, ess.gross_salary, e.id as employee_id
                 FROM employees e
                 LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                    AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                 WHERE e.employee_code = :code",
                ['code' => $code]
            );
        } else {
            return $this->db->fetch(
                "SELECT e.*, e.id as employee_id
                 FROM employees e
                 WHERE e.employee_code = :code",
                ['code' => $code]
            );
        }
    }

    // Create new employee
    public function create($data) {
        // Generate UUID for new employee
        $data['id'] = $this->generateUUID();
        
        // Generate employee code if not provided
        if (empty($data['employee_code'])) {
            $data['employee_code'] = $this->generateEmployeeCode();
        }
        
        // Set default status
        $data['status'] = $data['status'] ?? 'pending_hr_verification';
        $data['created_at'] = date('Y-m-d H:i:s');

        // Map form fields to database columns
        $dbData = $this->mapFormDataToDb($data);

        // Check if employee_salary_structures table exists
        $useSalaryTable = $this->checkSalaryTableExists();

        try {
            $this->db->beginTransaction();
            
            // Insert employee
            $employeeId = $this->db->insert('employees', $dbData);
            
            // Insert salary structure if provided and table exists
            if ($useSalaryTable && (!empty($data['basic_wage']) || !empty($data['basic_salary']))) {
                $salaryData = [
                    'employee_id' => $data['id'],
                    'effective_from' => $data['date_of_joining'] ?? date('Y-m-d'),
                    'basic_wage' => floatval($data['basic_wage'] ?? $data['basic_salary'] ?? 0),
                    'da' => floatval($data['da'] ?? 0),
                    'hra' => floatval($data['hra'] ?? 0),
                    'conveyance' => floatval($data['conveyance'] ?? 0),
                    'medical_allowance' => floatval($data['medical_allowance'] ?? 0),
                    'special_allowance' => floatval($data['special_allowance'] ?? 0),
                    'other_allowance' => floatval($data['other_allowance'] ?? 0),
                    'gross_salary' => floatval($data['gross_salary'] ?? 0),
                    'pf_applicable' => isset($data['pf_applicable']) ? 1 : 1,
                    'esi_applicable' => isset($data['esi_applicable']) ? 1 : 1,
                    'pt_applicable' => isset($data['pt_applicable']) ? 1 : 1,
                    'lwf_applicable' => isset($data['lwf_applicable']) ? 1 : 1,
                    'bonus_applicable' => isset($data['bonus_applicable']) ? 1 : 1,
                    'gratuity_applicable' => isset($data['gratuity_applicable']) ? 1 : 1,
                    'overtime_applicable' => isset($data['overtime_applicable']) ? 1 : 0,
                ];
                $this->db->insert('employee_salary_structures', $salaryData);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Employee created successfully.', 'employee_id' => $data['id']];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to create employee: ' . $e->getMessage()];
        }
    }

    // Map form data to database columns
    private function mapFormDataToDb($data) {
        $mapped = [];
        
        // Direct mapping - form fields match database columns
        $directFields = [
            'full_name', 'father_name', 'employee_code', 'mobile_number', 'alternate_mobile',
            'email', 'gender', 'date_of_birth', 'marital_status', 'blood_group',
            'aadhaar_number', 'uan_number', 'esic_number',
            'address', 'pin_code', 'state', 'district',
            'bank_name', 'account_number', 'ifsc_code', 'account_holder_name',
            'client_id', 'client_name', 'unit_id', 'unit_name',
            'designation', 'department',
            'worker_category', 'employment_type', 'date_of_joining', 'probation_period',
            'nominee_name', 'nominee_relationship', 'nominee_dob', 'nominee_contact',
            'emergency_contact_name', 'emergency_contact_relation',
            'status', 'profile_pic_url', 'profile_pic_cropped_url',
            'aadhaar_front_url', 'aadhaar_back_url', 'bank_document_url'
        ];
        
        foreach ($directFields as $field) {
            if (isset($data[$field])) {
                $mapped[$field] = $data[$field];
            }
        }
        
        // Handle middle_name -> father_name mapping (for backward compatibility)
        if (isset($data['middle_name']) && !isset($data['father_name'])) {
            $mapped['father_name'] = $data['middle_name'];
        }
        
        // Look up client_id from client_name if not provided
        if (!empty($data['client_name']) && empty($data['client_id'])) {
            $client = $this->db->fetch(
                "SELECT id, name FROM clients WHERE name = :name LIMIT 1",
                ['name' => $data['client_name']]
            );
            if ($client) {
                $mapped['client_id'] = $client['id'];
                $mapped['client_name'] = $client['name'];
            }
        }
        
        // Look up unit_id from unit_name if not provided
        if (!empty($data['unit_name']) && empty($data['unit_id'])) {
            $unit = $this->db->fetch(
                "SELECT id, name FROM units WHERE name = :name LIMIT 1",
                ['name' => $data['unit_name']]
            );
            if ($unit) {
                $mapped['unit_id'] = $unit['id'];
                $mapped['unit_name'] = $unit['name'];
            }
        }
        
        return $mapped;
    }

    // Update employee
    public function update($id, $data) {
        $employee = $this->getById($id);

        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        // Map form fields to database columns
        $dbData = $this->mapFormDataToDb($data);
        unset($dbData['id'], $dbData['employee_code'], $dbData['created_at']);
        $dbData['updated_at'] = date('Y-m-d H:i:s');

        // Check if employee_salary_structures table exists
        $useSalaryTable = $this->checkSalaryTableExists();

        // Extract salary data
        $salaryData = [];
        if ($useSalaryTable && (isset($data['basic_wage']) || isset($data['basic_salary']))) {
            $salaryData = [
                'basic_wage' => floatval($data['basic_wage'] ?? $data['basic_salary'] ?? 0),
                'da' => floatval($data['da'] ?? 0),
                'hra' => floatval($data['hra'] ?? 0),
                'conveyance' => floatval($data['conveyance'] ?? 0),
                'medical_allowance' => floatval($data['medical_allowance'] ?? 0),
                'special_allowance' => floatval($data['special_allowance'] ?? 0),
                'other_allowance' => floatval($data['other_allowance'] ?? 0),
                'gross_salary' => floatval($data['gross_salary'] ?? 0),
                'pf_applicable' => isset($data['pf_applicable']) ? 1 : 1,
                'esi_applicable' => isset($data['esi_applicable']) ? 1 : 1,
                'pt_applicable' => isset($data['pt_applicable']) ? 1 : 1,
                'lwf_applicable' => isset($data['lwf_applicable']) ? 1 : 1,
            ];
        }

        try {
            $this->db->beginTransaction();
            
            // Update employee
            $this->db->update('employees', $dbData, 'id = :id', ['id' => $id]);
            
            // Update salary structure if provided and table exists
            if (!empty($salaryData)) {
                // Check if salary structure exists
                $existing = $this->db->fetch(
                    "SELECT id FROM employee_salary_structures WHERE employee_id = :id AND (effective_to IS NULL OR effective_to >= CURDATE())",
                    ['id' => $id]
                );
                
                if ($existing) {
                    $this->db->update('employee_salary_structures', $salaryData, 'id = :id', ['id' => $existing['id']]);
                } else {
                    $salaryData['employee_id'] = $id;
                    $salaryData['effective_from'] = date('Y-m-d');
                    $this->db->insert('employee_salary_structures', $salaryData);
                }
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Employee updated successfully.'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to update employee: ' . $e->getMessage()];
        }
    }

    // Delete employee (soft delete by setting status)
    public function delete($id) {
        $result = $this->db->update('employees', [
            'status' => 'terminated',
            'date_of_leaving' => date('Y-m-d'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);

        return ['success' => true, 'message' => 'Employee deleted successfully.'];
    }

    // Approve employee
    public function approve($id, $approvedBy) {
        return $this->db->update('employees', [
            'status' => 'active',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $approvedBy,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);
    }

    // Generate UUID v4
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // Generate employee code
    public function generateEmployeeCode($prefix = '') {
        // Get the max employee code
        $lastCode = $this->db->fetchColumn(
            "SELECT MAX(employee_code) FROM employees"
        );

        if ($lastCode) {
            return (int)$lastCode + 1;
        }
        
        return 1001; // Starting employee code
    }

    // Get employee statistics
    public function getStatistics($filters = []) {
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filters['client_id'])) {
            $where .= " AND client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        return [
            'total' => $this->db->fetchColumn("SELECT COUNT(*) FROM employees $where", $params) ?: 0,
            'active' => $this->db->fetchColumn("SELECT COUNT(*) FROM employees $where AND status = 'approved'", $params) ?: 0,
            'pending' => $this->db->fetchColumn("SELECT COUNT(*) FROM employees $where AND status LIKE 'pending%'", $params) ?: 0,
        ];
    }

    // Get employees for payroll processing
    public function getActiveForPayroll($clientId = null, $unitId = null) {
        $useSalaryTable = $this->checkSalaryTableExists();
        
        if ($useSalaryTable) {
            $sql = "SELECT e.id, e.employee_code, e.full_name, e.father_name, e.date_of_joining, 
                           COALESCE(c.name, e.client_name) as client_name_display, 
                           COALESCE(u.name, e.unit_name) as unit_name_display, e.worker_category,
                           ess.basic_wage, ess.da, ess.hra, ess.conveyance, 
                           ess.medical_allowance, ess.special_allowance, ess.other_allowance,
                           ess.gross_salary, ess.pf_applicable, ess.esi_applicable, 
                           ess.pt_applicable, ess.lwf_applicable, ess.overtime_applicable
                    FROM employees e
                    INNER JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                        AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                    LEFT JOIN clients c ON e.client_id = c.id
                    LEFT JOIN units u ON e.unit_id = u.id
                    WHERE e.status = 'active'";
        } else {
            $sql = "SELECT e.id, e.employee_code, e.full_name, e.father_name, e.date_of_joining, 
                           COALESCE(c.name, e.client_name) as client_name_display, 
                           COALESCE(u.name, e.unit_name) as unit_name_display, e.worker_category
                    FROM employees e
                    LEFT JOIN clients c ON e.client_id = c.id
                    LEFT JOIN units u ON e.unit_id = u.id
                    WHERE e.status = 'active'";
        }
        $params = [];

        if ($clientId) {
            $sql .= " AND e.client_id = :client_id";
            $params['client_id'] = $clientId;
        }

        if ($unitId) {
            $sql .= " AND e.unit_id = :unit_id";
            $params['unit_id'] = $unitId;
        }

        return $this->db->fetchAll($sql, $params);
    }

    // Get employees for attendance
    public function getForAttendance($unitId) {
        return $this->db->fetchAll(
            "SELECT e.id, e.employee_code, e.full_name 
             FROM employees e
             WHERE e.unit_id = :unit_id AND e.status = 'active'
             ORDER BY e.employee_code",
            ['unit_id' => $unitId]
        );
    }

    // Search employees
    public function search($query, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT e.id, e.employee_code, e.full_name, e.designation, 
                    COALESCE(c.name, e.client_name) as client_name_display, 
                    COALESCE(u.name, e.unit_name) as unit_name_display 
             FROM employees e
             LEFT JOIN clients c ON e.client_id = c.id
             LEFT JOIN units u ON e.unit_id = u.id
             WHERE (e.employee_code LIKE :query OR e.full_name LIKE :query OR e.mobile_number LIKE :query)
             AND e.status = 'active'
             ORDER BY e.full_name
             LIMIT :limit",
            ['query' => "%$query%", 'limit' => $limit]
        );
    }

    // Get employees by client
    public function getByClient($clientId) {
        $baseColumns = $this->getBaseColumns();
        return $this->db->fetchAll(
            "SELECT $baseColumns, COALESCE(c.name, e.client_name) as client_name_display 
             FROM employees e
             LEFT JOIN clients c ON e.client_id = c.id
             WHERE e.client_id = :client_id AND e.status = 'active'",
            ['client_id' => $clientId]
        );
    }

    // Get employees by unit
    public function getByUnit($unitId) {
        $baseColumns = $this->getBaseColumns();
        return $this->db->fetchAll(
            "SELECT $baseColumns, COALESCE(u.name, e.unit_name) as unit_name_display 
             FROM employees e
             LEFT JOIN units u ON e.unit_id = u.id
             WHERE e.unit_id = :unit_id AND e.status = 'active'",
            ['unit_id' => $unitId]
        );
    }

    // Import employees from array
    public function importFromData($data, $skipDuplicates = true) {
        $imported = 0;
        $errors = [];
        $duplicates = [];

        foreach ($data as $row) {
            try {
                // Check for existing employee by mobile or aadhaar
                if (!empty($row['mobile_number'])) {
                    $exists = $this->db->fetch(
                        "SELECT id FROM employees WHERE mobile_number = :mobile",
                        ['mobile' => $row['mobile_number']]
                    );
                    if ($exists) {
                        if ($skipDuplicates) {
                            $duplicates[] = $row['mobile_number'];
                            continue;
                        }
                    }
                }

                $result = $this->create($row);
                if ($result['success']) {
                    $imported++;
                } else {
                    $errors[] = $result['message'];
                }
            } catch (Exception $e) {
                $errors[] = "Error importing row: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'imported' => $imported,
            'duplicates' => count($duplicates),
            'errors' => $errors
        ];
    }

    // Get all clients for dropdowns
    public function getAllClients() {
        return $this->db->fetchAll(
            "SELECT id, client_code, name, city FROM clients WHERE is_active = 1 ORDER BY name"
        );
    }

    // Get all units for dropdowns
    public function getAllUnits($clientId = null) {
        $sql = "SELECT id, unit_code, name, city FROM units WHERE is_active = 1";
        $params = [];
        
        if ($clientId) {
            $sql .= " AND client_id = :client_id";
            $params['client_id'] = $clientId;
        }
        
        $sql .= " ORDER BY name";
        
        return $this->db->fetchAll($sql, $params);
    }
}
?>
