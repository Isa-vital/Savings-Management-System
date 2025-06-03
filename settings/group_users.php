<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/GroupAccess.php';

$groupAccess = new GroupAccess($pdo);

// Check permission
if (!$groupAccess->hasPermission($_SESSION['admin']['id'], 'user_management')) {
    $_SESSION['error'] = "You don't have permission to manage users";
    header("Location: /savingssystem/dashboard.php");
    exit;
}

// Handle group assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_POST['user_id'];
    $groupId = (int)$_POST['group_id'];
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $success = $groupAccess->addUserToGroup($userId, $groupId);
    } else {
        $success = $groupAccess->removeUserFromGroup($userId, $groupId);
    }
    
    if ($success) {
        $_SESSION['success'] = "User group assignment updated successfully";
    } else {
        $_SESSION['error'] = "Failed to update user group assignment";
    }
    
    header("Location: group_users.php");
    exit;
}

// Get all users and groups
$users = $pdo->query("SELECT id, username, email FROM users ORDER BY username")->fetchAll();
$groups = $pdo->query("SELECT * FROM user_groups ORDER BY name")->fetchAll();

// Get user-group mappings
$userGroups = [];
$stmt = $pdo->query("SELECT user_id, group_id FROM user_group_mappings");
while ($row = $stmt->fetch()) {
    $userGroups[$row['user_id']][] = $row['group_id'];
}
?>

<div class="container">
    <h2>User Group Assignments</h2>
    
    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <?php foreach ($groups as $group): ?>
                <th class="text-center"><?= htmlspecialchars($group['name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($user['username']) ?>
                    <small class="text-muted d-block"><?= $user['email'] ?></small>
                </td>
                <?php foreach ($groups as $group): 
                    $inGroup = isset($userGroups[$user['id']]) && in_array($group['id'], $userGroups[$user['id']]);
                ?>
                <td class="text-center">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <input type="hidden" name="action" value="<?= $inGroup ? 'remove' : 'add' ?>">
                        
                        <button type="submit" class="btn btn-sm <?= $inGroup ? 'btn-success' : 'btn-outline-secondary' ?>">
                            <i class="fas <?= $inGroup ? 'fa-check' : 'fa-times' ?>"></i>
                        </button>
                    </form>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>