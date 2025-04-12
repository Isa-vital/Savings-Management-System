<?php
// Start session before destroying it
session_start();

// Verify config path (adjust according to your structure)
$config_path = realpath(__DIR__ . '/../config.php');
if (!$config_path || !file_exists($config_path)) {
    die('Configuration file not found');
}
require_once $config_path;

// Destroy session completely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Clear output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Prevent caching and redirect
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verify login.php exists (debugging)
$login_path = realpath($_SERVER['DOCUMENT_ROOT'] . '/savingssystem/auth/login.php');
if (!$login_path) {
    die('Login page not found at: ' . $_SERVER['DOCUMENT_ROOT'] . '/savingssystem/auth/login.php');
}

// Force redirect to login page
header("Location: /savingssystem/auth/login.php");
exit();
?>