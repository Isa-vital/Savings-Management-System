<?php
// At the VERY TOP (before any output)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
} else {
    session_start();
}

// Database configuration
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/database.php';
$pdo = $conn;

// Initialize variables at the start
$error = '';
$username = '';

// Check if already logged in
if (isset($_SESSION['admin']['id'])) {
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
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin'] = [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'role' => $admin['role'],
                    'last_activity' => time()
                ];
                session_regenerate_id(true);
                header("Location: ../index.php");
                exit;
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
    <title>Admin Login</title>
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
                <h4 class="mb-0">Admin Login</h4>
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