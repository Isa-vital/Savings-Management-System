<?php
// Attempt to include config.php for APP_NAME.
// If it causes issues in the testing environment, this can be simplified to a hardcoded name.
$app_name = 'Our SACCO Platform'; // Default
$base_url = './'; // Default base URL for relative links from root

if (file_exists(__DIR__ . '/config.php')) {
    @include_once __DIR__ . '/config.php'; // Suppress errors if any constants are redefined etc.
    if (defined('APP_NAME')) {
        $app_name = APP_NAME;
    }
    if (defined('BASE_URL')) { // Assuming BASE_URL ends with a slash
        $base_url = BASE_URL;
    } else {
        // Fallback BASE_URL detection if not in config or config not included
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // Basic path detection, assumes landing.php is in root.
        // For /subdir/landing.php, SCRIPT_NAME is /subdir/landing.php, dirname gives /subdir
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $base_url = $protocol . $host . $path . '/';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo htmlspecialchars($app_name); ?></title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 56px; /* Adjusted for fixed-top navbar */ }
        .hero-section {
            background: url('https://images.unsplash.com/photo-1579621970795-87facc2f976d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1200&q=80') center center/cover no-repeat;
            background-blend-mode: darken;
            color: white;
            padding: 8rem 0;
            text-align: center;
        }
        .hero-section h1 { font-size: 3.5rem; font-weight: bold; }
        .hero-section p { font-size: 1.25rem; margin-bottom: 2rem; }
        .features-section { padding: 4rem 0; }
        .feature-item { text-align: center; margin-bottom: 2rem; }
        .feature-icon { font-size: 3rem; margin-bottom: 1rem; color: #0d6efd; }
        .footer { background-color: #f8f9fa; padding: 2rem 0; text-align: center; margin-top: 3rem;}
        .navbar-brand img { max-height: 40px; margin-right: 10px;} /* Basic logo styling */
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <!-- Optional: <img src="path/to/your/logo.png" alt="Logo"> -->
                <?php echo htmlspecialchars($app_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo rtrim($base_url, '/'); ?>/auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="<?php echo rtrim($base_url, '/'); ?>/auth/register.html">Sign Up</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Secure Your Future, Save With Us</h1>
            <p class="lead">Join thousands of members who are building a brighter financial future through disciplined savings and accessible credit with <?php echo htmlspecialchars($app_name); ?>.</p>
            <a href="<?php echo rtrim($base_url, '/'); ?>/auth/register.html" class="btn btn-lg btn-success me-2">Sign Up Now</a>
            <a href="<?php echo rtrim($base_url, '/'); ?>/auth/login.php" class="btn btn-lg btn-outline-light">Login</a>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose Us?</h2>
            <div class="row">
                <div class="col-md-4 feature-item">
                    <i class="fas fa-piggy-bank feature-icon"></i>
                    <h3>Easy Savings Management</h3>
                    <p>Track your savings, view statements, and manage your account with ease through our user-friendly platform.</p>
                    <img src="assets/uploads/easy saavings.jpg" class="img-fluid rounded mt-2" alt="Piggy bank representing easy savings">
                </div>
                <div class="col-md-4 feature-item">
                    <i class="fas fa-hand-holding-usd feature-icon"></i>
                    <h3>Accessible Loans</h3>
                    <p>Get access to affordable loan products when you need them, with transparent terms and fair rates.</p>
                    <img src="assets/uploads/loan.jpg" class="img-fluid rounded mt-2" alt="Abstract security lock">
                </div>
                <div class="col-md-4 feature-item">
                    <i class="fas fa-shield-alt feature-icon"></i>
                    <h3>Secure & Transparent</h3>
                    <p>Your funds and data are secure with us. We operate with utmost transparency and accountability.</p>
                    <img src="assets/uploads/seecure.jpg" class="img-fluid rounded mt-2" alt="Diverse group of people representing community">
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section (Optional) -->
    <section id="howitworks" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Simple Steps to Get Started</h2>
            <div class="row text-center">
                <div class="col-md-4 mb-3">
                    <div class="p-3 border rounded shadow-sm">
                        <div class="display-4 text-primary mb-2">1</div>
                        <h4>Sign Up</h4>
                        <p>Register with us to get started</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                     <div class="p-3 border rounded shadow-sm">
                        <div class="display-4 text-primary mb-2">2</div>
                        <h4>Deposit Savings</h4>
                        <p>Start building your financial future.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                     <div class="p-3 border rounded shadow-sm">
                        <div class="display-4 text-primary mb-2">3</div>
                        <h4>Track Progress</h4>
                        <p>Monitor your growth and apply for loans.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Footer -->
     <?php require_once 'partials/footer.php'; ?>

    <!-- Bootstrap JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
