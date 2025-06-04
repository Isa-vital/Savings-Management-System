<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Our Application</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 56px; } /* Adjust body padding for fixed navbar */
        .hero-section {
            background: url('https://via.placeholder.com/1500x600.png?text=Hero+Background+Image') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: bold;
        }
        .hero-section p {
            font-size: 1.25rem;
            margin-bottom: 30px;
        }
        .features-section {
            padding: 60px 0;
        }
        .feature-item {
            text-align: center;
            margin-bottom: 30px;
        }
        .feature-item img {
            width: 80px; /* Placeholder icon size */
            height: 80px;
            margin-bottom: 15px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 0;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="https://via.placeholder.com/100x40.png?text=Logo" alt="Logo" style="height: 40px;">
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item active">
                        <a class="nav-link" href="#">Home <span class="sr-only">(current)</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary btn-sm ml-lg-2" href="#">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section">
        <div class="container">
            <h1>Welcome to Our Amazing Application</h1>
            <p>Discover how our solution can revolutionize your workflow and boost productivity.</p>
            <a href="#" class="btn btn-success btn-lg">Get Started</a>
        </div>
    </header>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="text-center mb-5">Key Features</h2>
            <div class="row">
                <div class="col-md-4 feature-item">
                    <img src="https://via.placeholder.com/80x80.png?text=Feature1" alt="Feature 1 Icon">
                    <h3>Feature One</h3>
                    <p>Briefly describe the first key feature or benefit here. Focus on value to the user.</p>
                </div>
                <div class="col-md-4 feature-item">
                    <img src="https://via.placeholder.com/80x80.png?text=Feature2" alt="Feature 2 Icon">
                    <h3>Feature Two</h3>
                    <p>Explain the second important feature. Highlight how it solves a problem or improves a process.</p>
                </div>
                <div class="col-md-4 feature-item">
                    <img src="https://via.placeholder.com/80x80.png?text=Feature3" alt="Feature 3 Icon">
                    <h3>Feature Three</h3>
                    <p>Detail the third main feature. Emphasize its unique advantages and user benefits.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 Your Company Name. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
