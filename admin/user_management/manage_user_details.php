<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includes - config.php should be first to define BASE_URL etc.
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';
// Assuming PHPMailer might be used for notifications from this page later (e.g. password reset trigger)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


// Access Control: Only Admins and Core Admins
require_admin(); // This checks for 'Core Admin' or 'Administrator' roles

$page_title = "Manage User Details";
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

// Fallback for APP_NAME and BASE_URL if not defined in config.php (though they should be)
if (!defined('APP_NAME')) { define('APP_NAME', 'Savings App'); }
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Basic path detection, assumes script is in admin/user_management/
    $script_name_parts = explode('/', $_SERVER['SCRIPT_NAME']);
    $base_path_segment = implode('/', array_slice($script_name_parts, 0, count($script_name_parts) - 3)) . '/';
    define('BASE_URL', $protocol . $host . $base_path_segment);
}


$user_details = null;
$member_details = null;
// Initialize $errors as an array
$errors = [];
// Retrieve session messages and then clear them
if(isset($_SESSION['errors'])) {
    $errors = array_merge($errors, $_SESSION['errors']); // Merge with other potential errors on page
    unset($_SESSION['errors']);
}
$success_message = $_SESSION['success_message'] ?? '';
if(isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}


if (!$user_id || $user_id <= 0) { // This check is for the GET user_id
    // If POST request, user_id should come from form or be same as GET
    // This initial check is fine for loading the page.
    $_SESSION['error_message'] = "Invalid user ID specified.";
    header("Location: " . BASE_URL . "admin/user_management/index.php");
    exit;
}

