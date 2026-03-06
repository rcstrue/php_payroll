<?php
/**
 * RCS HRMS Pro - Employee Class
 * Handles all employee-related operations
 * Updated for new database schema with UUID and employee_salary_structures
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
        $sql = "SELECT e.*, 
                       ess.basic_wage, ess.da, ess.hra, ess.gross_salary,
                       ess.pf_applicable, ess.esi_applicable, ess.pt_applicable
                FROM employees e
                LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                    AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                WHERE 1=1";
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

        // Client filter (client_name is now a varchar field storing the name)
        if (!empty($filters['client_id'])) {
            $client = $this->db->fetch("SELECT name FROM clients WHERE id = :id", ['id' => $filters['client_id']]);
            if ($client) {
                $sql .= " AND e.client_name = :client_name";
                $params['client_name'] = $client['name'];
            }
        }

        // Unit filter (unit_name is now a varchar field storing the name)
        if (!empty($filters['unit_id'])) {
            $unit = $this->db->fetch("SELECT name FROM units WHERE id = :id", ['id' => $filters['unit_id']]);
            if ($unit) {
                $sql .= " AND e.unit_name = :unit_name";
                $params['unit_name'] = $unit['name'];
            }
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

    // Get single employee by ID (UUID)
    public function getById($id) {
        $employee = $this->db->fetch(
            "SELECT e.*, 
                    ess.basic_wage, ess.da, ess.hra, ess.conveyance, ess.medical_allowance,
                    ess.special_allowance, ess.other_allowance, ess.gross_salary,
                    ess.pf_applicable, ess.esi_applicable, ess.pt_applicable, ess.lwf_applicable,
                    ess.bonus_applicable, ess.gratuity_applicable, ess.overtime_applicable
             FROM employees e
             LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
             WHERE e.id = :id",
            ['id' => $id]
        );

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
        return $this->db->fetch(
            "SELECT e.*, ess.*, e.id as employee_id
             FROM employees e
             LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
             WHERE e.employee_code = :code",
            ['code' => $code]
        );
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

        // Extract salary data
        $salaryData = [];
        $salaryFields = ['basic_wage', 'da', 'hra', 'conveyance', 'medical_allowance', 
                        'special_allowance', 'other_allowance', 'gross_salary',
                        'pf_applicable', 'esi_applicable', 'pt_applicable', 'lwf_applicable',
                        'bonus_applicable', 'gratuity_applicable', 'overtime_applicable'];
        
        foreach ($salaryFields as $field) {
            if (isset($data[$field])) {
                $salaryData[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        try {
            $this->db->beginTransaction();
            
            // Insert employee
            $employeeId = $this->db->insert('employees', $data);
            
            // Insert salary structure if provided
            if (!empty($salaryData) && $employeeId) {
                $salaryData['employee_id'] = $data['id'];
                $salaryData['effective_from'] = $data['date_of_joining'] ?? date('Y-m-d');
                $this->db->insert('employee_salary_structures', $salaryData);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Employee created successfully.', 'employee_id' => $data['id']];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to create employee: ' . $e->getMessage()];
        }
    }

    // Update employee
    public function update($id, $data) {
        $employee = $this->getById($id);

        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        unset($data['id'], $data['employee_code'], $data['created_at']);
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Extract salary data
        $salaryData = [];
        $salaryFields = ['basic_wage', 'da', 'hra', 'conveyance', 'medical_allowance', 
                        'special_allowance', 'other_allowance', 'gross_salary',
                        'pf_applicable', 'esi_applicable', 'pt_applicable', 'lwf_applicable',
                        'bonus_applicable', 'gratuity_applicable', 'overtime_applicable'];
        
        foreach ($salaryFields as $field) {
            if (isset($data[$field])) {
                $salaryData[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        try {
            $this->db->beginTransaction();
            
            // Update employee
            $this->db->update('employees', $data, 'id = :id', ['id' => $id]);
            
            // Update salary structure if provided
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
            'status' => 'approved',
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

        if (!empty($filters['client_name'])) {
            $where .= " AND client_name = :client_name";
            $params['client_name'] = $filters['client_name'];
        }

        return [
            'total' => $this->db->fetchColumn("SELECT COUNT(*) FROM employees $where", $params) ?: 0,
            'active' => $this->db->fetchColumn("SELECT COUNT(*) FROM employees $where AND status = 'approved'", $params) ?: 0,
            'pending' => $this->db->fetchColumn("SELECT COUNT(*) FROM employees $where AND status LIKE 'pending%'", $params) ?: 0,
        ];
    }

    // Get employees for payroll processing
    public function getActiveForPayroll($clientName = null, $unitName = null) {
        $sql = "SELECT e.id, e.employee_code, e.full_name, e.date_of_joining, 
                       e.client_name, e.unit_name, e.worker_category,
                       ess.basic_wage, ess.da, ess.hra, ess.conveyance, 
                       ess.medical_allowance, ess.special_allowance, ess.other_allowance,
                       ess.gross_salary, ess.pf_applicable, ess.esi_applicable, 
                       ess.pt_applicable, ess.lwf_applicable, ess.overtime_applicable
                FROM employees e
                INNER JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                    AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                WHERE e.status = 'approved'";
        $params = [];

        if ($clientName) {
            $sql .= " AND e.client_name = :client_name";
            $params['client_name'] = $clientName;
        }

        if ($unitName) {
            $sql .= " AND e.unit_name = :unit_name";
            $params['unit_name'] = $unitName;
        }

        return $this->db->fetchAll($sql, $params);
    }

    // Get employees for attendance
    public function getForAttendance($unitName) {
        return $this->db->fetchAll(
            "SELECT id, employee_code, full_name 
             FROM employees 
             WHERE unit_name = :unit_name AND status = 'approved'
             ORDER BY employee_code",
            ['unit_name' => $unitName]
        );
    }

    // Search employees
    public function search($query, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT id, employee_code, full_name, designation, client_name, unit_name 
             FROM employees 
             WHERE (employee_code LIKE :query OR full_name LIKE :query OR mobile_number LIKE :query)
             AND status = 'approved'
             ORDER BY full_name
             LIMIT :limit",
            ['query' => "%$query%", 'limit' => $limit]
        );
    }

    // Get employees by client
    public function getByClient($clientName) {
        return $this->db->fetchAll(
            "SELECT * FROM employees WHERE client_name = :client_name AND status = 'approved'",
            ['client_name' => $clientName]
        );
    }

    // Get employees by unit
    public function getByUnit($unitName) {
        return $this->db->fetchAll(
            "SELECT * FROM employees WHERE unit_name = :unit_name AND status = 'approved'",
            ['unit_name' => $unitName]
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
}
?>
