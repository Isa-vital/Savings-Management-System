<?php
class GroupAccess {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if user has permission through their groups
     */
    public function hasPermission($userId, $permissionName) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM user_group_mappings ugm
                JOIN group_permissions gp ON ugm.group_id = gp.group_id
                JOIN permissions p ON gp.permission_id = p.id
                WHERE ugm.user_id = ? AND p.name = ?
                LIMIT 1"
            );
            $stmt->execute([$userId, $permissionName]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all groups for a user
     */
    public function getUserGroups($userId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT g.* FROM user_group_mappings ugm
                JOIN user_groups g ON ugm.group_id = g.id
                WHERE ugm.user_id = ?"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to get user groups: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all permissions for a group
     */
    public function getGroupPermissions($groupId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT p.* FROM group_permissions gp
                JOIN permissions p ON gp.permission_id = p.id
                WHERE gp.group_id = ?"
            );
            $stmt->execute([$groupId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to get group permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all available permissions
     */
    public function getAllPermissions() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM permissions ORDER BY module, name");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to get all permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add user to group
     */
    public function addUserToGroup($userId, $groupId) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO user_group_mappings (user_id, group_id) 
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)"
            );
            return $stmt->execute([$userId, $groupId]);
        } catch (PDOException $e) {
            error_log("Failed to add user to group: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove user from group
     */
    public function removeUserFromGroup($userId, $groupId) {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM user_group_mappings 
                 WHERE user_id = ? AND group_id = ?"
            );
            return $stmt->execute([$userId, $groupId]);
        } catch (PDOException $e) {
            error_log("Failed to remove user from group: " . $e->getMessage());
            return false;
        }
    }
}
?>