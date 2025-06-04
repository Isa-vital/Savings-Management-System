<?php
//session start At the VERY TOP (before any output)
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
            background-color: #f0f2f5; /* Lighter grey for a softer look */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center; /* Center the card horizontally */
            padding: 1rem; /* Add some padding for smaller screens */
        }
        .login-card {
            max-width: 450px; /* Slightly wider card */
            width: 100%;
            border: none; /* Remove default card border if any */
            border-radius: 0.75rem; /* More pronounced border radius */
        }
        .login-card .card-header {
            background-color: #007bff; /* Bootstrap primary blue */
            color: white;
            text-align: center;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            padding-top: 1.5rem;
            padding-bottom: 0.5rem; /* Reduced padding below title */
        }
        .login-card .card-body {
            padding: 2rem; /* Increased padding in card body */
        }
        .login-logo {
            max-width: 100px;
            margin-bottom: 1rem;
        }
        .form-control {
            border-radius: 0.5rem; /* Softer radius for inputs */
            padding: 0.75rem 1rem;
        }
        .btn-primary {
            border-radius: 0.5rem; /* Softer radius for button */
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light"> {/* Ensure body takes full viewport height and centers content */}
    <div class="login-card card shadow-lg"> {/* Added shadow-lg for more depth */}
        <div class="card-header">
            <img src="https://via.placeholder.com/150x50.png?text=AppLogo" alt="Logo" class="mx-auto d-block login-logo">
            <h4 class="mb-0 text-center">Admin Login</h4>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger rounded-pill"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" class="mt-3">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                    </div>
                    <div class="mb-4"> {/* Increased margin bottom for spacing */}
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
</body>
</html>