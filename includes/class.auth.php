<?php
/**
 * RCS HRMS Pro - Authentication Class
 * Handles user authentication, authorization, and session management
 */

// Constant to avoid string duplication
define('SQL_WHERE_USER_ID', 'id = :id');

class Auth {
    private $db;
    private $user = null;
    
    // Role hierarchy for access control
    private $roleHierarchy = [
        'admin' => 100,
        'hr_executive' => 80,
        'manager' => 60,
        'supervisor' => 40,
        'worker' => 20
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->checkSession();
    }
    
    // Check existing session
    private function checkSession() {
        if (isset($_SESSION['user_id'])) {
            $user = $this->db->fetch(
                "SELECT u.id, u.username, u.email, u.role_id, u.first_name, u.last_name, u.is_active,
                        r.role_name, r.role_code
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id
                 WHERE u.id = :id AND u.is_active = 1",
                ['id' => $_SESSION['user_id']]
            );
            
            if ($user) {
                $this->user = $user;
            }
        }
    }
    
    // Login user
    public function login($username, $password, $remember = false) {
        // Get user with role info
        $user = $this->db->fetch(
            "SELECT u.id, u.username, u.email, u.password, u.role_id, u.first_name, u.last_name, u.is_active,
                    r.role_name, r.role_code
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE (u.username = :username OR u.email = :email)",
            ['username' => $username, 'email' => $username]
        );
        
        // Check if user exists
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }
        
        // Check if active
        if (empty($user['is_active'])) {
            return ['success' => false, 'error' => 'Account is inactive.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_code'] = $user['role_code'] ?? 'worker';
        $_SESSION['role_name'] = $user['role_name'] ?? 'User';
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        
        // Update last login
        try {
            $this->db->update('users', [
                'last_login' => date('Y-m-d H:i:s')
            ], SQL_WHERE_USER_ID, ['id' => $user['id']]);
        } catch (Exception $e) {
            // Ignore if last_login column doesn't exist
        }
        
        $this->user = $user;
        
        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user
        ];
    }
    
    // Logout user
    public function logout() {
        $_SESSION = [];
        session_destroy();
        $this->user = null;
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return $this->user !== null || isset($_SESSION['user_id']);
    }
    
    // Get current user
    public function getUser() {
        return $this->user;
    }
    
    // Get user ID
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    // Get user role
    public function getRole() {
        return $_SESSION['role_code'] ?? null;
    }
    
    // Check if user has specific role
    public function hasRole($role) {
        return isset($_SESSION['role_code']) && $_SESSION['role_code'] === $role;
    }
    
    // Check if user has role level or higher
    public function hasRoleLevel($role) {
        if (!isset($_SESSION['role_code'])) {
            return false;
        }
        
        $userLevel = $this->roleHierarchy[$_SESSION['role_code']] ?? 0;
        $requiredLevel = $this->roleHierarchy[$role] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    // Change password
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->fetch(
            "SELECT password FROM users WHERE id = :id",
            ['id' => $userId]
        );
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $this->db->update('users', [
            'password' => $hashedPassword
        ], SQL_WHERE_USER_ID, ['id' => $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
    
    // Create new user
    public function createUser($data) {
        $exists = $this->db->fetch(
            "SELECT id FROM users WHERE username = :username OR email = :email",
            ['username' => $data['username'], 'email' => $data['email']]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }
        
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        
        $userId = $this->db->insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $hashedPassword,
            'role_id' => $data['role_id'] ?? 5,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'is_active' => 1
        ]);
        
        if ($userId) {
            return ['success' => true, 'message' => 'User created successfully.', 'user_id' => $userId];
        }
        
        return ['success' => false, 'message' => 'Failed to create user.'];
    }
    
    // Reset password (admin only)
    public function resetPassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $result = $this->db->update('users', [
            'password' => $hashedPassword
        ], SQL_WHERE_USER_ID, ['id' => $userId]);
        
        if ($result !== false) {
            return ['success' => true, 'message' => 'Password reset successfully.'];
        }
        
        return ['success' => false, 'message' => 'Failed to reset password.'];
    }
    
    // Get all users (admin/hr)
    public function getUsers($filters = []) {
        $sql = "SELECT u.id, u.username, u.email, u.role_id, u.is_active, u.last_login, 
                       u.created_at, u.first_name, u.last_name, r.role_name, r.role_code
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['role_id'])) {
            $sql .= " AND u.role_id = :role_id";
            $params['role_id'] = $filters['role_id'];
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params['is_active'] = $filters['is_active'];
        }
        
        $sql .= " ORDER BY u.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get all users - alias for getUsers (backward compatibility)
    public function getAllUsers($activeOnly = true) {
        $filters = [];
        if ($activeOnly) {
            $filters['is_active'] = 1;
        }
        return $this->getUsers($filters);
    }
    
    // Get user by ID
    public function getUserById($userId) {
        return $this->db->fetch(
            "SELECT u.id, u.username, u.email, u.role_id, u.is_active, u.last_login, 
                    u.created_at, u.first_name, u.last_name, u.phone, r.role_name, r.role_code
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             WHERE u.id = :id",
            ['id' => $userId]
        );
    }
    
    // Update user
    public function updateUser($userId, $data) {
        $allowedFields = ['email', 'role_id', 'first_name', 'last_name', 'phone', 'is_active'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            return ['success' => false, 'message' => 'No data to update.'];
        }
        
        $result = $this->db->update('users', $updateData, SQL_WHERE_USER_ID, ['id' => $userId]);
        
        return ['success' => true, 'message' => 'User updated successfully.'];
    }
    
    // Delete user
    public function deleteUser($userId) {
        // Prevent deleting self
        if (isset($_SESSION['user_id']) && $userId == $_SESSION['user_id']) {
            return ['success' => false, 'message' => 'Cannot delete your own account.'];
        }
        
        $result = $this->db->delete('users', SQL_WHERE_USER_ID, ['id' => $userId]);
        
        return ['success' => true, 'message' => 'User deleted successfully.'];
    }
}
?>
