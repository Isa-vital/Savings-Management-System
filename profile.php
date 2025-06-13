<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration and authentication helpers
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/auth.php';

// Verify user is logged in
require_login();

// Initialize variables
$user_id = $_SESSION['user']['id'] ?? null;
$current_user_data = null;
$error_message = '';
$success_message = '';

// Verify we have a valid user ID
if (!$user_id) {
    $_SESSION['error_message'] = "Invalid user session. Please log in again.";
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

// Fetch user profile data with enhanced error handling
try {
    $stmt = $pdo->prepare("
        SELECT id, username, email, phone, photo, signature 
        FROM users 
        WHERE id = :user_id
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $user_id]);
    $current_user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_user_data) {
        throw new Exception("User profile not found in database");
    }

} catch (PDOException $e) {
    error_log("Database Error [Profile Fetch]: " . $e->getMessage());
    $error_message = "Could not load your profile due to a database error.";
} catch (Exception $e) {
    error_log("Profile Error: " . $e->getMessage());
    $error_message = $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user_data) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $error_message = 'Security token validation failed.';
    } else {
        // Process form data
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? $current_user_data['username']);

        // Initialize with existing values
        $update_data = [
            'username' => $username ?: $current_user_data['username'],
            'email' => $email ?: $current_user_data['email'],
            'phone' => $phone ?: $current_user_data['phone'],
            'photo' => $current_user_data['photo'],
            'signature' => $current_user_data['signature']
        ];

        // Validate email
        if (!$email && empty($current_user_data['email'])) {
            $error_message = "A valid email address is required.";
        }

        // Validate username
        if (empty($username)) {
            $error_message = "Username is required.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,}$/', $username)) {
            $error_message = "Username must be at least 3 characters and contain only letters, numbers, or underscores.";
        }

        // Handle file uploads if no errors yet
        if (empty($error_message)) {
            $upload_dir = __DIR__ . '/assets/uploads/';
            
            // Process profile photo
            if (!empty($_FILES['photo']['name'])) {
                $photo_result = handleFileUpload('photo', $upload_dir, [
                    'max_size' => 2 * 1024 * 1024, // 2MB
                    'user_id' => $user_id,
                    'current_file' => $current_user_data['photo']
                ]);
                
                if ($photo_result['success']) {
                    $update_data['photo'] = $photo_result['filename'];
                } else {
                    $error_message = $photo_result['error'];
                }
            }

            // Process signature if no errors yet
            if (empty($error_message) && !empty($_FILES['signature']['name'])) {
                $signature_result = handleFileUpload('signature', $upload_dir, [
                    'max_size' => 1 * 1024 * 1024, // 1MB
                    'user_id' => $user_id,
                    'current_file' => $current_user_data['signature']
                ]);
                
                if ($signature_result['success']) {
                    $update_data['signature'] = $signature_result['filename'];
                } else {
                    $error_message = $signature_result['error'];
                }
            }
        }

        // If still no errors, update database
        if (empty($error_message)) {
            try {
                // Check if email or username is being changed and is unique
                if (
                    $update_data['email'] !== $current_user_data['email'] ||
                    $update_data['username'] !== $current_user_data['username']
                ) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM users 
                        WHERE (email = :email OR username = :username) AND id != :user_id 
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':email' => $update_data['email'],
                        ':username' => $update_data['username'],
                        ':user_id' => $user_id
                    ]);
                    
                    if ($stmt->fetch()) {
                        throw new Exception("That email address or username is already in use.");
                    }
                }

                // Build dynamic update query
                $set_parts = [];
                foreach ($update_data as $field => $value) {
                    $set_parts[] = "$field = :$field";
                }
                
                $sql = "
                    UPDATE users 
                    SET " . implode(', ', $set_parts) . " 
                    WHERE id = :user_id
                ";
                
                $stmt = $pdo->prepare($sql);
                $update_data['user_id'] = $user_id;
                $stmt->execute($update_data);

                $_SESSION['success_message'] = "Profile updated successfully!";
                header("Location: profile.php");
                exit;
                
            } catch (PDOException $e) {
                error_log("Database Error [Profile Update]: " . $e->getMessage());
                $error_message = "Failed to update profile. Please try again.";
            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        }
    }
}

// File upload handler function
function handleFileUpload($field, $upload_dir, $options) {
    $result = ['success' => false];
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES[$field];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    
    // Validate file
    if (!in_array($ext, $allowed_types)) {
        $result['error'] = "Invalid file type. Only JPG, PNG, GIF allowed.";
    } elseif ($file['size'] > $options['max_size']) {
        $result['error'] = "File too large. Max " . ($options['max_size'] / 1024 / 1024) . "MB allowed.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = "File upload error: " . $file['error'];
    } else {
        // Generate unique filename
        $filename = "user_{$options['user_id']}_{$field}_" . uniqid() . ".$ext";
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Delete old file if it exists
            if (!empty($options['current_file']) && file_exists($upload_dir . $options['current_file'])) {
                @unlink($upload_dir . $options['current_file']);
            }
            
            $result['success'] = true;
            $result['filename'] = $filename;
        } else {
            $result['error'] = "Failed to save uploaded file.";
        }
    }
    
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
        }
        .img-preview-container {
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-user-circle me-2"></i>My Profile</h1>
                </div>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <?php if ($current_user_data): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateToken()) ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($current_user_data['username']) ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" id="email" name="email" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? $current_user_data['email']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['phone'] ?? $current_user_data['phone']) ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Profile Photo</label>
                                        <div class="img-preview-container">
                                            <?php if (!empty($current_user_data['photo'])): ?>
                                                <img src="<?= BASE_URL ?>assets/uploads/<?= htmlspecialchars($current_user_data['photo']) ?>" 
                                                     alt="Profile Photo" class="profile-img img-thumbnail">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/200" alt="No Photo" class="profile-img img-thumbnail">
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="photo" class="form-control" accept="image/*">
                                        <small class="text-muted">Max 2MB (JPG, PNG, GIF)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Signature</label>
                                        <div class="img-preview-container">
                                            <?php if (!empty($current_user_data['signature'])): ?>
                                                <img src="<?= BASE_URL ?>assets/uploads/<?= htmlspecialchars($current_user_data['signature']) ?>" 
                                                     alt="Signature" class="profile-img img-thumbnail">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/200x100" alt="No Signature" class="profile-img img-thumbnail">
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="signature" class="form-control" accept="image/*">
                                        <small class="text-muted">Max 1MB (JPG, PNG, GIF)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview image before upload
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const preview = this.closest('.mb-3').querySelector('img');
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
</body>
</html>