<?php
require_once __DIR__ . '/../includes/rbac.php';

class SettingsController {
    private $rbac;
    
    public function __construct() {
        global $pdo;
        $this->rbac = new RBAC($pdo);
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? 'dashboard';
        
        switch ($action) {
            case 'groups':
                $this->groupsAction();
                break;
            case 'roles':
                $this->rolesAction();
                break;
            case 'assign':
                $this->assignAction();
                break;
            default:
                $this->dashboardAction();
        }
    }
    
    private function dashboardAction() {
        $groups = $this->rbac->getGroups();
        $roles = $this->rbac->getRoles();
        include 'views/dashboard.php';
    }
    
    private function groupsAction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $this->rbac->createGroup($name, $description);
            $_SESSION['success'] = "Group created successfully";
            header("Location: ?action=groups");
            exit;
        }
        
        $groups = $this->rbac->getGroups();
        include 'views/groups.php';
    }
    
    private function rolesAction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $permissions = $_POST['permissions'] ?? [];
            
            $this->rbac->createRole($name, $description, $permissions);
            $_SESSION['success'] = "Role created successfully";
            header("Location: ?action=roles");
            exit;
        }
        
        $roles = $this->rbac->getRoles();
        $allPermissions = [
            'dashboard' => 'Access Dashboard',
            'members_view' => 'View Members',
            'members_manage' => 'Manage Members',
            'savings_view' => 'View Savings',
            'savings_manage' => 'Manage Savings',
            'loans_view' => 'View Loans',
            'loans_manage' => 'Manage Loans',
            'settings_manage' => 'Manage Settings',
            'reports_view' => 'View Reports',
            'reports_generate' => 'Generate Reports'
        ];
        
        include 'views/roles.php';
    }
    
    private function assignAction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)$_POST['user_id'];
            $groupId = (int)$_POST['group_id'];
            $roleId = (int)$_POST['role_id'];
            
            $this->rbac->assignUserToGroup($userId, $groupId, $roleId);
            $_SESSION['success'] = "User assigned successfully";
            header("Location: ?action=assign&group_id=$groupId");
            exit;
        }
        
        $groupId = $_GET['group_id'] ?? null;
        $groups = $this->rbac->getGroups();
        $roles = $this->rbac->getRoles();
        
        // Get users not in this group
        $users = [];
        if ($groupId) {
            $stmt = $this->pdo->prepare(
                "SELECT u.* FROM users u
                 WHERE u.id NOT IN (
                     SELECT user_id FROM user_group_mappings WHERE group_id = ?
                 )"
            );
            $stmt->execute([$groupId]);
            $users = $stmt->fetchAll();
        }
        
        include 'views/assign.php';
    }
}
?>