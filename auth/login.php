<?php
<?php
// Attempt to include config.php for APP_NAME and BASE_URL.
$app_name = 'Our SACCO Platform'; // Default
$base_url = '../'; // Default base URL for relative links from auth/ back to root

if (file_exists(__DIR__ . '/../config.php')) {
    @include_once __DIR__ . '/../config.php'; // Suppress errors
    if (defined('APP_NAME')) {
        $app_name = APP_NAME;
    }
    if (defined('BASE_URL')) {
        $base_url = BASE_URL; // Assuming BASE_URL ends with a slash
    }
}

// Session management (ensure it's started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// The original script had a session_unset() if session was active.
// This is unusual for a login page unless it's meant to force a new login, clearing old session.
// For a typical login, you'd just ensure session is started.
// If the goal was to clear a previous user's session before a new login attempt,
// that should happen *after* successful login of a *different* user, or on explicit logout.
// For now, removing the session_unset() to avoid clearing potentially useful session data (like error messages from other pages).

// $pdo is available from config.php

// Initialize variables at the start
$error = $_SESSION['error_message'] ?? ''; // Use session error message if available
if(isset($_SESSION['error_message'])) unset($_SESSION['error_message']);

$success_message = $_SESSION['success_message'] ?? '';
if(isset($_SESSION['success_message'])) unset($_SESSION['success_message']);

$username = '';

// Check if already logged in
if (isset($_SESSION['user']['id'])) { // Changed from admin to user
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        try {
            // Target 'users' table and fetch necessary fields
            $stmt = $pdo->prepare("SELECT id, username, password_hash, email, member_id, is_active FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if account is active
                if (!$user['is_active']) {
                    $error = "Your account is not active. Please check your email to activate it or contact an administrator.";
                } else {
                    // Fetch roles
                    $rolesStmt = $pdo->prepare("
                        SELECT r.role_name
                        FROM roles r
                        JOIN user_group_roles ugr ON r.id = ugr.role_id
                        WHERE ugr.user_id = :user_id
                    ");
                    $rolesStmt->execute(['user_id' => $user['id']]);
                    $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

                    // Fetch groups
                    $groupsStmt = $pdo->prepare("
                        SELECT g.group_name
                        FROM groups g
                        JOIN user_group_roles ugr ON g.id = ugr.group_id
                        WHERE ugr.user_id = :user_id
                    ");
                    $groupsStmt->execute(['user_id' => $user['id']]);
                    $groups = $groupsStmt->fetchAll(PDO::FETCH_COLUMN);

                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'member_id' => $user['member_id'],
                        'roles' => $roles,
                        'groups' => $groups,
                        'last_activity' => time()
                    ];
                    session_regenerate_id(true);
                    // Redirect to a dashboard or main page after login
                    header("Location: " . rtrim($base_url, '/') . "/index.php");
                    exit;
                }
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "System error during login. Please try again later.";
            error_log("Login PDOException: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($app_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px; /* For spacing on very small screens */
        }
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-header h2 {
            font-weight: 600;
            color: #333;
        }
        .login-header a {
            text-decoration: none;
            color: #555;
            font-size: 0.9rem;
        }
        .login-header a:hover {
            color: #007bff;
        }
        .login-card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            padding: 2rem; /* More padding inside card */
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem; /* Space between inputs */
        }
        .btn-primary {
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            background-color: #007bff; /* Ensure consistent button color */
            border-color: #007bff;
            color: white; /* Default text color for primary button */
            transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out, color 0.2s ease-in-out; /* Added transition */
        }
        .btn-primary:hover {
            background-color: #ffc107; /* A standard yellow color */
            border-color: #ffc107; /* Match border color */
            color: #212529; /* Dark color for text on yellow background for contrast */
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9em;
        }
        .login-footer a {
            color: #007bff;
            text-decoration: none;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <a href="<?php echo rtrim($base_url, '/'); ?>/landing.php" title="Back to Homepage">
                <i class="fas fa-home me-1"></i> <!-- Optional: Home icon -->
                <h2><?php echo htmlspecialchars($app_name); ?></h2>
            </a>
        </div>

        <div class="card login-card">
            <div class="card-body">
                <h4 class="card-title text-center mb-4">User Login</h4>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                     <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>"> <!-- Assuming generateToken() is available -->
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3 text-end">
                        <a href="<?php echo rtrim($base_url, '/') . '/auth/forgot_password.php'; ?>" class="text-muted small">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
        <div class="login-footer">
            <p>Don't have an account? <a href="<?php echo rtrim($base_url, '/'); ?>/auth/register.php">Sign Up</a></p>
            <p class="text-muted">&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($app_name); ?></p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>