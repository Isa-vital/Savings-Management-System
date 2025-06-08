<?php
// Config, Session, and PHPMailer
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php'; // Provides $pdo, BASE_URL, APP_NAME, SMTP settings etc.
// For auth/forgot_password.php, vendor autoload is one level up
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$page_title = "Forgot Password";
$errors = [];
$success_message = '';

// Fallback for APP_NAME and BASE_URL if not defined in config.php (though they should be)
if (!defined('APP_NAME')) { define('APP_NAME', 'Savings App'); }
if (!defined('BASE_URL')) {
    // Basic fallback for BASE_URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name_parts = explode('/', $_SERVER['SCRIPT_NAME']);
    // Assumes script is in 'auth' directory, so go one level up for base.
    $base_path_segment = implode('/', array_slice($script_name_parts, 0, count($script_name_parts) - 2)) . '/';
    define('BASE_URL', $protocol . $host . $base_path_segment);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) { // generateToken() and validateToken() assumed in config.php
        $errors[] = 'CSRF token validation failed. Please try again.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL); // Sanitize first

        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Then validate
            $errors[] = 'Invalid email format.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, email, username, is_active FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Always show a generic success message to prevent user enumeration,
                // but only proceed with token generation and email if user is found and active.
                $success_message = "If an account with that email exists and is active, a password reset link has been sent.";

                if ($user && $user['is_active']) {
                    // Generate a unique reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $reset_token_expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

                    $updateStmt = $pdo->prepare("UPDATE users SET reset_token = :reset_token, reset_token_expires_at = :expires WHERE id = :id");
                    $updateStmt->execute([
                        'reset_token' => $reset_token,
                        'expires' => $reset_token_expires_at,
                        'id' => $user['id']
                    ]);

                    if ($updateStmt->rowCount() > 0) {
                        $reset_link = rtrim(BASE_URL, '/') . '/auth/reset_password.php?token=' . $reset_token;

                        // Send email using PHPMailer
                        $mail = new PHPMailer(true);
                        try {
                            $mail->SMTPDebug = SMTP::DEBUG_OFF; // Change to SMTP::DEBUG_SERVER for testing
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
                            $mail->addAddress($user['email'], $user['username']);

                            $mail->isHTML(true);
                            $mail->Subject = 'Password Reset Request - ' . APP_NAME;
                            $mail->Body    = "Hello " . htmlspecialchars($user['username']) . ",<br><br>" .
                                             "You requested a password reset for your account on " . APP_NAME . ".<br>" .
                                             "Please click the link below to set a new password (this link is valid for 1 hour):<br>" .
                                             "<a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>" .
                                             "If you did not request a password reset, please ignore this email.<br><br>" .
                                             "Regards,<br>The " . APP_NAME . " Team";
                            $mail->AltBody = "Hello " . htmlspecialchars($user['username']) . ",\n\n" .
                                             "You requested a password reset for your account on " . APP_NAME . ".\n" .
                                             "Please copy and paste the following link into your browser to set a new password (this link is valid for 1 hour):\n" .
                                             $reset_link . "\n\n" .
                                             "If you did not request a password reset, please ignore this email.\n\n" .
                                             "Regards,\nThe " . APP_NAME . " Team";

                            $mail->send();
                            // Success message is already set

                        } catch (Exception $e) {
                            error_log("PHPMailer Error (Forgot Password) for " . $email . ": " . $mail->ErrorInfo . " (Details: " . $e->getMessage() . ")");
                            // Don't change $success_message, user sees generic one. Failure is logged.
                        }
                    } else {
                        // This implies DB error during UPDATE, not that user wasn't found (that's handled by generic success)
                        $errors[] = "Could not prepare your account for password reset. Please try again or contact support.";
                        $success_message = ''; // Clear generic success message if a specific error occurs here
                    }
                }
                // If user not found ($user is false) or user is not active, we still show the generic success message.
                // No 'else' block needed here for that case.
            } catch (PDOException $e) {
                error_log("PDOException (Forgot Password) for " . $email . ": " . $e->getMessage());
                $errors[] = "A database error occurred. Please try again later.";
                $success_message = ''; // Clear generic success message
            }
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .form-container { width: 100%; max-width: 450px; padding: 20px; }
        .form-header { text-align: center; margin-bottom: 1.5rem; }
        .form-header h2 { font-weight: 600; color: #333; }
        .form-header a { text-decoration: none; color: #555; font-size: 0.9rem; }
        .form-header a:hover { color: #007bff; }
        .form-card { border: none; border-radius: 0.75rem; box-shadow: 0 4px 25px rgba(0,0,0,0.1); padding: 2rem; }
        .form-control { border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem; }
        .btn-primary { border-radius: 0.5rem; padding: 0.75rem 1.5rem; font-weight: 600; }
        .form-footer { text-align: center; margin-top: 1.5rem; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <a href="<?php echo rtrim(BASE_URL, '/') . '/landing.php'; ?>" title="Back to Homepage">
                <h2><?php echo htmlspecialchars(APP_NAME); ?></h2>
            </a>
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

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <p class="mb-0"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!$success_message): // Hide form if success message is shown ?>
                <form method="POST" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>"> <!-- generateToken() from config.php -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Enter your account email address:</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Password Reset Link</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-footer">
            <p><a href="login.php">Back to Login</a></p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
