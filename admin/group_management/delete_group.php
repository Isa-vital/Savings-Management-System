<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_core_admin();

// For GET requests, CSRF protection is harder. A unique, short-lived token in the URL is better.
// For this task, we are proceeding with direct GET as per simplified instructions,
// but acknowledging POST from a form with CSRF is the standard secure method.
// A JavaScript confirm() was added in index.php to provide a basic user confirmation.

$group_id = $_GET['group_id'] ?? null;

if (!$group_id || !filter_var($group_id, FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid group ID provided for deletion.";
    header('Location: index.php');
    exit();
}

try {
    // First, check if the group exists to provide a more specific message
    $stmt_check = $pdo->prepare("SELECT group_name FROM groups WHERE id = :group_id");
    $stmt_check->execute(['group_id' => $group_id]);
    $group = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        $_SESSION['error_message'] = "Group not found or already deleted.";
        header('Location: index.php');
        exit();
    }

    // Delete the group. ON DELETE CASCADE for `user_group_roles.group_id` will handle associations.
    $stmt_delete = $pdo->prepare("DELETE FROM groups WHERE id = :group_id");
    $stmt_delete->execute(['group_id' => $group_id]);

    if ($stmt_delete->rowCount() > 0) {
        $_SESSION['success_message'] = "Group '".htmlspecialchars($group['group_name'])."' and its user associations deleted successfully.";
    } else {
        // Should ideally not happen if the check above passed, unless a race condition.
        $_SESSION['error_message'] = "Could not delete group '".htmlspecialchars($group['group_name'])."'. It might have been deleted by another process.";
    }

} catch (PDOException $e) {
    error_log("Group Deletion PDOError for group_id " . $group_id . ": " . $e->getMessage());
    // Check for foreign key constraint violation if ON DELETE CASCADE wasn't set up or failed for some reason
    if (str_contains($e->getMessage(), "foreign key constraint fails")) {
         $_SESSION['error_message'] = "Could not delete group '".htmlspecialchars($group['group_name'] ?? 'ID:'.$group_id)."' because it is still referenced by other parts of the system that were not automatically cleaned up. Please check user assignments.";
    } else {
        $_SESSION['error_message'] = "Database error deleting group: " . $e->getMessage();
    }
}

header('Location: index.php');
exit();
?>
