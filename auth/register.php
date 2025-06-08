<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php'; // For $pdo, BASE_URL, helper functions
// For auth/register.php, vendor autoload is one level up
require_once __DIR__ . '/../vendor/autoload.php';

$errors = [];
$success_message = $_SESSION['success_message'] ?? null;
if ($success_message) unset($_SESSION['success_message']);

// Helper function to generate a unique member number
function generateNewMemberNo(PDO $pdo): string {
    $prefix = 'PU-' . date('Ymd'); // PU for Public User or Platform User
    $is_unique = false;
    $member_no = '';
    $max_attempts = 10; // Prevent infinite loop
    $attempt = 0;

    while (!$is_unique && $attempt < $max_attempts) {
        $random_part = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $member_no = $prefix . '-' . $random_part;

        $stmt = $pdo->prepare("SELECT id FROM memberz WHERE member_no = :member_no");
        $stmt->execute(['member_no' => $member_no]);
        if (!$stmt->fetch()) {
            $is_unique = true;
        }
        $attempt++;
    }
    if (!$is_unique) {
        // Fallback if couldn't find unique after several attempts (highly unlikely)
        return $prefix . '-' . bin2hex(random_bytes(3));
    }
    return $member_no;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $errors['csrf'] = "CSRF token validation failed. Please try submitting the form again.";
    } else {
        // Retrieve and sanitize inputs (htmlspecialchars for display, raw for DB ops after validation)
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $terms_agreed = isset($_POST['terms_agreed']);

        // Server-side validation
        if (empty($full_name)) $errors['full_name'] = "Full name is required.";
        if (empty($email)) {
            $errors['email'] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format.";
        } else {
            // Check email uniqueness in users table
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = "This email address is already registered.";
            }
        }
        if (empty($phone)) $errors['phone'] = "Phone number is required."; // Basic check, can be enhanced
        if (empty($password)) {
            $errors['password'] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters long.";
        }
        if ($password !== $confirm_password) $errors['confirm_password'] = "Passwords do not match.";
        if (!$terms_agreed) $errors['terms_agreed'] = "You must agree to the terms and conditions.";

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Create memberz record
                $member_no = generateNewMemberNo($pdo);
                $stmt_member = $pdo->prepare(
                    "INSERT INTO memberz (member_no, full_name, email, phone, date_registered, is_system_user, user_id)
                     VALUES (:member_no, :full_name, :email, :phone, NOW(), 0, NULL)"
                );
                $stmt_member->execute([
                    'member_no' => $member_no,
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone
                ]);
                $member_id_db = $pdo->lastInsertId();

                // 2. Create users record
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]); // Or use hashPassword() from config
                $activation_token = bin2hex(random_bytes(32));
                $token_expires_at = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours

                // Username will be the email for simplicity, ensure it's unique if needed or handle collision
                // For now, assuming email is unique as per earlier check.
                $username = $email;
                // Check username uniqueness if it's different from email or if users can change email later
                // For this registration, email is the username and already checked for uniqueness.

                $stmt_user = $pdo->prepare(
                    "INSERT INTO users (member_id, username, password_hash, email, phone, is_active, activation_token, token_expires_at, created_at, updated_at)
                     VALUES (:member_id, :username, :password_hash, :email, :phone, 0, :activation_token, :token_expires_at, NOW(), NOW())"
                );
                $stmt_user->execute([
                    'member_id' => $member_id_db,
                    'username' => $username,
                    'password_hash' => $hashed_password,
                    'email' => $email,
                    'phone' => $phone,
                    'activation_token' => $activation_token,
                    'token_expires_at' => $token_expires_at
                ]);
                $user_id_db = $pdo->lastInsertId();

                // 3. Update memberz record with user_id
                $stmt_update_member = $pdo->prepare("UPDATE memberz SET is_system_user = 1, user_id = :user_id WHERE id = :member_id");
                $stmt_update_member->execute(['user_id' => $user_id_db, 'member_id' => $member_id_db]);

                $pdo->commit();

                // Send activation email
                $activation_link = rtrim(BASE_URL ?? '', '/') . '/auth/activate_account.php?token=' . $activation_token;
                $email_send_error_message = '';

                $mail = new PHPMailer(true);
                try {
                    //Server settings from config.php
                    $mail->SMTPDebug = SMTP::DEBUG_OFF; // Use SMTP::DEBUG_SERVER for debugging
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;

                    if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'tls') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    } elseif (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    }

                    $mail->Port       = SMTP_PORT;

                    //Recipients
                    $recipient_email = $email; // User's email from form
                    $recipient_name = $full_name; // User's full name from form
                    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                    $mail->addAddress($recipient_email, $recipient_name);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Activate Your ' . (APP_NAME ?? 'SACCO') . ' Account';

                    $email_body_html = "Hello " . htmlspecialchars($recipient_name) . ",<br><br>";
                    $email_body_html .= "Thank you for registering with " . (APP_NAME ?? 'Our Platform') . ".<br>";
                    $email_body_html .= "Please click the link below to activate your account:<br>";
                    $email_body_html .= "<a href='" . $activation_link . "'>" . $activation_link . "</a><br><br>";
                    $email_body_html .= "This link will expire in 24 hours.<br><br>";
                    $email_body_html .= "Regards,<br>The " . (APP_NAME ?? 'SACCO') . " Team";

                    $alt_email_body = "Hello " . htmlspecialchars($recipient_name) . ",\n\n";
                    $alt_email_body .= "Thank you for registering with " . (APP_NAME ?? 'Our Platform') . ".\n";
                    $alt_email_body .= "Please copy and paste the following link into your browser to activate your account:\n";
                    $alt_email_body .= $activation_link . "\n\n";
                    $alt_email_body .= "This link will expire in 24 hours.\n\n";
                    $alt_email_body .= "Regards,\nThe " . (APP_NAME ?? 'SACCO') . " Team";

                    $mail->Body = $email_body_html;
                    $mail->AltBody = $alt_email_body;

                    $mail->send();
                    $_SESSION['success_message'] = "Registration successful! An activation email has been sent to ".htmlspecialchars($email).". Please check your inbox (and spam folder).";
                } catch (Exception $e) {
                    error_log("PHPMailer Error in " . __FILE__ . " for " . $recipient_email . ": " . $mail->ErrorInfo . " (Details: " . $e->getMessage() . ")");
                    // Fallback: provide activation link in success message if email fails
                    $_SESSION['success_message'] = "Registration successful! We couldn't send an activation email (Error: " . htmlspecialchars($mail->ErrorInfo) . "). Please use this link to activate: " . $activation_link;
                }

                header('Location: login.php');
                exit();

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Registration PDOException: " . $e->getMessage());
                $errors['db'] = "Registration failed due to a database error. Please try again later.";
            } catch (Exception $e) { // Catch other exceptions like from random_bytes
                 if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Registration Exception: " . $e->getMessage());
                $errors['db'] = "An unexpected error occurred during registration. Please try again later.";
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
    <title>Register - <?php echo htmlspecialchars(APP_NAME ?? 'Our SACCO Platform'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px 0;}
        .register-container { max-width: 550px; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .form-control.is-invalid { border-color: #dc3545; }
        .invalid-feedback { display: block; } /* Ensure feedback is shown */
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="text-center mb-4">Create Your Account</h2>

        <?php if (isset($errors['db'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db']); ?></div>
        <?php endif; ?>
        <?php if ($success_message && empty($errors)): // Should not happen due to redirect, but as a fallback ?>
             <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (isset($errors['csrf'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errors['csrf']); ?></div>
        <?php endif; ?>


        <form action="register.php" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">

            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['full_name']); ?></div><?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div><?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone']); ?></div><?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div><?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div><?php endif; ?>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input <?php echo isset($errors['terms_agreed']) ? 'is-invalid' : ''; ?>" id="terms_agreed" name="terms_agreed" value="1" <?php echo (isset($_POST['terms_agreed']) ? 'checked' : ''); ?>>
                <label class="form-check-label" for="terms_agreed">I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a></label>
                <?php if (isset($errors['terms_agreed'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['terms_agreed']); ?></div><?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>

        <p class="text-center mt-3">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
