<?php
/**
 * RCS HRMS Pro - Client Management Class
 * 
 * Database schema:
 * - clients table has 'name' field (not 'client_name')
 * - employees table has 'client_name' VARCHAR field
 */

class Client {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Get all clients
    public function getAll($activeOnly = true) {
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM units WHERE client_id = c.id) as unit_count,
                (SELECT COUNT(*) FROM employees WHERE client_name = c.name AND status = 'approved') as employee_count
                FROM clients c";
        
        if ($activeOnly) {
            $sql .= " WHERE c.is_active = 1";
        }
        
        $sql .= " ORDER BY c.name ASC";
        
        return $this->db->fetchAll($sql);
    }
    
    // Get client by ID
    public function getById($id) {
        return $this->db->fetch(
            "SELECT * FROM clients WHERE id = :id",
            ['id' => $id]
        );
    }
    
    // Create new client
    public function create($data) {
        $exists = $this->db->fetch(
            "SELECT id FROM clients WHERE name = :name",
            ['name' => $data['client_name']]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Client with this name already exists.'];
        }
        
        // Generate client code if not provided
        $clientCode = $data['client_code'] ?? $this->generateClientCode($data['client_name']);
        
        $insertData = [
            'client_code' => $clientCode,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'gst_number' => $data['gst_number'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['phone'] ?? $data['contact_phone'] ?? null,
            'contact_email' => $data['email'] ?? $data['contact_email'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ];
        
        // Handle column name - check if 'name' or 'client_name' exists
        try {
            $columns = $this->db->fetchAll("SHOW COLUMNS FROM clients LIKE 'name'");
            if (!empty($columns)) {
                $insertData['name'] = $data['client_name'];
            } else {
                $insertData['client_name'] = $data['client_name'];
            }
        } catch (Exception $e) {
            $insertData['name'] = $data['client_name'];
        }
        
        $id = $this->db->insert('clients', $insertData);
        
        return ['success' => true, 'id' => $id, 'message' => 'Client created successfully.'];
    }
    
    // Update client
    public function update($id, $data) {
        $this->db->update('clients', [
            'name' => $data['client_name'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'gst_number' => $data['gst_number'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['phone'] ?? $data['contact_phone'] ?? null,
            'contact_email' => $data['email'] ?? $data['contact_email'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ], 'id = :id', ['id' => $id]);
        
        return ['success' => true, 'message' => 'Client updated successfully.'];
    }
    
    // Delete client
    public function delete($id) {
        // Get client name first
        $client = $this->getById($id);
        if ($client) {
            // Check if client has employees using client_name matching name
            $employees = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employees WHERE client_name = :name",
                ['name' => $client['name']]
            );
            
            if ($employees['count'] > 0) {
                return ['success' => false, 'message' => 'Cannot delete client with associated employees.'];
            }
        }
        
        $this->db->delete('clients', 'id = :id', ['id' => $id]);
        return ['success' => true, 'message' => 'Client deleted successfully.'];
    }
    
    // Get client list for dropdowns
    public function getList() {
        return $this->db->fetchAll(
            "SELECT id, name, client_code FROM clients WHERE is_active = 1 ORDER BY name"
        );
    }
    
    // Generate sequential client code
    private function generateClientCode($name) {
        // Get count of existing clients
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM clients");
        
        $nextNum = ($result) ? intval($result['count']) + 1 : 1;
        
        // Format as C001, C002, C003, etc.
        return 'C' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }
}
?>
