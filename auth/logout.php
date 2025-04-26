<?php
// Start session before destroying it
session_start();

// Define the base directory
$base_dir = __DIR__ . '/../';

// Verify the config file exists before requiring
$config_path = $base_dir . 'config.php';
if (!file_exists($config_path)) {
    die('Configuration file not found at: ' . $config_path);
}

require_once $config_path;

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Calculate the correct path to login.php
$login_path = '/savingssystem/auth/login.php';

// For debugging path issues (comment out in production)
// error_log("Attempting to redirect to: " . $login_path);

// Redirect to login page
header("Location: $login_path");
exit();
?>