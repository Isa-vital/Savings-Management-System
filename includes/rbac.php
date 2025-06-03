<?php
class RBAC {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    // Group Management
    public function createGroup($name, $description = '') {
        $stmt = $this->pdo->prepare("INSERT INTO user_groups (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        return $this->pdo->lastInsertId();
    }
    
    public function getGroups() {
        $stmt = $this->pdo->query("SELECT * FROM user_groups ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // Role Management
    public function createRole($name, $description, $permissions) {
        $stmt = $this->pdo->prepare("INSERT INTO group_roles (name, description, permissions) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, json_encode($permissions)]);
        return $this->pdo->lastInsertId();
    }
    
    public function getRoles() {
        $stmt = $this->pdo->query("SELECT * FROM group_roles ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // User Assignment
    public function assignUserToGroup($userId, $groupId, $roleId) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_group_mappings (user_id, group_id, role_id) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)"
        );
        return $stmt->execute([$userId, $groupId, $roleId]);
    }
    
    public function getUserGroups($userId) {
        $stmt = $this->pdo->prepare(
            "SELECT g.*, r.name as role_name, r.permissions 
             FROM user_group_mappings m
             JOIN user_groups g ON m.group_id = g.id
             JOIN group_roles r ON m.role_id = r.id
             WHERE m.user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    // Permission Checking
    public function userHasPermission($userId, $permission) {
        // Check all groups the user belongs to
        $groups = $this->getUserGroups($userId);
        
        foreach ($groups as $group) {
            $permissions = json_decode($group['permissions'], true);
            if (in_array('*', $permissions) || in_array($permission, $permissions)) {
                return true;
            }
        }
        
        return false;
    }
    
    // Get all users in a group
    public function getGroupUsers($groupId) {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.username, u.email, r.name as role_name
             FROM user_group_mappings m
             JOIN users u ON m.user_id = u.id
             JOIN group_roles r ON m.role_id = r.id
             WHERE m.group_id = ?"
        );
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }
}
?>