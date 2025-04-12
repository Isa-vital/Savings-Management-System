<?php
// Start session before destroying it
session_start();

// Define the absolute path to config.php
$config_path = __DIR__ . '/../config.php'; // Adjust based on your actual structure

// Verify the config file exists before requiring
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

// Redirect to login page using absolute URL
$login_url = '/savingssystem/auth/login.php'; // Adjust if your login is in a different location

// Verify login page exists (for debugging)
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $login_url)) {
    die('Login page not found at: ' . $_SERVER['DOCUMENT_ROOT'] . $login_url);
}

header("Location: $login_url");
exit();
?>