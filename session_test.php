<?php
// This should be the first file included if it handles session_start()
require_once __DIR__ . '/config.php'; 

echo "<h1>Session Test Page</h1>";

// Display PHP and session configuration details for debugging
echo "<h2>Configuration Details:</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
// session_status() output: 0 = PHP_SESSION_DISABLED, 1 = PHP_SESSION_NONE, 2 = PHP_SESSION_ACTIVE
$session_status_text = match(session_status()) {
    0 => 'PHP_SESSION_DISABLED',
    1 => 'PHP_SESSION_NONE',
    2 => 'PHP_SESSION_ACTIVE',
    default => 'Unknown'
};
echo "<p><strong>Session Status:</strong> " . session_status() . " (" . $session_status_text . ")</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";
echo "<p><strong>Session Save Path (from ini_get):</strong> " . htmlspecialchars(ini_get('session.save_path')) . "</p>";
echo "<p><strong>Session Cookie Lifetime:</strong> " . ini_get('session.cookie_lifetime') . " (0 means until browser closes)</p>";
echo "<p><strong>Session Cookie Path:</strong> " . ini_get('session.cookie_path') . "</p>";
echo "<p><strong>Session Cookie Domain:</strong> " . ini_get('session.cookie_domain') . "</p>";
echo "<p><strong>Session Cookie Secure:</strong> " . (ini_get('session.cookie_secure') ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Session Cookie HttpOnly:</strong> " . (ini_get('session.cookie_httponly') ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Session Use Strict Mode:</strong> " . (ini_get('session.use_strict_mode') ? 'Yes' : 'No') . "</p>";


echo "<h2>Session Variable Test:</h2>";

if (isset($_SESSION['minimal_test'])) {
    echo "<p style='color:green;'>Session variable 'minimal_test' IS SET.</p>";
    echo "<p>Current value: " . htmlspecialchars($_SESSION['minimal_test']) . "</p>";
    
    // Increment a counter
    $_SESSION['minimal_test_count'] = isset($_SESSION['minimal_test_count']) ? $_SESSION['minimal_test_count'] + 1 : 1;
    echo "<p>Refresh count: " . $_SESSION['minimal_test_count'] . "</p>";
    
    if ($_SESSION['minimal_test_count'] > 1) {
        echo "<p style='color:green; font-weight:bold;'>Session appears to be persisting across page loads!</p>";
    }

} else {
    echo "<p style='color:red;'>Session variable 'minimal_test' IS NOT SET.</p>";
    echo "<p>Setting 'minimal_test' to 'Hello, Sessions Work!' and 'minimal_test_count' to 1.</p>";
    $_SESSION['minimal_test'] = 'Hello, Sessions Work!';
    $_SESSION['minimal_test_count'] = 1;
    echo "<p>Please refresh the page to see if the variable persists.</p>";
}

echo "<h3>Current Session Data (<code>\$_SESSION</code>):</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<p><a href='session_test.php' class='btn btn-info'>Refresh this page</a></p>";
echo "<p><a href='" . (defined('BASE_URL') ? htmlspecialchars(BASE_URL . 'auth/login.php') : 'auth/login.php') . "' class='btn btn-secondary'>Try Go to Login Page</a> (to see if this session is visible there)</p>";

// Check if the session save path is writable (basic check, might not be foolproof)
echo "<h2>Session Save Path Writable Check:</h2>";
$save_path = ini_get('session.save_path');
if (!empty($save_path)) {
    if (is_dir($save_path)) {
        if (is_writable($save_path)) {
            echo "<p style='color:green;'>Session save path ('" . htmlspecialchars($save_path) . "') is a directory and appears to be writable by PHP.</p>";
        } else {
            echo "<p style='color:red;'>ERROR: Session save path ('" . htmlspecialchars($save_path) . "') is a directory BUT IS NOT WRITABLE by PHP. Check permissions for user: " . get_current_user() . "</p>";
        }
    } else {
        echo "<p style='color:red;'>ERROR: Session save path ('" . htmlspecialchars($save_path) . "') IS NOT A VALID DIRECTORY.</p>";
    }
} else {
    echo "<p style='color:orange;'>Warning: session.save_path is empty in php.ini. PHP will use a system default temporary directory, which might have issues or be harder to debug. Check your PHP configuration (php.ini).</p>";
}

?>
