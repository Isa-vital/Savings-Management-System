
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm px-3 py-3">
    <div class="container-fluid">
        <!-- App Name -->
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center" href="index.php">
            <i class="fas fa-cubes me-3 fs-3 text-light"></i>
            <?= APP_NAME ?>
        </a>

        <!-- User Dropdown -->
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center fs-5" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2 fs-3 text-light"></i>
                    <span class="text-light"><?= htmlspecialchars($_SESSION['admin']['username']) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <a class="dropdown-item fs-6" href="/savingssystem/profile.php">
                            <i class="fas fa-user me-2"></i> Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger fs-6" href="auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
