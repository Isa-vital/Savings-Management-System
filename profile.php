<?php
session_start();
require_once __DIR__ . '/config.php'; // Ensures $pdo, BASE_URL, APP_NAME
require_once __DIR__ . '/helpers/auth.php'; // For require_login, has_role

require_login(); // Ensures any logged-in user can access their profile.

$user_id = $_SESSION['user']['id'];
$current_user_data = null;
$error_message = '';
$success_message = '';


// Fetch user profile
try {
    $stmt = $pdo->prepare("SELECT id, username, email, phone, photo, signature FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $current_user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_user_data) {
        // This should ideally not happen if user is logged in, but as a safeguard
        $_SESSION['error_message'] = "Unable to load your profile data.";
        header("Location: " . BASE_URL . "index.php"); // Redirect to a safe page
        exit;
    }
} catch (PDOException $e) {
    error_log("Profile Fetch PDOException for user_id {$user_id}: " . $e->getMessage());
    $error_message = "Could not load your profile due to a database error.";
    // Allow page to render to show error
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $error_message = 'CSRF token validation failed. Please try again.';
    } elseif ($current_user_data) { // Proceed only if user data was loaded
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone'] ?? ''); // Add phone update

        // Initialize with existing images, update if new ones are uploaded
        $photo = $current_user_data['photo'];
        $signature = $current_user_data['signature'];
        $update_fields = ['email' => $email, 'phone' => $phone]; // Start with fields always updated

        if (empty($email)) {
            $error_message = "Email address cannot be empty.";
        } else {
            // Handle profile photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK && !empty($_FILES['photo']['name'])) {
                $photo_dir = __DIR__ . "/assets/uploads/";
                if (!is_dir($photo_dir)) mkdir($photo_dir, 0755, true);
                $photo_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($photo_ext, $allowed_exts) && $_FILES['photo']['size'] < 2000000) { // Max 2MB
                    $photoName = "user_" . $user_id . "_photo_" . uniqid() . "." . $photo_ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_dir . $photoName)) {
                        // Optionally delete old photo if it exists and is different
                        if ($photo && $photo !== $photoName && file_exists($photo_dir . $photo)) {
                            @unlink($photo_dir . $photo);
                        }
                        $photo = $photoName;
                        $update_fields['photo'] = $photo;
                    } else {
                        $error_message = ($error_message ? $error_message."<br>" : "") . "Failed to upload profile photo.";
                    }
                } else {
                     $error_message = ($error_message ? $error_message."<br>" : "") . "Invalid photo file type or size (max 2MB, jpg/png/gif).";
                }
            }

            // Handle signature upload
            if (isset($_FILES['signature']) && $_FILES['signature']['error'] == UPLOAD_ERR_OK && !empty($_FILES['signature']['name'])) {
                $sig_dir = __DIR__ . "/assets/uploads/";
                if (!is_dir($sig_dir)) mkdir($sig_dir, 0755, true);
                $sig_ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
                 if (in_array($sig_ext, $allowed_exts) && $_FILES['signature']['size'] < 1000000) { // Max 1MB
                    $sigName = "user_" . $user_id . "_sig_" . uniqid() . "." . $sig_ext;
                    if (move_uploaded_file($_FILES['signature']['tmp_name'], $sig_dir . $sigName)) {
                         if ($signature && $signature !== $sigName && file_exists($sig_dir . $signature)) {
                            @unlink($sig_dir . $signature);
                        }
                        $signature = $sigName;
                        $update_fields['signature'] = $signature;
                    } else {
                        $error_message = ($error_message ? $error_message."<br>" : "") . "Failed to upload signature image.";
                    }
                } else {
                    $error_message = ($error_message ? $error_message."<br>" : "") . "Invalid signature file type or size (max 1MB, jpg/png/gif).";
                }
            }

            if (empty($error_message)) { // Proceed if no upload errors and email is valid
                try {
                    // Check if email needs to be updated and if it's unique (if changed)
                    if ($email !== $current_user_data['email']) {
                        $stmt_check_email = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
                        $stmt_check_email->execute(['email' => $email, 'user_id' => $user_id]);
                        if ($stmt_check_email->fetch()) {
                            $error_message = "That email address is already in use by another account.";
                        }
                    }

                    if (empty($error_message)) {
                         $sql_parts = [];
                         foreach (array_keys($update_fields) as $field) {
                             $sql_parts[] = "`{$field}` = :{$field}";
                         }
                         $sql_update = "UPDATE users SET " . implode(', ', $sql_parts) . ", updated_at = NOW() WHERE id = :user_id";

                        $updateStmt = $pdo->prepare($sql_update);
                        $update_params = array_merge($update_fields, ['user_id' => $user_id]);
                        $updateStmt->execute($update_params);

                        $_SESSION['success_message'] = "Profile updated successfully.";
                        header("Location: profile.php"); // Redirect to refresh and show success
                        exit;
                    }
                } catch (PDOException $e) {
                     error_log("Profile Update PDOException for user_id {$user_id}: " . $e->getMessage());
                     $error_message = "Database error updating profile. Please try again.";
                }
            }
        }
    } else {
        $error_message = "Could not load user data to process update.";
    }
}

