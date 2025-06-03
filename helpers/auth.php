<?php
// helpers/auth.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is logged in.
 * If not, sets an error message and redirects to the login page.
 */
function require_login() {
    // Use a more specific check, like for user ID.
    if (!isset($_SESSION['user']['id'])) {
        $_SESSION['error_message'] = "Please log in to access this page.";
        // Ensure the path is absolute from the web root, or correctly relative.
        // Assuming BASE_URL is defined in config.php and available, or use a known path.
        // For now, using a common absolute path.
        header('Location: /auth/login.php');
        exit();
    }
    // Optionally, update last activity timestamp here if needed
    // $_SESSION['user']['last_activity'] = time();
}

/**
 * Checks if the current user has at least one of the specified roles.
 *
 * @param string|array $target_roles A single role name or an array of role names.
 * @return bool True if the user has at least one of the target roles, false otherwise.
 */
function has_role($target_roles): bool {
    if (!isset($_SESSION['user']['roles']) || !is_array($_SESSION['user']['roles'])) {
        return false; // User not logged in or roles not set
    }

    $user_roles = $_SESSION['user']['roles'];
    if (is_string($target_roles)) {
        $target_roles = [$target_roles]; // Convert single role string to array
    }

    foreach ($target_roles as $target_role) {
        if (in_array($target_role, $user_roles)) {
            return true;
        }
    }
    return false;
}

/**
 * Checks if the current user belongs to at least one of the specified groups.
 *
 * @param string|array $target_groups A single group name or an array of group names.
 * @return bool True if the user is in at least one of the target groups, false otherwise.
 */
function is_in_group($target_groups): bool {
    if (!isset($_SESSION['user']['groups']) || !is_array($_SESSION['user']['groups'])) {
        return false; // User not logged in or groups not set
    }

    $user_groups = $_SESSION['user']['groups'];
    if (is_string($target_groups)) {
        $target_groups = [$target_groups]; // Convert single group string to array
    }

    foreach ($target_groups as $target_group) {
        if (in_array($target_group, $user_groups)) {
            return true;
        }
    }
    return false;
}

/**
 * Checks if the user has 'Core Admin' or 'Administrator' role.
 * If not, sets an error message and redirects.
 */
function require_admin() {
    require_login(); // Ensure user is logged in first
    if (!has_role(['Core Admin', 'Administrator'])) {
        $_SESSION['error_message'] = "You do not have sufficient administrative privileges to access this page.";
        header('Location: /index.php'); // Or a dedicated 'access-denied.php' page
        exit();
    }
}

/**
 * Checks if the user has the 'Core Admin' role.
 * If not, sets an error message and redirects.
 */
function require_core_admin() {
    require_login();
    if (!has_role('Core Admin')) {
        $_SESSION['error_message'] = "This action requires Core Administrator privileges.";
        header('Location: /index.php');
        exit();
    }
}

/**
 * Checks if the user has the 'Administrator' role.
 */
function require_administrator() {
    require_login();
    // This allows 'Core Admin' as well. If only 'Administrator' is desired,
    // the check would be `has_role('Administrator') && !has_role('Core Admin')`.
    if (!has_role(['Administrator', 'Core Admin'])) {
        $_SESSION['error_message'] = "This action requires Administrator privileges.";
        header('Location: /index.php');
        exit();
    }
}

/**
 * Basic permission checking function.
 *
 * @param string $permission The permission string to check (e.g., 'manage_system_settings').
 * @return bool True if the user has the permission, false otherwise.
 */
function can(string $permission): bool {
    if (!isset($_SESSION['user']['id'])) {
        return false; // Not logged in
    }

    // Core Admin has all permissions by default in this basic setup
    if (has_role('Core Admin')) {
        return true;
    }

    // Specific permission checks for other roles
    switch ($permission) {
        case 'manage_system_settings':
            // Only Core Admin can manage system settings (already covered by the check above).
            return false;
        case 'manage_users':
            // Example: Administrator can manage users.
            return has_role('Administrator');
        case 'view_reports':
            // Example: Members and Administrators can view reports.
            return has_role(['Administrator', 'Member']);
        // Add more cases for other permissions as the system grows
        default:
            // By default, deny unknown permissions for non-Core Admins.
            return false;
    }
}

/**
 * Gets the current logged-in user's data.
 *
 * @return array|null The user data array from session, or null if not logged in.
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Checks if a user is logged in (simple check based on user ID).
 *
 * @return bool True if user session ID exists, false otherwise.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user']['id']);
}

?>