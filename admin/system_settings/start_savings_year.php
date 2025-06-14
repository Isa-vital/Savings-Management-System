<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_core_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "CSRF token validation failed. Please try again.";
        header('Location: index.php');
        exit;
    }

    // TODO: Implement database logic to start a new savings year.
    // This includes:
    // 1. Ensuring no other savings year is currently marked as 'active'.
    // 2. Creating a new record in the savings_years table (or equivalent).
    // 3. Setting its start date to the current date/time.
    // 4. Marking this new year as 'active'.
    // 5. Logging this action in an audit trail.
    // Consider any initial setup required for a new year.

    // Placeholder success message
    $_SESSION['success_message'] = "New savings year started successfully (Placeholder - DB logic pending).";

    header('Location: index.php');
    exit;

} else {
    // If not a POST request, redirect to settings page or show an error
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: index.php');
    exit;
}
?>