try {
    // Fetch user details
    $stmt_user = $pdo->prepare("SELECT u.id, u.username, u.email, u.phone, u.is_active, u.member_id, u.created_at
                                FROM users u
                                WHERE u.id = :user_id");
    $stmt_user->execute(['user_id' => $user_id]);
    $user_details = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_details) {
        $_SESSION['error_message'] = "User not found with ID: " . htmlspecialchars($user_id);
        header("Location: " . BASE_URL . "admin/user_management/index.php");
        exit;
    }

    // Fetch linked member details if member_id exists
    if (!empty($user_details['member_id'])) {
        $stmt_member = $pdo->prepare("SELECT m.member_no, m.full_name, m.email AS member_email, m.phone AS member_phone, m.occupation, m.district
                                      FROM memberz m
                                      WHERE m.id = :member_id");
        $stmt_member->execute(['member_id' => $user_details['member_id']]);
        $member_details = $stmt_member->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error fetching user/member details for user_id {$user_id}: " . $e->getMessage());
    // Add to $errors array to display on the page itself if possible
    $errors[] = "Database error occurred while fetching initial details. Please try again or check logs.";
    // $user_details might be null here, page will show error.
}


// POST Request Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure $user_id from GET is still the context for POST operations
    // And that initial $user_details were loaded (otherwise critical checks might fail)
    if (!$user_id || !$user_details) {
        $errors[] = "Cannot process form: User context is missing or invalid.";
    } elseif (isset($_POST['action'])) {
        if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
            $errors[] = 'CSRF token validation failed. Please try submitting the form again.';
        } else {
            if ($_POST['action'] === 'update_details') {
                $new_username = trim($_POST['username'] ?? '');
                $new_email = trim($_POST['email'] ?? '');
                $new_phone = trim($_POST['phone'] ?? '');
                // $current_user_id is $user_id (from GET, validated)

                // Validation
                if (empty($new_username)) {
                    $errors[] = "Username cannot be empty.";
                }
                if (empty($new_email)) {
                    $errors[] = "Email cannot be empty.";
                } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email format.";
                }
                // Phone validation can be added (e.g., length, numeric)

                // Check for username uniqueness (if changed)
                if ($new_username !== $user_details['username']) {
                    $stmt_check_username = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
                    $stmt_check_username->execute(['username' => $new_username, 'user_id' => $user_id]);
                    if ($stmt_check_username->fetch()) {
                        $errors[] = "Username '" . htmlspecialchars($new_username) . "' already taken. Please choose another.";
                    }
                }

                // Check for email uniqueness (if changed)
                if ($new_email !== $user_details['email']) {
                    $stmt_check_email = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
                    $stmt_check_email->execute(['email' => $new_email, 'user_id' => $user_id]);
                    if ($stmt_check_email->fetch()) {
                        $errors[] = "Email address '" . htmlspecialchars($new_email) . "' already in use by another account.";
                    }
                }

                if (empty($errors)) {
                    try {
                        $update_stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, phone = :phone, updated_at = NOW() WHERE id = :user_id");
                        $update_stmt->execute([
                            'username' => $new_username,
                            'email' => $new_email,
                            'phone' => $new_phone,
                            'user_id' => $user_id
                        ]);
                        $_SESSION['success_message'] = "User details updated successfully.";
                        // Refresh data after update by redirecting
                        header("Location: " . BASE_URL . "admin/user_management/manage_user_details.php?user_id=" . $user_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log("Error updating user details for user_id {$user_id}: " . $e->getMessage());
                        $errors[] = "Database error occurred while updating details. Please try again.";
                    }
                }
                // If errors occurred, the script will continue and display them.
                // The form fields below will use $_POST values if set, falling back to $user_details,
                // so submitted (but erroneous) data can be shown.
            } elseif ($_POST['action'] === 'toggle_status') {
                // $current_user_id is $user_id from GET
                // $user_details should be populated from the GET request prior to POST handling
                if (!$user_details) { // Should already be caught by the general check at POST start
                     $errors[] = "User details not available for status toggle.";
                } else {
                    // No need to re-fetch status, use $user_details['is_active'] from initial page load
                    $current_status = $user_details['is_active'];
                    $new_status = $current_status ? 0 : 1; // Toggle the status
                    $new_status_text = $new_status ? "activated" : "deactivated";

                    try {
                        $update_stmt = $pdo->prepare("UPDATE users SET is_active = :is_active, updated_at = NOW() WHERE id = :user_id");
                        $update_stmt->execute([
                            'is_active' => $new_status,
                            'user_id' => $user_id
                        ]);
                        $_SESSION['success_message'] = "User account has been successfully " . $new_status_text . ".";
                        // Refresh data after update by redirecting
                        header("Location: " . BASE_URL . "admin/user_management/manage_user_details.php?user_id=" . $user_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log("Error toggling user status for user_id {$user_id}: " . $e->getMessage());
                        $errors[] = "Database error occurred while changing user status. Please try again.";
                    }
                }
            } elseif ($_POST['action'] === 'send_reset_link') {
                // $current_user_id is $user_id from GET
                // $user_details should be available from the GET request processing part
                if (!$user_details || $user_details['id'] != $user_id) { // $user_id is from GET param
                     $errors[] = "User details not found or mismatch. Cannot send reset link.";
                } elseif (!$user_details['is_active']) {
                    $errors[] = "Cannot send password reset link to an inactive user. Please activate the account first.";
                } else {
                    try {
                        $reset_token = bin2hex(random_bytes(32));
                        $reset_token_expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

                        $updateStmt = $pdo->prepare("UPDATE users SET reset_token = :reset_token, reset_token_expires_at = :expires, updated_at = NOW() WHERE id = :id");
                        $updateStmt->execute([
                            'reset_token' => $reset_token,
                            'expires' => $reset_token_expires_at,
                            'id' => $user_id
                        ]);

                        if ($updateStmt->rowCount() > 0) {
                            $reset_link = rtrim(BASE_URL, '/') . '/auth/reset_password.php?token=' . $reset_token;

                            $mail = new PHPMailer(true);
                            try {
                                $mail->SMTPDebug = SMTP::DEBUG_OFF; // Or SMTP::DEBUG_SERVER for testing
                                $mail->isSMTP();
                                $mail->Host       = SMTP_HOST;
                                $mail->SMTPAuth   = true;
                                $mail->Username   = SMTP_USERNAME;
                                $mail->Password   = SMTP_PASSWORD;
                                if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'tls') {
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                } elseif (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') {
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                } else {
                                    $mail->SMTPSecure = false;
                                }
                                $mail->Port       = SMTP_PORT;

                                $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                                $mail->addAddress($user_details['email'], $user_details['username']);

                                $mail->isHTML(true);
                                $mail->Subject = 'Password Reset Initiated by Administrator - ' . APP_NAME;
                                $mail->Body    = "Hello " . htmlspecialchars($user_details['username']) . ",<br><br>" .
                                                 "An administrator has initiated a password reset for your account on " . APP_NAME . ".<br>" .
                                                 "Please click the link below to set a new password (this link is valid for 1 hour):<br>" .
                                                 "<a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>" .
                                                 "If you did not expect this, please contact support or an administrator.<br><br>" .
                                                 "Regards,<br>The " . APP_NAME . " Team";
                                $mail->AltBody = "Hello " . htmlspecialchars($user_details['username']) . ",\n\n" .
                                                 "An administrator has initiated a password reset for your account on " . APP_NAME . ".\n" .
                                                 "Please copy and paste the following link into your browser to set a new password (this link is valid for 1 hour):\n" .
                                                 $reset_link . "\n\n" .
                                                 "If you did not expect this, please contact support or an administrator.\n\n" .
                                                 "Regards,\nThe " . APP_NAME . " Team";

                                $mail->send();
                                $_SESSION['success_message'] = "Password reset link successfully sent to " . htmlspecialchars($user_details['email']) . ".";
                            } catch (Exception $e) { // PHPMailer Exception
                                error_log("PHPMailer Error (Admin Send Reset Link for user " . $user_id . "): " . $mail->ErrorInfo . " (Details: " . $e->getMessage() . ")");
                                $_SESSION['error_message'] = 'User record updated for password reset, but could not send email. Please check system email settings or contact support. The user can also try the "Forgot Password" link themselves.';
                            }
                        } else {
                            $errors[] = "Could not prepare user account for password reset (DB update failed). Please try again.";
                        }
                    } catch (PDOException $e) {
                        error_log("Error sending reset link (admin PDO): " . $e->getMessage());
                        $errors[] = "Database error occurred. Please try again.";
                    } catch (Exception $e) { // Catch other general exceptions like from random_bytes
                        error_log("Error sending reset link (admin General): " . $e->getMessage());
                        $errors[] = "An unexpected error occurred. Please try again.";
                    }
                }
                // Redirect back to the details page if no new errors were added by this specific action.
                // If $_SESSION['error_message'] was set by PHPMailer, it's a kind of "soft" error,
                // the primary action (token generation) succeeded. So we redirect.
                // If $errors array got new entries, it means primary action failed, so don't redirect.
                if (empty($errors) || isset($_SESSION['error_message'])) {
                     header("Location: " . BASE_URL . "admin/user_management/manage_user_details.php?user_id=" . $user_id);
                     exit;
                }
            }
            // ... other actions ...
        }
    } else {
         // This case means POST but no action, or user_id/user_details context lost.
         $errors[] = "Could not process request: Form action or user context missing.";
    }
}
// End of POST handler. If it was a POST and had errors, $errors is populated.
// If it was a POST and successful, it redirected.
// If it's a GET, or POST with errors, script continues to render HTML.
// $user_details should still hold original data for GET, or for POST if update failed before re-fetch.
// For POST with validation errors, form fields below use $_POST values.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .detail-label { font-weight: bold; }
        .card-header-actions .btn, .card-header-actions .nav-link { margin-left: 0.5rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($user_details['username'] ?? 'N/A'); ?></h1>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $err): echo '<p class="mb-0">' . htmlspecialchars($err) . '</p>'; endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <?php if ($user_details && empty($errors) ): // Only display if user found and no critical DB error during fetch that would make $user_details null ?>
                <div class="row">
                    <!-- User Account Details Card -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                 <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit User Account</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="manage_user_details.php?user_id=<?php echo htmlspecialchars($user_details['id']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                                    <input type="hidden" name="action" value="update_details">

                                    <div class="mb-3">
                                        <label for="username" class="form-label detail-label">Username:</label>
                                        <input type="text" class="form-control <?php if(isset($errors['username_update'])) echo 'is-invalid'; ?>" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $user_details['username']); ?>" required>
                                        <?php if(isset($errors['username_update'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['username_update']); ?></div><?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label detail-label">Email:</label>
                                        <input type="email" class="form-control <?php if(isset($errors['email_update'])) echo 'is-invalid'; ?>" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $user_details['email']); ?>" required>
                                        <?php if(isset($errors['email_update'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['email_update']); ?></div><?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label for="phone" class="form-label detail-label">Phone:</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ($user_details['phone'] ?? '')); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <p class="mb-1"><span class="detail-label">Status:</span>
                                            <?php if ($user_details['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                            <!-- Status toggle button will be added in next step -->
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <p><span class="detail-label">User Since:</span> <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($user_details['created_at']))); ?></p>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Member Profile Details Card (if applicable) -->
                    <?php if ($member_details): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-address-card me-2"></i>Linked Member Profile</h5>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4 detail-label">Member No:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($member_details['member_no']); ?></dd>

                                    <dt class="col-sm-4 detail-label">Full Name:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($member_details['full_name']); ?></dd>

                                    <dt class="col-sm-4 detail-label">Member Email:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($member_details['member_email'] ?? 'N/A'); ?></dd>

                                    <dt class="col-sm-4 detail-label">Member Phone:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($member_details['member_phone'] ?? 'N/A'); ?></dd>

                                    <dt class="col-sm-4 detail-label">Occupation:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($member_details['occupation'] ?? 'N/A'); ?></dd>

                                    <dt class="col-sm-4 detail-label">District:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($member_details['district'] ?? 'N/A'); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($user_details['member_id']): // User has a member_id but details couldn't be fetched ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                             <div class="card-header"><h5 class="mb-0">Linked Member Profile</h5></div>
                            <div class="card-body"><div class="alert alert-warning">Member details associated with member ID <?php echo htmlspecialchars($user_details['member_id']); ?> could not be found.</div></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions Card (will be populated in later steps) -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>User Actions</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Role & Group Assignments</h6>
                        <?php if (has_role('Core Admin')) : // Only Core Admins can access group/role assignments ?>
                            <a href="<?php echo htmlspecialchars(BASE_URL . 'admin/group_management/assign_users.php?user_id=' . urlencode($user_details['id'])); ?>" class="btn btn-outline-info btn-sm mb-2">
                                <i class="fas fa-user-tag me-2"></i>Manage Roles & Groups
                            </a>
                            <small class="form-text text-muted d-block mt-1 mb-3">
                                Assign this user to groups and define their roles within those groups.
                            </small>
                        <?php else: ?>
                            <p class="text-muted mb-3"><em>Role and group assignments are managed by Core Administrators.</em></p>
                        <?php endif; ?>

                        <hr>
                        <h6 class="card-subtitle mb-2 text-muted">Account Status</h6>
                        <form method="POST" action="manage_user_details.php?user_id=<?php echo htmlspecialchars($user_details['id']); ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <?php if ($user_details['is_active']): ?>
                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to deactivate this user? They will not be able to log in.');">
                                    <i class="fas fa-user-times me-2"></i>Deactivate User
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to activate this user? They will be able to log in.');">
                                    <i class="fas fa-user-check me-2"></i>Activate User
                                </button>
                            <?php endif; ?>
                        </form>

                        <hr>
                        <h6 class="card-subtitle mb-2 text-muted">Password Management</h6>
                        <form method="POST" action="manage_user_details.php?user_id=<?php echo htmlspecialchars($user_details['id']); ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                            <input type="hidden" name="action" value="send_reset_link">
                            <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Are you sure you want to send a password reset link to this user?');">
                                <i class="fas fa-key me-2"></i>Send Password Reset Link
                            </button>
                        </form>
                        <small class="form-text text-muted d-block mt-1">
                            This will send an email to '<?php echo htmlspecialchars($user_details['email']); ?>' with instructions to reset their password.
                        </small>
                    </div>
                </div>

                <?php elseif (empty($errors)): // If $user_details is null and no $errors were set by PDOException (e.g. bad ID redirect already happened) ?>
                    <div class="alert alert-warning">User data could not be loaded. The user may have been deleted or the ID is incorrect. This message appears if initial checks passed but data is still unavailable.</div>
                <?php endif; ?>
                 <div class="mt-3">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/admin/user_management/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to User List</a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
