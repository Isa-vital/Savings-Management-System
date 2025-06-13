<?php
// Session is expected to be started by config.php,
// which should be included by the parent script before this navbar partial.
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// Attempt to include config.php if not already done by parent script
// This is tricky because the path to config.php varies based on the including file's location.
// The most reliable way is for config.php to be included by the entry-point script.
// However, to prevent fatal errors in navbar.php itself if BASE_URL is missing:

if (!defined('APP_NAME')) {
    if (file_exists(__DIR__ . '/../config.php')) {
        @include_once __DIR__ . '/../config.php'; // Try to load from parent of partials
    }
    if (!defined('APP_NAME')) { // If still not defined after include attempt
        define('APP_NAME', 'Savings System'); // Absolute fallback
    }
}

if (!defined('BASE_URL')) {
    // If config.php was included by the parent, BASE_URL should be defined.
    // If navbar.php tried to include config.php above, it might be defined.
    // If still not defined, create a fallback. This fallback is basic.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Fallback host

    // Try to guess if inside 'savingssystem' subdirectory
    $uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $path_parts = explode('/', trim($uri_path, '/'));

    $base_path_segment = '';
    // A common pattern is the project being in a subdirectory like '/savingssystem/'
    // This is a heuristic and might need adjustment for other structures.
    if (isset($path_parts[0]) && $path_parts[0] === 'savingssystem') {
        $base_path_segment = '/' . $path_parts[0] . '/';
    } else {
        // If not in 'savingssystem', or if path is complex, assume root or a simpler path.
        // This might need to be manually configured in config.php for best results.
        // For navbar, if included from admin/user_management/index.php, SCRIPT_NAME would be /savingssystem/admin/user_management/index.php
        // We need to get back to /savingssystem/
        $script_name = $_SERVER['SCRIPT_NAME'] ?? ''; // e.g. /savingssystem/admin/user_management/index.php
        if (preg_match('/^(\/[^\/]+\/)/', $script_name, $matches)) {
            $base_path_segment = $matches[1]; // e.g. /savingssystem/
        } else {
            $base_path_segment = '/'; // Default to root if no clear segment found
        }
    }

    define('BASE_URL', $protocol . $host . rtrim($base_path_segment, '/') . '/');
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm px-3 py-3">
    <div class="container-fluid">
        <!-- App Name -->
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center" href="<?= htmlspecialchars(BASE_URL . 'index.php') ?>">
            <i class="fas fa-cubes me-3 fs-3 text-light"></i>
            <?= htmlspecialchars(APP_NAME) ?>
        </a>

        <!-- User Dropdown -->
        <ul class="navbar-nav ms-auto">
            <?php if (isset($_SESSION['user']['id'])): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center fs-5" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2 fs-3 text-light"></i>
                    <span class="text-light">
                        <?php 
                        if (isset($_SESSION['user']['username'])) {
                            echo htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']);
                        } else { 
                            echo "User"; // Fallback if username somehow not set
                        }
                        ?>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <a class="dropdown-item fs-6" href="<?= htmlspecialchars(BASE_URL . 'profile.php') ?>">
                            <i class="fas fa-user me-2"></i> Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger fs-6" href="<?= htmlspecialchars(BASE_URL . 'auth/logout.php') ?>">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link fs-5" href="<?= htmlspecialchars(BASE_URL . 'auth/login.php') ?>">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                     <a class="nav-link fs-5 ms-2 btn btn-primary btn-sm text-white" href="<?= htmlspecialchars(BASE_URL . 'auth/register.php') ?>">
                        <i class="fas fa-user-plus me-2"></i>Sign Up
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
