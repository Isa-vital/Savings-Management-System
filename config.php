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

// ==================== SECURE SESSION ====================

// session_start([
//     'name' => 'SaccoSecureSession',
//     'cookie_lifetime' => 86400,
//     'cookie_secure' => true,
//     'cookie_httponly' => true,
//     'use_strict_mode' => true
// ]);

// ==================== DATABASE (PDO POWER) ====================
define('DB_HOST', 'localhost');
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
function isLoggedIn() {
    return isset($_SESSION['user']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
        redirect('/auth/login.php');
    }
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Administrator privileges required";
        redirect('/dashboard.php');
    }
}

// ==================== CSRF PROTECTION ====================
function generateToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateToken($token) {
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
define('APP_NAME', 'Rukindo Kweyamba Savings Group');// Replace BASE_URL definition with this:
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/savingssystem/');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15 minutes

// ==================== AUTO-CLOSE CONNECTION ====================
register_shutdown_function(function() {
    global $pdo;
    $pdo = null; // Proper PDO connection closure
});