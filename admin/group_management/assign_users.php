<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_core_admin();

$page_title = "Assign Users to Groups";
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);

$selected_group_id = $_GET['group_id'] ?? $_POST['group_id'] ?? null;
$selected_group = null;
$assigned_users = [];
$available_users_for_add = [];

// Fetch all lists for dropdowns
try {
    $groups_list = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $users_list_all = $pdo->query("SELECT id, username, email FROM users WHERE is_active = 1 ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    $roles_list = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Assign Users Page - Data Fetch PDOError: " . $e->getMessage());
    $error_message = "Database error fetching initial data: " . $e->getMessage();
    // Avoid further processing if essential lists can't be fetched
    $groups_list = $users_list_all = $roles_list = [];
}


// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "CSRF token validation failed.";
    } elseif (empty($selected_group_id) || !filter_var($selected_group_id, FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = "Invalid or missing Group ID for the action.";
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = $_POST['user_id'] ?? null;
        $role_id = $_POST['role_id'] ?? $_POST['new_role_id'] ?? null;

        try {
            if ($user_id && !filter_var($user_id, FILTER_VALIDATE_INT)) throw new Exception("Invalid User ID.");
            if (($action === 'add_to_group' || $action === 'update_role_in_group') && $role_id && !filter_var($role_id, FILTER_VALIDATE_INT)) {
                 throw new Exception("Invalid Role ID.");
            }

            if ($action === 'add_to_group' && $user_id && $role_id) {
                // UPSERT: Add user or update their role if they are already in the group
                $stmt = $pdo->prepare("INSERT INTO user_group_roles (user_id, group_id, role_id)
                                       VALUES (:user_id, :group_id, :role_id)
                                       ON DUPLICATE KEY UPDATE role_id = :role_id");
                $stmt->execute(['user_id' => $user_id, 'group_id' => $selected_group_id, 'role_id' => $role_id]);
                $_SESSION['success_message'] = "User successfully added to group or role updated.";
            } elseif ($action === 'update_role_in_group' && $user_id && $role_id) {
                $stmt = $pdo->prepare("UPDATE user_group_roles SET role_id = :role_id
                                       WHERE user_id = :user_id AND group_id = :group_id");
                $stmt->execute(['role_id' => $role_id, 'user_id' => $user_id, 'group_id' => $selected_group_id]);
                $_SESSION['success_message'] = "User's role updated successfully in the group.";
            } elseif ($action === 'remove_from_group' && $user_id) {
                $stmt = $pdo->prepare("DELETE FROM user_group_roles
                                       WHERE user_id = :user_id AND group_id = :group_id");
                $stmt->execute(['user_id' => $user_id, 'group_id' => $selected_group_id]);
                $_SESSION['success_message'] = "User removed from group successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid action or missing parameters.";
            }
        } catch (PDOException $e) {
            error_log("Assign Users POST Action PDOError: " . $e->getMessage());
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
    // Redirect to the same page with group_id to show results and clear POST
    header("Location: assign_users.php?group_id=" . $selected_group_id);
    exit();
}


// If a group is selected (either by GET or after POST redirect)
if ($selected_group_id && filter_var($selected_group_id, FILTER_VALIDATE_INT)) {
    try {
        $stmt_group = $pdo->prepare("SELECT id, group_name FROM groups WHERE id = :group_id");
        $stmt_group->execute(['group_id' => $selected_group_id]);
        $selected_group = $stmt_group->fetch(PDO::FETCH_ASSOC);

        if ($selected_group) {
            $stmt_assigned = $pdo->prepare("
                SELECT u.id as user_id, u.username, u.email, r.id as role_id, r.role_name
                FROM users u
                JOIN user_group_roles ugr ON u.id = ugr.user_id
                JOIN roles r ON ugr.role_id = r.id
                WHERE ugr.group_id = :group_id
                ORDER BY u.username ASC
            ");
            $stmt_assigned->execute(['group_id' => $selected_group_id]);
            $assigned_users = $stmt_assigned->fetchAll(PDO::FETCH_ASSOC);

            // Filter users for the "Add User" dropdown (those not already in this group)
            $assigned_user_ids = array_column($assigned_users, 'user_id');
            foreach ($users_list_all as $user) {
                if (!in_array($user['id'], $assigned_user_ids)) {
                    $available_users_for_add[] = $user;
                }
            }
        } else {
            $error_message = $error_message ?: "Selected group not found.";
            $selected_group_id = null; // Reset if group not found
        }
    } catch (PDOException $e) {
        error_log("Assign Users Page - Selected Group Data Fetch PDOError: " . $e->getMessage());
        $error_message = $error_message ?: "Database error fetching group details: " . $e->getMessage();
        $selected_group_id = null; // Reset on error
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars($settings['site_name'] ?? APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <!-- Group Selection Form -->
                <div class="card mb-4">
                    <div class="card-header">Select Group to Manage</div>
                    <div class="card-body">
                        <form action="assign_users.php" method="GET" class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label for="group_id_select" class="form-label">Select Group:</label>
                                <select class="form-select" id="group_id_select" name="group_id" required>
                                    <option value="">-- Select a Group --</option>
                                    <?php foreach ($groups_list as $group_item): ?>
                                        <option value="<?php echo $group_item['id']; ?>" <?php echo ($selected_group_id == $group_item['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group_item['group_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">View/Manage Members</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selected_group_id && $selected_group): ?>
                    <h2 class="mb-3">Managing Group: <?php echo htmlspecialchars($selected_group['group_name']); ?></h2>

                    <!-- Assigned Users Table -->
                    <div class="card mb-4">
                        <div class="card-header">Assigned Users</div>
                        <div class="card-body">
                            <?php if (empty($assigned_users)): ?>
                                <p>No users are currently assigned to this group.</p>
                            <?php else: ?>
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Current Role in Group</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_users as $assigned_user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assigned_user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($assigned_user['email']); ?></td>
                                                <form action="assign_users.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                                                    <input type="hidden" name="action" value="update_role_in_group">
                                                    <input type="hidden" name="user_id" value="<?php echo $assigned_user['user_id']; ?>">
                                                    <input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>">
                                                    <td>
                                                        <select name="new_role_id" class="form-select form-select-sm" required>
                                                            <?php foreach ($roles_list as $role): ?>
                                                                <option value="<?php echo $role['id']; ?>" <?php echo ($assigned_user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <button type="submit" class="btn btn-sm btn-success">Update Role</button>
                                                </form>
                                                <form action="assign_users.php" method="POST" class="d-inline ms-1">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                                                    <input type="hidden" name="action" value="remove_from_group">
                                                    <input type="hidden" name="user_id" value="<?php echo $assigned_user['user_id']; ?>">
                                                    <input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this user from the group?');">Remove</button>
                                                </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Add User to Group Form -->
                    <div class="card">
                        <div class="card-header">Add User to "<?php echo htmlspecialchars($selected_group['group_name']); ?>"</div>
                        <div class="card-body">
                            <form action="assign_users.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                                <input type="hidden" name="action" value="add_to_group">
                                <input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label for="user_id_add" class="form-label">Select User</label>
                                        <select class="form-select" id="user_id_add" name="user_id" required>
                                            <option value="">-- Select User --</option>
                                            <?php foreach ($available_users_for_add as $user_to_add): ?>
                                                <option value="<?php echo $user_to_add['id']; ?>">
                                                    <?php echo htmlspecialchars($user_to_add['username'] . ' (' . $user_to_add['email'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if(empty($available_users_for_add)): ?>
                                                 <option value="" disabled>No new users available to add</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="role_id_add" class="form-label">Select Role for User</label>
                                        <select class="form-select" id="role_id_add" name="role_id" required>
                                            <option value="">-- Select Role --</option>
                                            <?php foreach ($roles_list as $role): ?>
                                                <option value="<?php echo $role['id']; ?>">
                                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">Add User to Group</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
