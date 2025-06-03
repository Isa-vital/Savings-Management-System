<?php
require_once __DIR__ . '/../config.php'; // For DB, BASE_URL, password_hash
// helpers/auth.php might start a session if not already started by config.php
require_once __DIR__ . '/../helpers/auth.php';

$token = $_GET['token'] ?? $_POST['token'] ?? null;
$page_title = "Activate Your Account";
$error_message = '';
$success_message = '';
$user = null;
$show_form = false;

if (empty($token)) {
    $error_message = "Invalid activation link: No token provided.";
} else {
    try {
        // Fetch user by token, ensuring not active and token not expired
        $stmt = $pdo->prepare("SELECT id, username, email, is_active, activation_token, token_expires_at
                               FROM users
                               WHERE activation_token = :token");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error_message = "Invalid or expired activation link. The token does not match any pending activation.";
        } elseif ($user['is_active'] == 1) {
            $error_message = "This account has already been activated. You can try logging in.";
            // Optionally, redirect to login page
            // header('Location: login.php'); exit;
        } elseif (new DateTime() > new DateTime($user['token_expires_at'])) {
            $error_message = "This activation link has expired. Please request a new one or contact support.";
        } else {
            // Token is valid, user is not active, token not expired
            $show_form = true;
        }

    } catch (PDOException $e) {
        error_log("Activation DB Error: " . $e->getMessage());
        $error_message = "A database error occurred. Please try again later.";
    } catch (Exception $e) { // Catch other exceptions like DateTime issues
        error_log("Activation System Error: " . $e->getMessage());
        $error_message = "A system error occurred. Please try again later.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form && $user) {
    // Re-verify token and user state on POST to be absolutely sure
    // This handles the case where the state might have changed between GET and POST
    // (e.g., activated in another tab, or token expired in the meantime)
    // For simplicity in this example, we rely on the $user object fetched initially,
    // assuming a short time window. A more robust check would re-query the user state here.

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error_message = "Please enter and confirm your password.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) { // Basic password strength check
        $error_message = "Password must be at least 8 characters long.";
    } else {
        try {
            $pdo->beginTransaction();

            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]); // Or your defined hashPassword function

            $update_stmt = $pdo->prepare("UPDATE users
                                          SET password_hash = :password_hash,
                                              is_active = 1,
                                              activation_token = NULL,
                                              token_expires_at = NULL,
                                              updated_at = NOW()
                                          WHERE id = :user_id
                                          AND activation_token = :token -- Ensure token still matches
                                          AND is_active = 0 -- Ensure still inactive
                                          AND token_expires_at > NOW() -- Ensure token not expired just now");

            $update_stmt->execute([
                'password_hash' => $hashed_password,
                'user_id' => $user['id'],
                'token' => $token
            ]);

            if ($update_stmt->rowCount() > 0) {
                $pdo->commit();
                $success_message = "Your account has been activated successfully! You can now log in.";
                $show_form = false; // Hide form on success
            } else {
                // This could happen if the account was activated or token expired between form load and submit
                $pdo->rollBack();
                $error_message = "Could not activate account. The activation link may have just expired or the account status changed. Please try again or contact support.";
                // Re-fetch user state to provide more specific error if needed
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Activation POST DB Error: " . $e->getMessage());
            $error_message = "A database error occurred while activating your account. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($settings['site_name'] ?? APP_NAME ?? 'Sacco App'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .activation-container { max-width: 450px; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="activation-container">
        <h2 class="text-center mb-4"><?php echo htmlspecialchars($page_title); ?></h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
                <p class="mt-2"><a href="login.php" class="btn btn-primary">Go to Login</a></p>
            </div>
        <?php endif; ?>

        <?php if ($error_message && !$success_message): // Show error only if no success ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
                 <?php if (str_contains($error_message, "expired") || str_contains($error_message, "Invalid")): ?>
                    <p class="mt-2"><a href="login.php" class="btn btn-secondary">Back to Login</a></p>
                 <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_form && !$success_message): ?>
            <p>Welcome, <?php echo htmlspecialchars($user['username']); ?>! Please set your password to activate your account.</p>
            <form action="activate_account.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Activate Account</button>
            </form>
        <?php endif; ?>

        <?php if (!$show_form && !$success_message && !$error_message):
            // This case should ideally not be reached if logic is correct,
            // but as a fallback if token was invalid from the start and no error was set.
        ?>
            <div class="alert alert-warning">No valid token found or form cannot be displayed.</div>
            <p class="mt-2 text-center"><a href="login.php" class="btn btn-secondary">Back to Login</a></p>
        <?php endif; ?>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
