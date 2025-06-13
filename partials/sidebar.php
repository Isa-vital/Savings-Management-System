<?php
// Session is expected to be started by config.php, 
// which should be included by the parent script before this sidebar partial.
// if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Ensure APP_NAME is defined
if (!defined('APP_NAME')) {
    if (file_exists(__DIR__ . '/../config.php')) {
        @include_once __DIR__ . '/../config.php'; // Try to load from parent of partials
    }
    if (!defined('APP_NAME')) { // If still not defined after include attempt
        define('APP_NAME', 'Savings App'); // Absolute fallback
    }
}

// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    // Fallback for BASE_URL. This is a simplified version.
    // A robust version should ideally be in config.php and correctly set for the environment.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? ''; 
    $base_path_segment = '/'; // Default
    if (preg_match('/^(\/[^\/]+\/)/', $script_name, $matches)) {
         // If script_name starts with /somedir/ and is not in admin or auth, assume somedir is project root
        if (strpos($script_name, '/admin/') === false && strpos($script_name, '/auth/') === false && strpos($script_name, '/members/') === false ) {
             $base_path_segment = $matches[1];
        } else if (preg_match('/^(\/.*?\/)(admin|auth|members|savings|loans|partials)\//', $script_name, $sub_matches)) {
            // If in a known subdir, take the part before it as base.
            $base_path_segment = $sub_matches[1];
        } else {
             $base_path_segment = rtrim(dirname($script_name), '/\\');
             // Go up if in 'partials' or similar common subfolder for includes
             if(basename($base_path_segment) === 'partials') $base_path_segment = dirname($base_path_segment);
             $base_path_segment = rtrim($base_path_segment, '/\\') . '/';
        }
    }
    if ($base_path_segment === '//') $base_path_segment = '/';
    define('BASE_URL', $protocol . $host . rtrim($base_path_segment, '/') . '/');
}


// Ensure helpers/auth.php is loaded for has_role() and is_logged_in()
if (!function_exists('has_role') || !function_exists('is_logged_in')) {
    if(file_exists(__DIR__ . '/../helpers/auth.php')) {
        require_once __DIR__ . '/../helpers/auth.php';
    } elseif (file_exists(__DIR__ . '/helpers/auth.php')) { 
        require_once __DIR__ . '/helpers/auth.php';
    } else {
        // Define dummy functions if helpers are absolutely missing to prevent fatal errors in sidebar rendering
        if (!function_exists('is_logged_in')) { function is_logged_in_sidebar_fallback() { return isset($_SESSION['user']['id']); } }
        if (!function_exists('has_role')) { function has_role($roles) { 
            if (!isset($_SESSION['user']['roles']) || !is_array($_SESSION['user']['roles'])) return false;
            if (is_string($roles)) $roles = [$roles];
            foreach($roles as $role) { if(in_array($role, $_SESSION['user']['roles'])) return true;}
            return false;
        }}
    }
}
$is_logged_in_user = function_exists('is_logged_in') ? is_logged_in() : (function_exists('is_logged_in_sidebar_fallback') ? is_logged_in_sidebar_fallback() : false);
?>

