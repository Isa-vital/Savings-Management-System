<?php
// Ensure session is started (might be redundant if already started by including script)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Make sure helpers are available for role checks
// Assuming config.php (which defines BASE_URL) is loaded by the parent script including this sidebar.
// If not, it would need: require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/auth.php'; 

// Define BASE_URL if not already defined (fallback, ideally set in config.php)
if (!defined('BASE_URL')) {
    // This is a fallback. Adjust if your structure is different or BASE_URL is guaranteed by config.php
    // Example: Detect if running in 'savingssystem' subdirectory.
    $script_name_parts = explode('/', $_SERVER['SCRIPT_NAME']);
    $sub_dir_index = array_search('savingssystem', $script_name_parts);
    if ($sub_dir_index !== false) {
        $base_path_parts = array_slice($script_name_parts, 0, $sub_dir_index + 1);
        $calculated_base_url = implode('/', $base_path_parts) . '/';
    } else {
        $calculated_base_url = '/'; // Or some other sensible default
    }
    define('BASE_URL', $calculated_base_url);
}

?>
<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active fw-bold" href="<?php echo BASE_URL; ?>index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active fw-bold" href="<?php echo BASE_URL; ?>overview.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Stat Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>members/memberslist.php">
                    <i class="fas fa-users me-2"></i>Group Members
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>savings/savingslist.php">
                    <i class="fas fa-wallet me-2"></i>Manage Savings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>loans/loanslist.php">
                    <i class="fas fa-hand-holding-usd me-2"></i>Manage Loans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            
            <!-- Administration Section -->
            <?php if (is_logged_in()): // Show Administration heading only if logged in and has any admin rights ?>
                <?php if (has_role(['Core Admin', 'Administrator'])): ?>
                    <li class="nav-item nav-category">
                        <span class="nav-link disabled text-muted">Administration</span>
                    </li>
                <?php endif; ?>

                <?php if (has_role(['Core Admin', 'Administrator'])): ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>admin/user_management/index.php">
                            <i class="fas fa-users-cog me-2"></i>User Management
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (has_role('Core Admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>admin/group_management/index.php">
                            <i class="fas fa-layer-group me-2"></i>Group Management
                        </a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>admin/group_management/assign_users.php"> <!-- Assuming assign_users is part of group mgmt -->
                            <i class="fas fa-user-friends me-2"></i>Assign Users to Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>admin/system_settings/index.php">
                            <i class="fas fa-cogs me-2"></i>System Settings
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Member Area -->
            <?php if (is_logged_in() && isset($_SESSION['user']['member_id']) && !empty($_SESSION['user']['member_id'])): ?>
                <li class="nav-item nav-category">
                    <span class="nav-link disabled text-muted">Member Area</span>
                </li>
                <?php if (has_role('Member')): ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>members/my_savings.php">
                            <i class="fas fa-piggy-bank me-2"></i>My Savings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>members/savings_performance.php">
                            <i class="fas fa-chart-line me-2"></i>Savings Performance
                        </a>
                    </li>
                    <!-- Add more member-specific links here, e.g., My Loans, My Profile -->
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- General Settings Link (kept for now, review its purpose) -->
            <li class="nav-item">
                <a class="nav-link fw-bold" href="<?php echo BASE_URL; ?>profile.php"> <!-- Changed from index.php to profile.php as a more likely target -->
                    <i class="fas fa-user-cog me-2"></i>My Profile/Settings
                </a>
            </li>
        </ul>
    </div>
</div>