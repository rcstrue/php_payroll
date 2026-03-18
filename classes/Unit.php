<?php
/**
 * RCS HRMS Pro - Unit Management Class
 * 
 * Database schema:
 * - units table has 'name' field (not 'unit_name')
 * - employees table has 'unit_name' VARCHAR field
 */

class Unit {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Get all units
    public function getAll($clientId = null, $activeOnly = true) {
        $sql = "SELECT u.*, c.name as client_name,
                (SELECT COUNT(*) FROM employees e WHERE e.unit_id = u.id AND e.status IN ('approved', 'pending_hr_verification')) as employee_count
                FROM units u
                LEFT JOIN clients c ON u.client_id = c.id
                WHERE 1=1";
        
        $params = [];
        
        if ($clientId) {
            $sql .= " AND u.client_id = :client_id";
            $params['client_id'] = $clientId;
        }
        
        if ($activeOnly) {
            $sql .= " AND u.is_active = 1";
        }
        
        $sql .= " ORDER BY c.name, u.name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get unit by ID
    public function getById($id) {
        return $this->db->fetch(
            "SELECT u.*, c.name as client_name FROM units u LEFT JOIN clients c ON u.client_id = c.id WHERE u.id = :id",
            ['id' => $id]
        );
    }
    
    // Create new unit
    public function create($data) {
        // Check for duplicate unit code
        if (!empty($data['unit_code'])) {
            $exists = $this->db->fetch(
                "SELECT id FROM units WHERE unit_code = :code",
                ['code' => $data['unit_code']]
            );
            if ($exists) {
                return ['success' => false, 'message' => 'Unit code already exists!'];
            }
        }
        
        // Check for duplicate name under same client
        $exists = $this->db->fetch(
            "SELECT id FROM units WHERE name = :name AND client_id = :client_id",
            ['name' => $data['unit_name'], 'client_id' => $data['client_id']]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Unit with this name already exists for this client.'];
        }
        
        // Generate unit code if not provided
        $unitCode = $data['unit_code'] ?? $this->generateUnitCode($data['unit_name'], $data['client_id']);
        
        $id = $this->db->insert('units', [
            'client_id' => $data['client_id'],
            'unit_code' => $unitCode,
            'name' => $data['unit_name'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['phone'] ?? $data['contact_phone'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);
        
        return ['success' => true, 'id' => $id, 'message' => 'Unit created successfully.'];
    }
    
    // Update unit
    public function update($id, $data) {
        // Check for duplicate unit code (exclude current)
        if (!empty($data['unit_code'])) {
            $exists = $this->db->fetch(
                "SELECT id FROM units WHERE unit_code = :code AND id != :id",
                ['code' => $data['unit_code'], 'id' => $id]
            );
            if ($exists) {
                return ['success' => false, 'message' => 'Unit code already exists!'];
            }
        }
        
        $this->db->update('units', [
            'client_id' => $data['client_id'],
            'name' => $data['unit_name'],
            'unit_code' => $data['unit_code'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['phone'] ?? $data['contact_phone'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ], 'id = :id', ['id' => $id]);
        
        return ['success' => true, 'message' => 'Unit updated successfully.'];
    }
    
    // Delete unit
    public function delete($id) {
        $unit = $this->getById($id);
        if ($unit) {
            $employees = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employees WHERE unit_id = :id",
                ['id' => $id]
            );
            
            if ($employees['count'] > 0) {
                return ['success' => false, 'message' => 'Cannot delete unit with associated employees.'];
            }
        }
        
        $this->db->delete('units', 'id = :id', ['id' => $id]);
        return ['success' => true, 'message' => 'Unit deleted successfully.'];
    }
    
    // Get units by client for dropdowns
    public function getByClient($clientId) {
        return $this->db->fetchAll(
            "SELECT id, name, unit_code FROM units WHERE client_id = :client_id AND is_active = 1 ORDER BY name",
            ['client_id' => $clientId]
        );
    }
    
    // Get unit list for dropdowns
    public function getList() {
        return $this->db->fetchAll(
            "SELECT id, name, unit_code, client_id FROM units WHERE is_active = 1 ORDER BY name"
        );
    }
    
    // Generate sequential unit code
    private function generateUnitCode($name, $clientId) {
        // Get client code for prefix
        $client = $this->db->fetch("SELECT client_code FROM clients WHERE id = :id", ['id' => $clientId]);
        $prefix = $client ? $client['client_code'] : 'UNT';
        
        // Get count of units for this client
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM units WHERE client_id = :client_id",
            ['client_id' => $clientId]
        );
        
        $nextNum = ($result) ? intval($result['count']) + 1 : 1;
        
        // Format as PREFIX-01, PREFIX-02, etc.
        return $prefix . '-' . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
    }
}
?>
