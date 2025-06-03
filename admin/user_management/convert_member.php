<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

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

    // 5. Notifications (Simulated)
    $activation_link = rtrim(BASE_URL ?? '', '/') . '/auth/activate_account.php?token=' . $activation_token;

    // TODO: Implement actual email sending.
    // For now, we can store it in a session variable for testing/display if needed, or log it.
    $email_message = "Dear " . htmlspecialchars($member['full_name']) . ",\n\nPlease click the following link to activate your account and set your password:\n" . $activation_link . "\n\nThis link will expire in 24 hours.\n\nRegards,\n" . ($settings['site_name'] ?? APP_NAME);
    error_log("Activation Email for " . $member['email'] . ": " . $email_message); // Log for now
    // mail($member['email'], "Activate Your Account - " . ($settings['site_name'] ?? APP_NAME), $email_message);

    // TODO: Implement actual SMS sending.
    $sms_message = "Activate your account for " . ($settings['site_name'] ?? APP_NAME) . " using this link: " . $activation_link;
    error_log("Activation SMS for " . $member['phone'] . ": " . $sms_message); // Log for now

    $_SESSION['success_message'] = "User account creation initiated for " . htmlspecialchars($member['full_name']) . " (Username: " . htmlspecialchars($username) . "). An activation link has been (notionally) sent to their email and phone. Activation link for testing: " . $activation_link;

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
