<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Load Composer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_admin(); // Core Admin or Administrator

if (!isset($_GET['member_id'])) {
    $_SESSION['error_message'] = "Member ID not provided.";
    header('Location: index.php');
    exit();
}

$member_id = filter_var($_GET['member_id'], FILTER_VALIDATE_INT);
if (!$member_id) {
    $_SESSION['error_message'] = "Invalid Member ID format.";
    header('Location: index.php');
    exit();
}

try {
    // Fetch member details
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, is_system_user, user_id FROM memberz WHERE id = :member_id");
    $stmt->execute(['member_id' => $member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['error_message'] = "Member not found.";
        header('Location: index.php');
        exit();
    }

    if ($member['is_system_user'] || !is_null($member['user_id'])) {
        $_SESSION['error_message'] = "This member is already a system user or linked to one.";
        header('Location: index.php');
        exit();
    }

    if (empty($member['email'])) {
        $_SESSION['error_message'] = "Member does not have an email address, which is required to create a user account.";
        header('Location: index.php');
        exit();
    }

    // Check if email already exists in users table
    $stmt_email_check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt_email_check->execute(['email' => $member['email']]);
    if ($stmt_email_check->fetch()) {
        $_SESSION['error_message'] = "An account with this email address (".$member['email'].") already exists in the users table.";
        header('Location: index.php');
        exit();
    }

    // User Creation Logic
    $pdo->beginTransaction();

    // 1. Generate a unique username
    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $member['full_name']));
    if (empty($base_username)) $base_username = 'user';
    $username = $base_username;
    $counter = 1;
    while (true) {
        $stmt_user = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt_user->execute(['username' => $username]);
        if (!$stmt_user->fetch()) {
            break;
        }
        $username = $base_username . $counter;
        $counter++;
        if ($counter > 100) {
            throw new Exception("Could not generate a unique username after 100 attempts.");
        }
    }

    // 2. Generate activation token
    $activation_token = bin2hex(random_bytes(32));
    $token_expires_at = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

    // 3. Insert into users table
    $sql_insert_user = "INSERT INTO users (member_id, username, password_hash, email, phone, is_active, activation_token, token_expires_at, created_at, updated_at)
                        VALUES (:member_id, :username, NULL, :email, :phone, 0, :activation_token, :token_expires_at, NOW(), NOW())";
    $stmt_insert_user = $pdo->prepare($sql_insert_user);
    $stmt_insert_user->execute([
        'member_id' => $member['id'],
        'username' => $username,
        'email' => $member['email'],
        'phone' => $member['phone'],
        'activation_token' => $activation_token,
        'token_expires_at' => $token_expires_at
    ]);
    $new_user_id = $pdo->lastInsertId();

    // 4. Update memberz table
    $sql_update_member = "UPDATE memberz SET is_system_user = 1, user_id = :user_id WHERE id = :member_id";
    $stmt_update_member = $pdo->prepare($sql_update_member);
    $stmt_update_member->execute(['user_id' => $new_user_id, 'member_id' => $member['id']]);

    $pdo->commit();

    // 5. Send activation email with PHPMailer
    $activation_link = rtrim(BASE_URL ?? '', '/') . '/auth/activate_account.php?token=' . $activation_token;
    
    $mail = new PHPMailer(true);
    try {
        // Server settings (configure these in your config.php)
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.mailtrap.io';
        $mail->SMTPAuth = true;
        $mail->Username = defined('SMTP_USER') ? SMTP_USER : 'your_mailtrap_username';
        $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : 'your_mailtrap_password';
        $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 2525;

        // Recipients
        $mail->setFrom('no-reply@yoursavingssystem.com', 'Savings System');
        $mail->addAddress($member['email'], $member['full_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Activate Your Account';
        $mail->Body = '
            <h2>Welcome to Our Savings System</h2>
            <p>Dear ' . htmlspecialchars($member['full_name']) . ',</p>
            <p>An account has been created for you. Please click the button below to activate your account and set your password:</p>
            <p><a href="' . $activation_link . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">Activate Account</a></p>
            <p>This link will expire in 24 hours.</p>
            <p>If you didn\'t request this, please ignore this email.</p>
        ';
        $mail->AltBody = "Dear " . $member['full_name'] . ",\n\nPlease click the following link to activate your account:\n" . $activation_link . "\n\nThis link will expire in 24 hours.";

        $mail->send();
        
        $_SESSION['success_message'] = "User account created for " . htmlspecialchars($member['full_name']) . " (Username: " . htmlspecialchars($username) . "). Activation email sent successfully!";
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        $_SESSION['success_message'] = "User account created but email could not be sent. Mailer Error: " . $mail->ErrorInfo;
        // Still show success because account was created, just email failed
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Database error during user conversion: " . $e->getMessage();
    error_log("User Conversion PDOException: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Error during user conversion: " . $e->getMessage();
    error_log("User Conversion Exception: " . $e->getMessage());
}

header('Location: index.php');
exit();