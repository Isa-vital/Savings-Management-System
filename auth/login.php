<?php
//session start At the VERY TOP (before any output)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
} else {
    session_start();
}

// Database configuration
require_once __DIR__ . '/../config.php'; // $pdo is available from here

// Initialize variables at the start
$error = '';
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

                    $_SESSION['user'] = [ // Changed from admin to user
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'member_id' => $user['member_id'],
                        'roles' => $roles,
                        'groups' => $groups,
                        'last_activity' => time()
                    ];
                    session_regenerate_id(true);
                    header("Location: ../index.php");
                    exit;
                }
            } else {
                $error = "Invalid username or password";
            }
        } catch (PDOException $e) {
            $error = "System error. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Login</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">User Login</h4> 
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>