<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';
// Adjust path if your vendor directory is elsewhere relative to this script
// Assuming vendor/autoload.php is in the project root, and this script is two levels down (admin/user_management/)
require_once __DIR__ . '/../../vendor/autoload.php';


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
        $_SESSION['error_message'] = "Member does not have an email address, which is required to create a user account. Please update the member's details first.";
        header('Location: index.php'); // Or redirect to member edit page: ../../members/edit.php?id=$member_id
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
            break; // Unique username found
        }
        $username = $base_username . $counter;
        $counter++;
        if ($counter > 100) { // Safety break
            throw new Exception("Could not generate a unique username after 100 attempts.");
        }
    }

    // 2. Generate activation token
    $activation_token = bin2hex(random_bytes(32));
    $token_expires_at = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours

    // 3. Insert into users table
    $sql_insert_user = "INSERT INTO users (member_id, username, password_hash, email, phone, is_active, activation_token, token_expires_at, created_at, updated_at) 
                        VALUES (:member_id, :username, :password_hash, :email, :phone, 0, :activation_token, :token_expires_at, NOW(), NOW())"; // Changed NULL to :password_hash
    $stmt_insert_user = $pdo->prepare($sql_insert_user);
    $stmt_insert_user->execute([
        'member_id' => $member['id'],
        'username' => $username,
        'password_hash' => 'PENDING_ACTIVATION_NO_LOGIN', // New placeholder value
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

    // 5. Notifications (Actual Email Sending)
    $activation_link = rtrim(BASE_URL ?? '', '/') . '/auth/activate_account.php?token=' . $activation_token;
    $email_send_error_message = ''; // To store potential email error

    $mail = new PHPMailer(true);
    try {
        //Server settings from config.php
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Set to SMTP::DEBUG_SERVER for detailed debugging output
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
        // If SMTP_ENCRYPTION is false or not 'tls'/'ssl', SMTPSecure remains PHPMailer's default (usually none)
        // For explicit 'false' or empty, PHPMailer might still attempt opportunistic TLS if server supports it.
        // To truly disable, one might need $mail->SMTPAutoTLS = false; if $mail->SMTPSecure is not set.
        // The provided block implies if not 'tls' or 'ssl', it's effectively 'none' or relies on PHPMailer defaults.

        $mail->Port       = SMTP_PORT;

        //Recipients
        $recipient_email = $member['email'];
        $recipient_name = $member['full_name'];
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($recipient_email, $recipient_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Activate Your ' . (APP_NAME ?? 'SACCO') . ' Account';
        
        $email_body = "Hello " . htmlspecialchars($recipient_name) . ",<br><br>";
        $email_body .= "An account has been created for you on " . (APP_NAME ?? 'Our Platform') . ".<br>";
        $email_body .= "Please click the link below to activate your account and set your password:<br>";
        $email_body .= "<a href='" . $activation_link . "'>" . $activation_link . "</a><br><br>";
        $email_body .= "This link will expire in 24 hours.<br><br>";
        $email_body .= "If you did not request this, please ignore this email.<br><br>";
        $email_body .= "Regards,<br>The " . (APP_NAME ?? 'SACCO') . " Team";
        
        $alt_email_body = "Hello " . htmlspecialchars($recipient_name) . ",\n\n";
        $alt_email_body .= "An account has been created for you on " . (APP_NAME ?? 'Our Platform') . ".\n";
        $alt_email_body .= "Please copy and paste the following link into your browser to activate your account and set your password:\n";
        $alt_email_body .= $activation_link . "\n\n";
        $alt_email_body .= "This link will expire in 24 hours.\n\n";
        $alt_email_body .= "If you did not request this, please ignore this email.\n\n";
        $alt_email_body .= "Regards,\nThe " . (APP_NAME ?? 'SACCO') . " Team";

        $mail->Body = $email_body;
        $mail->AltBody = $alt_email_body;

        $mail->send();
        $_SESSION['success_message'] = "User account for " . htmlspecialchars($recipient_name) . " (Username: " . htmlspecialchars($username) . ") created. Activation email sent successfully.";
    } catch (Exception $e) {
        error_log("PHPMailer Error in " . __FILE__ . " for " . $recipient_email . ": " . $mail->ErrorInfo . " (Details: " . $e->getMessage() . ")");
        // Set a generic error message for the user, but log the detailed one.
        $email_send_error_message = ' User account created, but could not send activation email. Please contact support or use the link below for activation.';
        $_SESSION['success_message'] = "User account for " . htmlspecialchars($recipient_name) . " (Username: " . htmlspecialchars($username) . ") created." . $email_send_error_message . " Activation link for testing: " . $activation_link;
    }
    
    // TODO: Implement actual SMS sending (if applicable).
    // $sms_message = "Activate your account for " . (APP_NAME ?? 'SACCO') . " using this link: " . $activation_link;
    // error_log("Activation SMS for " . $member['phone'] . ": " . $sms_message); // Log for now


} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Database error during user conversion: " . $e->getMessage();
    error_log("User Conversion PDOException for member_id " . $member_id . ": " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Error during user conversion: " . $e->getMessage();
    error_log("User Conversion Exception for member_id " . $member_id . ": " . $e->getMessage());
}

header('Location: index.php');
exit();
?>
