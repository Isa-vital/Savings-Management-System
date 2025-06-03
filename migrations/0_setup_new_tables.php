<?php

require_once __DIR__ . '/../config.php'; // Assuming config.php is in the root directory

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create roles table
    $sqlRoles = "
    CREATE TABLE IF NOT EXISTS `roles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `role_name` VARCHAR(255) NOT NULL UNIQUE,
        `description` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlRoles);
    echo "Table 'roles' created successfully or already exists.\n";

    // Populate roles table
    $stmt = $pdo->prepare("INSERT INTO `roles` (`role_name`, `description`) VALUES (:role_name, :description) ON DUPLICATE KEY UPDATE role_name=role_name;");
    $rolesData = [
        ['Core Admin', 'Full control over all groups, users, and system settings.'],
        ['Administrator', 'Access all application features except system settings.'],
        ['Member', 'Access to personal savings, loan history, and limited system interaction.']
    ];
    foreach ($rolesData as $role) {
        $stmt->execute(['role_name' => $role[0], 'description' => $role[1]]);
    }
    echo "Roles populated successfully.\n";

    // 2. Create groups table
    $sqlGroups = "
    CREATE TABLE IF NOT EXISTS `groups` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `group_name` VARCHAR(255) NOT NULL UNIQUE,
        `description` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlGroups);
    echo "Table 'groups' created successfully or already exists.\n";

    // 3. Create users table
    $sqlUsers = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NULL,
        `username` VARCHAR(255) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `phone` VARCHAR(20) NULL,
        `is_active` BOOLEAN DEFAULT 0,
        `activation_token` VARCHAR(255) NULL UNIQUE,
        `token_expires_at` TIMESTAMP NULL,
        `last_login` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_member_id` (`member_id`),
        CONSTRAINT `fk_users_memberz` FOREIGN KEY (`member_id`) REFERENCES `memberz`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlUsers);
    echo "Table 'users' created successfully or already exists.\n";

    // 4. Create user_group_roles table
    $sqlUserGroupRoles = "
    CREATE TABLE IF NOT EXISTS `user_group_roles` (
        `user_id` INT NOT NULL,
        `group_id` INT NOT NULL,
        `role_id` INT NOT NULL,
        PRIMARY KEY (`user_id`, `group_id`, `role_id`),
        CONSTRAINT `fk_ugr_users` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_ugr_groups` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_ugr_roles` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlUserGroupRoles);
    echo "Table 'user_group_roles' created successfully or already exists.\n";

    // 5. Create system_settings table
    $sqlSystemSettings = "
    CREATE TABLE IF NOT EXISTS `system_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(255) NOT NULL UNIQUE,
        `setting_value` TEXT NULL,
        `description` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlSystemSettings);
    echo "Table 'system_settings' created successfully or already exists.\n";

    // 6. Alter memberz table
    // Add is_system_user column
    $sqlAlterMemberz1 = "ALTER TABLE `memberz` ADD COLUMN IF NOT EXISTS `is_system_user` BOOLEAN NOT NULL DEFAULT 0;";
    $pdo->exec($sqlAlterMemberz1);
    echo "Column 'is_system_user' added to 'memberz' table successfully or already exists.\n";

    // Add user_id column
    // Need to check if the column exists before adding foreign key, as ADD COLUMN IF NOT EXISTS is not universal for FK constraints.
    // This part might need to be split if the column could exist without the constraint.
    // For simplicity, assuming if user_id column needs adding, constraint also needs adding.

    // Check if user_id column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `memberz` LIKE 'user_id'");
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exists) {
        $sqlAlterMemberz2 = "ALTER TABLE `memberz` ADD COLUMN `user_id` INT NULL UNIQUE, ADD CONSTRAINT `fk_memberz_users` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;";
        $pdo->exec($sqlAlterMemberz2);
        echo "Column 'user_id' with foreign key constraint added to 'memberz' table successfully.\n";
    } else {
        // Check if foreign key exists
        $stmt = $pdo->prepare("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'memberz' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users';");
        $stmt->execute();
        $fkExists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fkExists) {
            $sqlAlterMemberz2_fk = "ALTER TABLE `memberz` ADD CONSTRAINT `fk_memberz_users` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;";
            $pdo->exec($sqlAlterMemberz2_fk);
            echo "Foreign key constraint 'fk_memberz_users' on 'user_id' added to 'memberz' table successfully.\n";
        } else {
            echo "Column 'user_id' and its foreign key constraint in 'memberz' table already exist.\n";
        }
    }

    echo "Migration script executed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}

?>
