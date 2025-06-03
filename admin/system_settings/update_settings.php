<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_core_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Allow only POST requests
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: index.php');
    exit();
}

// CSRF Token Validation
if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
    $_SESSION['error_message'] = "CSRF token validation failed. Please try again.";
    // Log this attempt for security monitoring
    error_log("CSRF validation failed for user: " . ($_SESSION['user']['username'] ?? 'unknown_user'));
    header('Location: index.php');
    exit();
}

// Define expected settings keys (should match those in index.php)
$expected_settings_keys = [
    'site_name',
    'interest_rate',
    'loan_processing_fee',
    'notification_email_from',
    'currency_symbol'
];

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO system_settings (setting_key, setting_value)
            VALUES (:setting_key, :setting_value)
            ON DUPLICATE KEY UPDATE setting_value = :setting_value_update";

    $stmt = $pdo->prepare($sql);

    foreach ($expected_settings_keys as $key) {
        if (isset($_POST[$key])) {
            $value = $_POST[$key];
            // Basic sanitization/validation can be added here per setting type
            // For example, ensuring numeric types are indeed numeric
            if (($key === 'interest_rate' || $key === 'loan_processing_fee') && !is_numeric($value)) {
                throw new Exception("Invalid numeric value for " . htmlspecialchars($key));
            }
            if ($key === 'notification_email_from' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                 throw new Exception("Invalid email format for Notification From Email.");
            }

            $stmt->bindParam(':setting_key', $key);
            $stmt->bindParam(':setting_value', $value);
            $stmt->bindParam(':setting_value_update', $value); // For the UPDATE part
            $stmt->execute();
        }
    }

    $pdo->commit();
    $_SESSION['success_message'] = "System settings updated successfully.";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Database error updating settings: " . $e->getMessage();
    // Log detailed error
    error_log("System Settings PDOException for user " . ($_SESSION['user']['username'] ?? 'unknown_user') . ": " . $e->getMessage());
} catch (Exception $e) {
    // Catch custom exceptions (like validation errors)
     if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Should not be necessary if exception is before transaction logic
    }
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

// Regenerate CSRF token after successful POST processing (optional, but good practice)
// generateToken(); // Assuming this function not only returns but also sets the new token in session.

header('Location: index.php');
exit();
?>
