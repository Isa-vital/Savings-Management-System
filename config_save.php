<?php


// ==================== CORE SETTINGS ====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Africa/Nairobi');

// ==================== SECURE SESSION ====================
if (session_status() === PHP_SESSION_NONE) {
    // Basic session start
    session_start();

    // Or, for more secure options (ensure these are appropriate for your server setup):

    session_start([
        'name' => 'SaccoSecureSession', // Custom session name
        'cookie_lifetime' => 86400,    // Session cookie lifetime in seconds (1 day)
        'cookie_secure' => isset($_SERVER['HTTPS']), // Send cookie only over HTTPS
        'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
        'use_strict_mode' => true,     // Helps prevent session fixation
        // 'save_path' => '/path/to/your/custom/session/save_path', // Optional: custom save path
    ]);
}

// ==================== DATABASE (PDO POWER) ====================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'savings_mgt_systemdb');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');         // Database charset
define('BASE_URL', 'http://localhost/savingssystem/');   ///uncomment this in dev environment



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
die("Oops! \n. Database not well connected!");
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
define('APP_NAME', 'Rukindo Kweyamba Savings Group');
//define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/'); // Ensure this correctly reflects your base URL, including subdirectories if any.
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15 minutes


// ==================== EMAIL (PHPMailer SMTP) SETTINGS ====================
// --- IMPORTANT: Fill these placeholders with your actual SMTP credentials ---
define('SMTP_HOST', 'smtp.gmail.com'); // e.g., 'smtp.gmail.com' or your hosting provider's SMTP server
define('SMTP_USERNAME', 'isaacvital44@gmail.com');    // Your SMTP username (often your email address)
define('SMTP_PASSWORD', 'kpgt iiqs xhhh dsie');                // Your SMTP password (or app-specific password)
define('SMTP_PORT', 587);                                 // SMTP port (e.g., 587 for TLS, 465 for SSL, 25 for unencrypted)
define('SMTP_ENCRYPTION', 'tls');                         // SMTP encryption: 'tls' (recommended), 'ssl', or false for none
                                                          // For PHPMailer, this translates to:
                                                          // 'tls' -> PHPMailer::ENCRYPTION_STARTTLS
                                                          // 'ssl' -> PHPMailer::ENCRYPTION_SMTPS
                                                          // false -> $mail->SMTPSecure = false; (though might still use opportunistic TLS)

define('MAIL_FROM_EMAIL', 'isaacvital44@gmail.com');      // The email address system emails will be sent from
define('MAIL_FROM_NAME', (defined('APP_NAME') ? APP_NAME : 'Savings App') . ' Support'); // Uses APP_NAME if defined

// --- PHPMailer Path ---
// If you installed PHPMailer via Composer, this should be all you need in scripts that send email:
// require_once __DIR__ . '/vendor/autoload.php';
//
// If you've manually downloaded PHPMailer, you might need to define paths to its classes,
// or include them directly in the scripts. For now, we'll assume Composer / autoloading.
// Example for manual include (less common now):
// define('PHPMAILER_PATH_PHPMAILER', __DIR__ . '/path/to/phpmailer/src/PHPMailer.php');
// define('PHPMAILER_PATH_SMTP', __DIR__ . '/path/to/phpmailer/src/SMTP.php');
// define('PHPMAILER_PATH_EXCEPTION', __DIR__ . '/path/to/phpmailer/src/Exception.php');


// ==================== AUTO-CLOSE CONNECTION ====================
register_shutdown_function(function() {
    global $pdo;
    $pdo = null; // Proper PDO connection closure
});