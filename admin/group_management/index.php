<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_core_admin();

$page_title = "Group Management";
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Handle Create Group Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $error_message = "CSRF token validation failed. Please try again.";
    } else {
        $group_name = trim($_POST['group_name']);
        $description = trim($_POST['description']);

        if (empty($group_name)) {
            $error_message = "Group name is required.";
        } else {
            try {
                // Check for uniqueness
                $stmt_check = $pdo->prepare("SELECT id FROM groups WHERE group_name = :group_name");
                $stmt_check->execute(['group_name' => $group_name]);
                if ($stmt_check->fetch()) {
                    $error_message = "A group with this name already exists.";
                } else {
                    $stmt_insert = $pdo->prepare("INSERT INTO groups (group_name, description) VALUES (:group_name, :description)");
                    $stmt_insert->execute(['group_name' => $group_name, 'description' => $description]);
                    $_SESSION['success_message'] = "Group '".htmlspecialchars($group_name)."' created successfully.";
                    header("Location: index.php"); // Redirect to clear POST and refresh list
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Group Creation PDOError: " . $e->getMessage());
                $error_message = "Database error creating group: " . $e->getMessage();
            }
        }
    }
}

// Fetch existing groups
$groups = [];
try {
    $stmt_groups = $pdo->query("SELECT id, group_name, description FROM groups ORDER BY group_name ASC");
    $groups = $stmt_groups->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Group Fetch PDOError: " . $e->getMessage());
    $error_message = $error_message ?: "Database error fetching groups: " . $e->getMessage(); // Show only if no other error
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

                <!-- Create New Group Form -->
                <div class="card mb-4">
                    <div class="card-header">Create New Group</div>
                    <div class="card-body">
                        <form action="index.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name</label>
                                <input type="text" class="form-control" id="group_name" name="group_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
                        </form>
                    </div>
                </div>

                <!-- Existing Groups Table -->
                <h2>Existing Groups</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Group Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($groups)): ?>
                                <tr><td colspan="3">No groups found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($groups as $group): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                                        <td><?php echo htmlspecialchars($group['description'] ?? ''); ?></td>
                                        <td>
                                            <a href="edit_group.php?group_id=<?php echo $group['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="delete_group.php?group_id=<?php echo $group['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this group? This will remove all user associations to this group.');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
