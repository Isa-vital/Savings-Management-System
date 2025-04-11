<?php
require_once '../config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    redirect('../index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);

    // TEMPORARY: Hardcoded admin credentials (remove in production)
    $valid_username = 'admin';
    $valid_password_hash = password_hash('admin123', PASSWORD_BCRYPT);
    
    if ($username === $valid_username && password_verify('admin123', $valid_password_hash)) {
        $_SESSION['user'] = [
            'id' => 1,
            'username' => $username,
            'role' => 'admin',
            'email' => 'admin@sacco.com',
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        redirect('../index.php');
    } else {
        $error = "Invalid credentials";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Savings Mgt System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>System Login</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
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
        </div>
    </div>
</body>
</html>