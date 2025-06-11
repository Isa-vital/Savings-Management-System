<?php
/**
 * Rukindo Kweyamba Savings System - Supercharged Config
 * Now with PDO Security & Better Performance
 */

// ==================== CORE SETTINGS ====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Africa/Nairobi');

// ==================== SESSION HANDLING ==================== // Renamed section for clarity
if (session_status() === PHP_SESSION_NONE) {
    session_start(); 
}

// ==================== DATABASE (PDO POWER) ====================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'savings_mgt_systemdb');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');         // Database charset


try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";port=".DB_PORT.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("DB Connection Failed: " . $e->getMessage());
    die("System temporarily unavailable. Staff notified.");
}

// ==================== ESSENTIAL FUNCTIONS ====================
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($input, $hash) {
    return password_verify($input, $hash);
}

function redirect($url, $delay = 0) {
    if ($delay > 0) {
        header("Refresh: $delay; url=$url");
    } else {
        header("Location: $url");
    }
    exit();
}
//sanitize data function
// In config.php - Replace the sanitize function with this:
function sanitize($data) {
    if (!is_string($data)) return $data;
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ==================== AUTH HELPERS ====================
// It's better if these are primarily in helpers/auth.php and that file includes config.php or is included after it.
// For now, keeping isLoggedIn here as it's simple and used by config itself for other commented out helpers.
function isLoggedIn() {
    // Session is assumed to be started by the block at the top of config.php
    return isset($_SESSION['user']);
}

/*
// This function is now superseded by require_login() in helpers/auth.php
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
        redirect('/auth/login.php');
    }
}
*/

/*
// This function is now superseded by require_admin() in helpers/auth.php
function requireAdmin() {
    requireAuth();
    if ($_SESSION['user']['role'] !== 'admin') { // Old role check
        $_SESSION['error'] = "Administrator privileges required";
        redirect('/dashboard.php');
    }
}
*/

// ==================== CSRF PROTECTION ====================
function generateToken() {
    // Session is assumed to be started by the block at the top of config.php
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateToken($token) {
    // Session is assumed to be started by the block at the top of config.php
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ==================== UTILITIES ====================
function formatPhoneUG($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (preg_match('/^(0|256|\+256)?(7\d{8})$/', $cleaned, $matches)) {
        return '+256 ' . chunk_split($matches[2], 3, ' ');
    }
    return $phone; // Return original if invalid
}

// ==================== APP CONSTANTS ====================
if (!defined('APP_NAME')) { // Define if not already defined
    define('APP_NAME', 'Rukindo Kweyamba Savings Group');
}
define('APP_VERSION', '1.0.0'); // Application version
// Ensure this line is active (uncommented) and replaces any dynamic BASE_URL definition for this test.
define('BASE_URL', 'http://localhost/savingssystem/');   ///uncomment this in dev environment

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15 minutes


// ==================== EMAIL (PHPMailer SMTP) SETTINGS ====================

define('SMTP_HOST', 'smtp.gmail.com'); // e.g., 'smtp.gmail.com' or our hosting provider's SMTP server
define('SMTP_USERNAME', 'info.rksavingssystem@gmail.com');    // our SMTP username (often your email address)
define('SMTP_PASSWORD', 'xsrc mqgl fsum lizm');                // our SMTP password (or app-specific password)
define('SMTP_PORT', 587);                                 // SMTP port (e.g., 587 for TLS, 465 for SSL, 25 for unencrypted)
define('SMTP_ENCRYPTION', 'tls');                         // SMTP encryption: 'tls' (recommended), 'ssl', or false for none
                                                         
define('MAIL_FROM_EMAIL', 'info.rksavingssystem@gmail.com');      // The email address system emails will be sent from
define('MAIL_FROM_NAME', (defined('APP_NAME') ? APP_NAME : 'Savings App') . ' Support'); // Uses APP_NAME if defined


// ==================== AUTO-CLOSE CONNECTION ====================
register_shutdown_function(function() {
    global $pdo;
    if ($pdo) { // Check if $pdo was successfully initialized
        $pdo = null; // Proper PDO connection closure
    }
});