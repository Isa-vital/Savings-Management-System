<?php
// Config, Session
// Session is expected to be started by config.php
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }
require_once __DIR__ . '/../config.php'; // Provides $pdo, BASE_URL, APP_NAME, password_hash helper etc.

$page_title = "Reset Password";
$errors = [];
$success_message = '';
$token = filter_input(INPUT_GET, 'token', FILTER_DEFAULT); // basic get with no sanitization

$show_form = false;
$user_id_from_token = null; // To store user ID if token is initially valid

// Fallback for APP_NAME and BASE_URL if not defined in config.php (though they should be)
if (!defined('APP_NAME')) { define('APP_NAME', 'Savings App'); }
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name_parts = explode('/', $_SERVER['SCRIPT_NAME']);
    $base_path_segment = implode('/', array_slice($script_name_parts, 0, count($script_name_parts) - 2)) . '/';
    define('BASE_URL', $protocol . $host . $base_path_segment);
}


if (empty($token)) {
    $errors[] = "Invalid password reset link. No token provided.";
} else {
    try {
        // Validate token: exists, not expired, and belongs to an active user
        $stmt = $pdo->prepare("SELECT id, username, reset_token_expires_at, is_active FROM users WHERE reset_token = :token");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = "Invalid or expired password reset link. Please request a new one if needed.";
        } elseif (!$user['is_active']) {
            $errors[] = "This account is currently inactive. Password cannot be reset. Please contact support.";
        } elseif (strtotime($user['reset_token_expires_at']) < time()) {
            $errors[] = "Password reset link has expired. Please request a new one.";
        } else {
            // Token is valid and user is active
            $show_form = true;
            $user_id_from_token = $user['id']; // Store user_id for the POST request
        }
    } catch (PDOException $e) {
        error_log("PDOException (Reset Password Token Check): " . $e->getMessage());
        $errors[] = "An error occurred while validating your reset link. Please try again.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form only if token was initially valid (show_form was true)
    // And ensure that user_id_from_token was set from a valid GET request token
    if ($show_form && $user_id_from_token) {
        if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) { // generateToken()/validateToken() from config.php
            $errors[] = 'CSRF token validation failed. Please try again.';
        } else {
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            // Token from POST should match token from GET (which was validated)
            $posted_token = $_POST['token'] ?? '';

            if ($posted_token !== $token) {
                 $errors[] = "Token mismatch. Please use the link provided in your email.";
                 $show_form = false; // Don't show form again if this basic check fails
            } elseif (empty($password) || empty($confirm_password)) {
                $errors[] = 'Both password fields are required.';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            } elseif ($password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            } else {
                try {
                    // Hash the new password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]); // Or your hashPassword() helper from config

                    // Update password and clear reset token fields
                    // Crucially, re-check the token and its expiry AT THE TIME OF UPDATE.
                    $updateStmt = $pdo->prepare(
                        "UPDATE users SET password_hash = :password_hash, reset_token = NULL, reset_token_expires_at = NULL
                         WHERE id = :id AND reset_token = :token AND reset_token_expires_at > NOW() AND is_active = 1"
                    );

                    $updateResult = $updateStmt->execute([
                        'password_hash' => $hashed_password,
                        'id' => $user_id_from_token, // Use user_id obtained from initial valid token check
                        'token' => $token
                    ]);

                    if ($updateResult && $updateStmt->rowCount() > 0) {
                        $success_message = "Your password has been successfully reset! You can now log in with your new password.";
                        $_SESSION['success_message'] = $success_message;
                        header("Location: " . rtrim(BASE_URL, '/') . "/auth/login.php");
                        exit;
                    } else {
                        $errors[] = "Could not update your password. The reset link might have been used, expired, or the account status changed. Please try the 'Forgot Password' process again.";
                        $show_form = false; // Hide form as the token is now considered invalid or used
                    }
                } catch (PDOException $e) {
                    error_log("PDOException (Reset Password Update): " . $e->getMessage());
                    $errors[] = "A database error occurred while resetting your password. Please try again later.";
                }
            }
        }
    } else {
        // If POST but show_form was false (e.g. initial token invalid) or user_id_from_token not set
        $errors[] = "Invalid request. Please use the link from your email.";
        $show_form = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .form-container { width: 100%; max-width: 450px; padding: 20px; }
        .form-header { text-align: center; margin-bottom: 1.5rem; }
        .form-header h2 { font-weight: 600; color: #333; }
        .form-card { border: none; border-radius: 0.75rem; box-shadow: 0 4px 25px rgba(0,0,0,0.1); padding: 2rem; }
        .form-control { border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem; }
        .btn-primary { border-radius: 0.5rem; padding: 0.75rem 1.5rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
             <h2><?php echo htmlspecialchars(APP_NAME); ?></h2>
        </div>

        <div class="card form-card">
            <div class="card-body">
                <h4 class="card-title text-center mb-4"><?php echo htmlspecialchars($page_title); ?></h4>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $err): ?>
                            <p class="mb-0"><?php echo htmlspecialchars($err); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php
                // This success_message is set on this page only if update fails AFTER a successful GET,
                // but then redirects. So, it's mostly for the login page.
                // The logic below is for displaying the form or a success message *before* redirection.
                // For now, $success_message on this page usually means we are about to redirect.
                // The "Proceed to Login" button is for the case where errors occurred, then success, but redirect failed.
                // Or if we decide not to redirect immediately for some reason.
                // Given the current flow, if $success_message is set, a redirect should have happened.
                // This block is more of a fallback or for future adjustments.
                if ($success_message && !$show_form):
                ?>
                    <div class="alert alert-success">
                        <p class="mb-0"><?php echo htmlspecialchars($success_message); ?></p>
                        <p class="mt-2"><a href="login.php" class="btn btn-sm btn-outline-success">Proceed to Login</a></p>
                    </div>
                <?php endif; ?>

                <?php if ($show_form): ?>
                <p class="text-muted text-center">Please enter your new password below.</p>
                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>"> <!-- generateToken() from config.php -->
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>"> <!-- Resubmit token -->

                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" id="password" name="password" class="form-control <?php if(isset($errors['password']) || isset($errors['confirm_password'])) echo 'is-invalid'; ?>" required>
                         <?php if(isset($errors['password'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['password']); ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php if(isset($errors['confirm_password'])) echo 'is-invalid'; ?>" required>
                        <?php if(isset($errors['confirm_password'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['confirm_password']); ?></div><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                </form>
                <?php elseif (!$success_message && empty($errors) && !empty($token)):
                      // This state means token was provided, but initial validation failed (e.g. expired, not found)
                      // $errors array would have been populated by the initial GET check.
                      // This specific elseif might be redundant if $errors always catches these.
                ?>
                     <!-- Errors are already displayed above. No need for this specific message. -->
                <?php elseif (empty($token) && empty($errors)):
                      // Only if initial token is empty AND no errors were added (unlikely due to initial check)
                ?>
                    <div class="alert alert-info">Please use the link sent to your email to reset your password.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-footer mt-3 text-center">
            <p><a href="login.php">Back to Login</a></p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