// Re-fetch data if updated, or use existing if not POST/failed POST
if (isset($_SESSION['success_message'])) { // After redirect
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
    // Re-fetch user data to show updated info
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, phone, photo, signature FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $current_user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Profile Re-Fetch PDOException for user_id {$user_id}: " . $e->getMessage());
        $error_message = "Could not reload profile data after update.";
    }
}
if(isset($_SESSION['error_message']) && empty($error_message)){ // From redirect
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Basic styling, can be expanded or moved to a CSS file */
        .profile-img-preview { max-height: 150px; margin-bottom: 10px; }
        .img-thumbnail { padding: 0.25rem; background-color: #fff; border: 1px solid #dee2e6; border-radius: 0.25rem; max-width: 100%; height: auto;}
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h2 class="mb-4"><i class="fas fa-user-circle me-2"></i>My Profile</h2>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($current_user_data): ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Update Your Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($current_user_data['username']); ?>" disabled readonly>
                                    <small class="form-text text-muted">Username cannot be changed.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email address</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? $current_user_data['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ($current_user_data['phone'] ?? '')); ?>">
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <label for="photo" class="form-label">Profile Photo</label><br>
                                    <?php if (!empty($current_user_data['photo'])): ?>
                                        <img src="<?php echo BASE_URL . 'assets/uploads/' . htmlspecialchars($current_user_data['photo']); ?>" alt="Current Profile Photo" class="img-thumbnail profile-img-preview">
                                    <?php else: ?>
                                        <img src="https://via.placeholder.com/150?text=No+Photo" alt="No Profile Photo" class="img-thumbnail profile-img-preview">
                                    <?php endif; ?>
                                    <input type="file" name="photo" id="photo" class="form-control form-control-sm mt-2">
                                    <small class="form-text text-muted">Max 2MB (JPG, PNG, GIF)</small>
                                </div>
                                <div class="mb-3">
                                    <label for="signature" class="form-label">Signature Image</label><br>
                                    <?php if (!empty($current_user_data['signature'])): ?>
                                        <img src="<?php echo BASE_URL . 'assets/uploads/' . htmlspecialchars($current_user_data['signature']); ?>" alt="Current Signature" class="img-thumbnail profile-img-preview">
                                    <?php else: ?>
                                         <img src="https://via.placeholder.com/150x75?text=No+Signature" alt="No Signature" class="img-thumbnail profile-img-preview">
                                    <?php endif; ?>
                                    <input type="file" name="signature" id="signature" class="form-control form-control-sm mt-2">
                                    <small class="form-text text-muted">Max 1MB (JPG, PNG, GIF)</small>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update Profile</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
                <p>Could not load profile information.</p>
            <?php endif; ?>
        </main>
    </div>
    <?php require_once 'partials/footer.php'; ?>
</div>

   