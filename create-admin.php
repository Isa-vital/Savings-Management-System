<?php

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/config.php'; // Provides $pdo, APP_NAME, helper functions

// --- Predefined User Details ---
$fullName = "System Admin";
$email = "isaacmukonyezi2.0@gmail.com";
$phone = "0754902000"; // Store as string
$username = "CoreAdmin";
$plainPassword = "Today123"; 

echo "Attempting to create Core Admin user...\n";

try {
    // --- Password Hashing ---
    $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    if ($passwordHash === false) {
        die("Error: Failed to hash password.\n");
    }

    // --- Begin Transaction ---
    $pdo->beginTransaction();

    // --- Check for Existing User ---
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $stmtCheck->execute(['username' => $username, 'email' => $email]);
    if ($stmtCheck->fetch()) {
        $pdo->rollBack(); // No changes made, but good practice
        echo "User '{$username}' or email '{$email}' already exists. Aborting.\n";
        exit;
    }

    // --- Ensure 'Core Admins' Group (ID 1) and 'Core Admin' Role (ID 1) exist ---
    // Role 'Core Admin' with ID 1 is assumed to be created by migration 0_setup_new_tables.php
    // Group 'Core Admins' with ID 1 needs to be ensured or created.
    $stmtGroup = $pdo->prepare(
        "INSERT IGNORE INTO groups (id, group_name, description) 
         VALUES (1, 'Core Admins', 'Group for Core System Administrators')"
    );
    $stmtGroup->execute();
    // We assume Role ID 1 ('Core Admin') exists from migrations. If not, this script would need to create it or fail.


    // --- Insert into `memberz` ---
    $memberNo = 'CORE-ADMIN-01'; // Could be made more dynamic if script were run multiple times for different core admins
    // Check if memberNo already exists to avoid issues if script is re-run after partial failure
    $stmtCheckMemberNo = $pdo->prepare("SELECT id FROM memberz WHERE member_no = :member_no");
    $stmtCheckMemberNo->execute(['member_no' => $memberNo]);
    if ($stmtCheckMemberNo->fetch()) {
        // If CORE-ADMIN-01 member exists but user doesn't, it implies a previous partial run.
        // Decide on strategy: error out, or try to use existing member record.
        // For simplicity, error out if member_no is taken and user doesn't exist.
        // This situation is less likely if the initial user check passes.
        echo "Member number '{$memberNo}' already exists, but user was not found. Possible inconsistent state. Aborting.\n";
        $pdo->rollBack();
        exit;
    }

    $stmtMember = $pdo->prepare(
        "INSERT INTO memberz (member_no, full_name, email, phone, reg_date, 
                              is_system_user, user_id, nin_number, gender, dob, occupation, district, country, profile_pic, signature_pic) 
         VALUES (:member_no, :full_name, :email, :phone, NOW(), 
                 0, NULL, 'N/A', 'Other', '1970-01-01', 'System Administrator', 'N/A', 'N/A', NULL, NULL)"
    );
    $memberParams = [
        'member_no' => $memberNo,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone
    ];
    $stmtMember->execute($memberParams);
    $newMemberId = $pdo->lastInsertId();

    if (!$newMemberId) {
        throw new Exception("Failed to create member record.");
    }

    // --- Insert into `users` ---
    $stmtUser = $pdo->prepare(
        "INSERT INTO users (member_id, username, password_hash, email, phone, 
                            is_active, activation_token, token_expires_at, created_at, updated_at)
         VALUES (:member_id, :username, :password_hash, :email, :phone, 
                 1, NULL, NULL, NOW(), NOW())"
    );
    $userParams = [
        'member_id' => $newMemberId,
        'username' => $username,
        'password_hash' => $passwordHash,
        'email' => $email,
        'phone' => $phone
    ];
    $stmtUser->execute($userParams);
    $newUserId = $pdo->lastInsertId();

    if (!$newUserId) {
        throw new Exception("Failed to create user record.");
    }

    // --- Update `memberz` with user_id ---
    $stmtUpdateMember = $pdo->prepare("UPDATE memberz SET is_system_user = 1, user_id = :user_id WHERE id = :member_id");
    $stmtUpdateMember->execute(['user_id' => $newUserId, 'member_id' => $newMemberId]);

    // --- Assign Role ---
    // Assuming Role ID 1 = 'Core Admin' and Group ID 1 = 'Core Admins'
    $roleId = 1; 
    $groupId = 1; 
    $stmtAssignRole = $pdo->prepare(
        "INSERT INTO user_group_roles (user_id, group_id, role_id) VALUES (:user_id, :group_id, :role_id)"
    );
    $stmtAssignRole->execute(['user_id' => $newUserId, 'group_id' => $groupId, 'role_id' => $roleId]);

    // --- Commit Transaction ---
    $pdo->commit();

    echo "Core Admin user '{$username}' created successfully.\n";
    echo "User ID: {$newUserId}, Member ID: {$newMemberId}\n";
    echo "Assigned to Group ID {$groupId} with Role ID {$roleId}.\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error (PDO): " . $e->getMessage() . "\n";
    if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) { // MySQL duplicate entry
        echo "This might be due to a duplicate entry that wasn't caught by the initial check (e.g. member_no).\n";
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}

?>
