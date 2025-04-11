<?php
/**
 * Savings Management System - Configuration File
 * 
 * Handles database connections, sessions, and core functions
 */

// ==================== ERROR REPORTING ====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== SESSION SETUP ====================
session_start([
    'cookie_lifetime' => 86400, // 1 day
    'cookie_secure'   => true,   // Requires HTTPS
    'cookie_httponly' => true,   // Prevent JS access
    'use_strict_mode' => true    // Enhanced session security
]);

// ==================== DATABASE CONFIGURATION ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', "");
define('DB_NAME', 'savings_mgt_systemdb');
define('DB_PORT', 3306);

// ==================== DATABASE CONNECTION ====================
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log($e->getMessage());
    die("System maintenance in progress. Please try again later.");
}

// ==================== SECURITY FUNCTIONS ====================
/**
 * Sanitize input data
 */
function sanitize($data) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($data))));
}

/**
 * Password hashing (using PHP password_hash)
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Redirect with optional status code
 */
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

/**
 * CSRF token generation/validation
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ==================== UTILITY FUNCTIONS ====================
/**
 * Formats Ugandan phone numbers to a standard +256 XXX XXX XXX format
 * @param string $phone The raw phone number
 * @return string Formatted phone number
 */
function formatUgandanPhone($phone) {
    if (empty($phone)) {
        return 'N/A';
    }

    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle different number formats
    if (strlen($phone) === 9 && substr($phone, 0, 1) !== '0') {
        // Already in 771234567 format
        return '+256 ' . chunk_split($phone, 3, ' ');
    }
    elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        // 0771234567 format
        return '+256 ' . chunk_split(substr($phone, 1), 3, ' ');
    }
    elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '256') {
        // 256771234567 format
        return '+256 ' . chunk_split(substr($phone, 3), 3, ' ');
    }
    elseif (strlen($phone) === 13 && substr($phone, 0, 4) === '+256') {
        // +256771234567 format
        return '+256 ' . chunk_split(substr($phone, 4), 3, ' ');
    }
    
    // Return original if not a standard Ugandan number
    return $phone;
}

/**
 * Validates Ugandan phone numbers
 */
function isValidUgandanPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(0|256|\+256)?(7|3)\d{8}$/', $phone);
}

// ==================== APPLICATION SETTINGS ====================
define('APP_NAME', 'Rukindo Kweyamba Savings Management System');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/savingssystem/');
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_RESET_TIMEOUT', 3600); // 1 hour

// ==================== AUTOLOAD CLASSES ====================
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ==================== TIMEZONE SETTING ====================
date_default_timezone_set('Africa/Nairobi');

// ==================== ERROR HANDLER ====================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] in $errfile on line $errline: $errstr");
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo "<div class='alert alert-danger'>System Error: $errstr</div>";
    }
});

// ==================== SHUTDOWN FUNCTION ====================
register_shutdown_function(function() {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
});

// ==================== AUTHENTICATION HELPERS ====================
function is_logged_in() {
    return isset($_SESSION['user']);
}

function require_auth() {
    if (!is_logged_in()) {
        redirect('auth/login.php');
    }
}

function require_admin() {
    require_auth();
    if ($_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Admin access required";
        redirect('index.php');
    }
}