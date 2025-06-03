<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/GroupAccess.php';

$groupAccess = new GroupAccess($pdo);

// Check admin permission
if (!$groupAccess->hasPermission($_SESSION['admin']['id'], 'manage_settings')) {
    header("Location: /savingssystem/dashboard.php");
    exit;
}

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_POST['user_id'];
    $permissionId = (int)$_POST['permission_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'grant') {
            $stmt = $pdo->prepare(
                "INSERT INTO user_permissions (user_id, permission_id) 
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)"
            );
            $stmt->execute([$userId, $permissionId]);
            $_SESSION['success'] = "Permission granted successfully";
        } else {
            $stmt = $pdo->prepare(
                "DELETE FROM user_permissions 
                 WHERE user_id = ? AND permission_id = ?"
            );
            $stmt->execute([$userId, $permissionId]);
            $_SESSION['success'] = "Permission revoked successfully";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating permission: " . $e->getMessage();
    }
    
    header("Location: permissions.php");
    exit;
}

// Get all users and permissions
$users = $pdo->query("SELECT id, username, role FROM users ORDER BY username")->fetchAll();
$permissions = $groupAccess->getAllPermissions();
$userPermissions = [];

foreach ($users as $user) {
    $userPermissions[$user['id']] = $groupAccess->getUserPermissions($user['id']);
}
?>

<!-- HTML Interface -->
<div class="container">
    <h2>Permission Management</h2>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>User</th>
                <?php foreach ($permissions as $perm): ?>
                <th><?= htmlspecialchars($perm['description']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($user['username']) ?>
                    <small class="text-muted d-block"><?= $user['role'] ?></small>
                </td>
                <?php foreach ($permissions as $perm): ?>
                <td class="text-center">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="permission_id" value="<?= $perm['id'] ?>">
                        <?php if (in_array($perm['name'], $userPermissions[$user['id']])): ?>
                            <button type="submit" name="action" value="revoke" class="btn btn-sm btn-success">
                                <i class="fas fa-check"></i>
                            </button>
                        <?php else: ?>
                            <button type="submit" name="action" value="grant" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </form>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>