<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">

            <?php if ($is_logged_in_user): ?>
                <?php // CORE ADMIN AND ADMINISTRATOR SHARED ITEMS (Main Dashboard) ?>
                <?php if (has_role(['Core Admin', 'Administrator'])): ?>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="<?php echo htmlspecialchars(BASE_URL . 'index.php'); ?>">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'overview.php'); ?>">
                             <i class="fas fa-chart-pie me-2"></i> Stat Overview 
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'members/memberslist.php'); ?>">
                             <i class="fas fa-users me-2"></i> All Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'savings/savingslist.php'); ?>">
                             <i class="fas fa-piggy-bank me-2"></i> All Savings
                        </a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'loans/loanslist.php'); ?>">
                             <i class="fas fa-hand-holding-usd me-2"></i> All Loans
                        </a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'reports.php'); ?>"> 
                            <i class="fas fa-chart-bar me-2"></i> Reports 
                        </a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'Calendar.php'); ?>">
                            <i class="fas fa-calendar-alt me-2"></i> Calendar
                        </a>
                    </li>
                    <!-- Removed transactions.php as it's not explicitly created -->
                <?php endif; ?>

                <?php // ADMINISTRATION DROPDOWN ?>
                <?php 
                $can_see_system_settings = has_role('Core Admin');
                $can_see_user_management = has_role(['Core Admin', 'Administrator']);
                $can_see_group_management = has_role('Core Admin');
                $can_see_loan_management = has_role(['Core Admin', 'Administrator']); // Added this

                if ($can_see_system_settings || $can_see_user_management || $can_see_group_management || $can_see_loan_management) : // Added $can_see_loan_management
                ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold d-flex justify-content-between align-items-center" href="#adminSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="adminSubmenu">
                            <span><i class="fas fa-shield-alt me-2"></i> Administration</span>
                            <i class="fas fa-chevron-down small"></i>
                        </a>
                        <div class="collapse ps-3" id="adminSubmenu">
                            <ul class="nav flex-column">
                                <?php if ($can_see_system_settings) : ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= htmlspecialchars(BASE_URL . 'admin/system_settings/index.php') ?>">
                                        <i class="fas fa-cogs me-2 text-secondary"></i> System Settings
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php if ($can_see_loan_management) : ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= htmlspecialchars(BASE_URL . 'admin/loans/loan_applications_list.php') ?>">
                                        <i class="fas fa-file-invoice-dollar me-2 text-secondary"></i> Loan Applications
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if ($can_see_user_management) : ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= htmlspecialchars(BASE_URL . 'admin/user_management/index.php') ?>">
                                        <i class="fas fa-users-cog me-2 text-secondary"></i> User Management
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if ($can_see_group_management) : ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= htmlspecialchars(BASE_URL . 'admin/group_management/index.php') ?>">
                                        <i class="fas fa-layer-group me-2 text-secondary"></i> Group Management
                                    </a>
                                </li>
                                <li class="nav-item"> 
                                    <a class="nav-link" href="<?= htmlspecialchars(BASE_URL . 'admin/group_management/assign_users.php') ?>">
                                        <i class="fas fa-user-tag me-2 text-secondary"></i> Assign User Roles
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </li>
                <?php endif; ?>


                <?php // MEMBER SPECIFIC ITEMS ?>
                <?php if (has_role('Member') && isset($_SESSION['user']['member_id']) && !empty($_SESSION['user']['member_id'])): ?>
                    <li class="nav-item mt-2"> 
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
                            <span>User Menu</span>
                        </h6>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'members/my_savings.php'); ?>">
                            <i class="fas fa-wallet me-2"></i> My Savings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'members/request_deposit.php'); ?>">
                            <i class="fas fa-donate me-2"></i> Request Deposit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'members/savings_performance.php'); ?>">
                            <i class="fas fa-chart-line me-2"></i> Savings Performance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'loans/apply_loan.php'); ?>">
                            <i class="fas fa-hand-holding-usd me-2"></i> Apply for Loan
                        </a>
                    </li>
                    <!--repay loan---->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'members/add_loan_repayment.php'); ?>">
                            <i class="fas fa-money-bill-wave me-2"></i> Repay Loan
                        </a>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'profile.php'); // Assuming general profile page ?>">
                            <i class="fas fa-user-edit me-2"></i> My Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'Calendar.php'); ?>">
                            <i class="fas fa-calendar-alt me-2"></i> Calendar
                        </a>
                    </li>
                    <!-- Add other member-specific links here, e.g., apply for loan, loan history -->
                <?php endif; ?>
                
                <?php // SHARED "MY PROFILE" FOR NON-MEMBER ADMINS ?>
                 <?php if (has_role(['Core Admin', 'Administrator']) && !(has_role('Member') && isset($_SESSION['user']['member_id']) && !empty($_SESSION['user']['member_id'])) ): ?>
                     <li class="nav-item mt-2"> <!-- Add some spacing if this is the only "personal" link for an admin -->
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
                            <span>User Account</span>
                        </h6>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'profile.php'); ?>">
                            <i class="fas fa-user-circle me-2"></i> My Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'auth/reset_password.php'); ?>">
                            <i class="fas fa-key me-2"></i> Change Password
                        </a>
                    </li>
                 <?php endif; ?>


            <?php else: // Not logged in - Show minimal links ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'landing.php'); ?>">
                        <i class="fas fa-home me-2"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'auth/login.php'); ?>">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'auth/register.php'); ?>">
                        <i class="fas fa-user-plus me-2"></i> Sign Up
                    </a>
                </li>
            <?php endif; ?>

        </ul>

        <?php if ($is_logged_in_user): ?>
        <hr> 
        <ul class="nav flex-column mb-2">
             <li class="nav-item">
                <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'auth/logout.php'); ?>">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo htmlspecialchars(BASE_URL . 'auth/reset_password.php'); ?>">
                    <i class="fas fa-key me-2"></i> Change Password
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</nav>