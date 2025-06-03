<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_core_admin();

$page_title = "Edit Group";
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null; // Usually not needed here as we redirect
unset($_SESSION['error_message'], $_SESSION['success_message']);

$group_id = $_GET['group_id'] ?? null;
$group = null;

if (!$group_id || !filter_var($group_id, FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid group ID provided.";
    header('Location: index.php');
    exit();
}

// Fetch group details
try {
    $stmt_group = $pdo->prepare("SELECT id, group_name, description FROM groups WHERE id = :group_id");
    $stmt_group->execute(['group_id' => $group_id]);
    $group = $stmt_group->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        $_SESSION['error_message'] = "Group not found.";
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Group Fetch for Edit PDOError: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error fetching group details: " . $e->getMessage();
    header('Location: index.php');
    exit();
}


// Handle Edit Group Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group'])) {
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $error_message = "CSRF token validation failed. Please try again.";
    } elseif (empty($_POST['group_id_hidden']) || (int)$_POST['group_id_hidden'] !== (int)$group_id) {
        // Basic check to ensure the form submitted is for the correct group
        $error_message = "Form submission error. Group ID mismatch.";
    }
    else {
        $group_name = trim($_POST['group_name']);
        $description = trim($_POST['description']);

        if (empty($group_name)) {
            $error_message = "Group name is required.";
        } else {
            try {
                // Check for uniqueness (excluding current group)
                $stmt_check = $pdo->prepare("SELECT id FROM groups WHERE group_name = :group_name AND id != :current_group_id");
                $stmt_check->execute(['group_name' => $group_name, 'current_group_id' => $group_id]);
                if ($stmt_check->fetch()) {
                    $error_message = "Another group with this name already exists.";
                } else {
                    $stmt_update = $pdo->prepare("UPDATE groups SET group_name = :group_name, description = :description WHERE id = :group_id");
                    $stmt_update->execute([
                        'group_name' => $group_name,
                        'description' => $description,
                        'group_id' => $group_id
                    ]);
                    $_SESSION['success_message'] = "Group '".htmlspecialchars($group_name)."' updated successfully.";
                    header("Location: index.php"); // Redirect to clear POST and refresh list
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Group Update PDOError: " . $e->getMessage());
                $error_message = "Database error updating group: " . $e->getMessage();
            }
        }
        // If there was an error, the form will re-populate with current $group data, but we need to show new POSTed values if they exist
        $group['group_name'] = $group_name ?? $group['group_name'];
        $group['description'] = $description ?? $group['description'];
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
                    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($group['group_name'] ?? ''); ?></h1>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">Edit Group Details</div>
                    <div class="card-body">
                        <form action="edit_group.php?group_id=<?php echo htmlspecialchars($group_id); ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                            <input type="hidden" name="group_id_hidden" value="<?php echo htmlspecialchars($group_id); ?>">
                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name</label>
                                <input type="text" class="form-control" id="group_name" name="group_name" value="<?php echo htmlspecialchars($group['group_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($group['description'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="update_group" class="btn btn-primary">Update Group</button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
