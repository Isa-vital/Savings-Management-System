<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Attempt to include config.php for APP_NAME and BASE_URL, with fallbacks
$app_name_navbar = 'SACCO App'; // Default
// Default base_url_navbar: Assumes navbar is included from files in root, or one level down (e.g. admin/index.php includes ../partials/navbar.php)
// So, for links from root, it's './', for links from one level down, it's '../' to get to root.
// A more robust way is if BASE_URL from config.php is always the absolute web path to project root.

$base_url_navbar_to_root = ''; // Path from current file to project root
if (isset($included_from_root) && $included_from_root === true) { // A hypothetical variable set by including file
    $base_url_navbar_to_root = './';
} else {
    // Check common locations of config.php relative to this partial
    if (file_exists(__DIR__ . '/../config.php')) { // If partials/navbar.php and config.php is in root
        @include_once __DIR__ . '/../config.php';
        $base_url_navbar_to_root = rtrim(BASE_URL ?? '../', '/'); // Use defined BASE_URL or default to '../'
    } elseif (file_exists(__DIR__ . '/../../config.php')) { // If partials/navbar.php and config.php is in ../../ (e.g. partials in includes/)
         @include_once __DIR__ . '/../../config.php';
         $base_url_navbar_to_root = rtrim(BASE_URL ?? '../../', '/');
    } else { // Fallback if config not found easily
        // This basic fallback assumes the URL structure matches file structure from webroot
        // This part is tricky without knowing the exact include context of navbar.php
        // For now, relying on BASE_URL from config.php to be the primary source.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // dir of including script
        // Crude way to guess project root if in a subdir like 'partials'
        if (basename($script_dir) === 'partials'){
            $project_root_path = dirname($script_dir);
        } else {
            $project_root_path = $script_dir;
        }
        $base_url_navbar_to_root = rtrim($protocol . $host . $project_root_path, '/');
    }
}

if (defined('APP_NAME')) {
    $app_name_navbar = APP_NAME;
}
// Ensure $base_url_navbar_to_root ends with a slash if it's not empty
if (!empty($base_url_navbar_to_root) && substr($base_url_navbar_to_root, -1) !== '/') {
    $base_url_navbar_to_root .= '/';
}
if (empty($base_url_navbar_to_root) && defined('BASE_URL')) { // If BASE_URL is defined and absolute
    $base_url_navbar_to_root = BASE_URL;
} elseif (empty($base_url_navbar_to_root)) {
    $base_url_navbar_to_root = '/'; // Absolute fallback if all else fails
}


?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm px-3 py-3">
    <div class="container-fluid">
        <!-- App Name -->
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center" href="<?php echo $base_url_navbar_to_root; ?>index.php">
            <i class="fas fa-cubes me-3 fs-3 text-light"></i>
            <?php echo htmlspecialchars($app_name_navbar); ?>
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
                            echo htmlspecialchars($_SESSION['user']['username']);
                        } else { 
                            echo "User"; // Fallback if username somehow not set
                        }
                        ?>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <a class="dropdown-item fs-6" href="<?php echo $base_url_navbar_to_root; ?>profile.php">
                            <i class="fas fa-user me-2"></i> Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger fs-6" href="<?php echo $base_url_navbar_to_root; ?>auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link fs-5" href="<?php echo $base_url_navbar_to_root; ?>auth/login.php">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                     <a class="nav-link fs-5 ms-2 btn btn-primary btn-sm text-white" href="<?php echo $base_url_navbar_to_root; ?>auth/register.php">
                        <i class="fas fa-user-plus me-2"></i>Sign Up
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
