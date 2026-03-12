<?php
/**
 * RCS HRMS Pro - Client Class
 * Handles all client-related operations
 */

class Client {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all clients
    public function getList($activeOnly = true) {
        $sql = "SELECT id, client_code, name as client_name, contact_person, contact_email, 
                       contact_phone, city, state, is_active
                FROM clients";
        
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        
        $sql .= " ORDER BY name";
        
        return $this->db->fetchAll($sql);
    }

    // Get all clients (alias for getList)
    public function getAll($activeOnly = true) {
        return $this->getList($activeOnly);
    }

    // Get client by ID
    public function getById($id) {
        return $this->db->fetch(
            "SELECT * FROM clients WHERE id = :id",
            ['id' => $id]
        );
    }

    // Get client by name
    public function getByName($name) {
        return $this->db->fetch(
            "SELECT * FROM clients WHERE name = :name LIMIT 1",
            ['name' => $name]
        );
    }

    // Create new client
    public function create($data) {
        // Generate client code if not provided
        if (empty($data['client_code'])) {
            $data['client_code'] = $this->generateClientCode();
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = $data['is_active'] ?? 1;

        try {
            $id = $this->db->insert('clients', $data);
            return ['success' => true, 'message' => 'Client created successfully.', 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to create client: ' . $e->getMessage()];
        }
    }

    // Update client
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        try {
            $this->db->update('clients', $data, 'id = :id', ['id' => $id]);
            return ['success' => true, 'message' => 'Client updated successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update client: ' . $e->getMessage()];
        }
    }

    // Delete client
    public function delete($id) {
        // Check if client has employees
        $empCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM employees WHERE client_id = :id",
            ['id' => $id]
        );
        
        if ($empCount > 0) {
            return ['success' => false, 'message' => "Cannot delete client. $empCount employees are assigned to this client."];
        }
        
        try {
            $this->db->delete('clients', 'id = :id', ['id' => $id]);
            return ['success' => true, 'message' => 'Client deleted successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete client: ' . $e->getMessage()];
        }
    }

    // Generate client code
    private function generateClientCode() {
        $lastCode = $this->db->fetchColumn(
            "SELECT MAX(client_code) FROM clients WHERE client_code LIKE 'C%'"
        );
        
        if ($lastCode) {
            $num = (int)substr($lastCode, 1) + 1;
            return 'C' . str_pad($num, 4, '0', STR_PAD_LEFT);
        }
        
        return 'C0001';
    }

    // Get client statistics
    public function getStatistics($clientId) {
        return [
            'units' => $this->db->fetchColumn(
                "SELECT COUNT(*) FROM units WHERE client_id = :id",
                ['id' => $clientId]
            ) ?: 0,
            'employees' => $this->db->fetchColumn(
                "SELECT COUNT(*) FROM employees WHERE client_id = :id",
                ['id' => $clientId]
            ) ?: 0,
            'active_employees' => $this->db->fetchColumn(
                "SELECT COUNT(*) FROM employees WHERE client_id = :id AND status = 'active'",
                ['id' => $clientId]
            ) ?: 0
        ];
    }
}
?>
