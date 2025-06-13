<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/auth.php'; // Include the new auth helpers


if (!isset($_SESSION['user']['id'])) { // Check the new session structure
    header("Location: " . BASE_URL . "landing.php");
    exit;
}
error_log("Index session data for user: " . print_r($_SESSION['user'] ?? 'No user session', true));

// Check if the user has the required role(s) for this admin dashboard
if (!has_role(['Core Admin', 'Administrator'])) {
    // If logged in but not an admin/core_admin, redirect to landing or a member dashboard.
    $_SESSION['error_message'] = "You do not have permission to access this dashboard."; // Use the new session key
    // Potentially redirect to a member-specific dashboard if one exists and user is a member
    if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header('Location: ' . BASE_URL . 'members/my_savings.php'); // Example member page
    } else {
        header('Location: ' . BASE_URL . 'landing.php'); // Default redirect for non-privileged users
    }
    exit;
}

// Initialize stats array
$stats = [];
$transactions = [];

try {
    // ==================== DASHBOARD STATISTICS ====================
    // Total Members
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM memberz");
    $stats['total_members'] = $stmt->fetchColumn();

    // Total Savings
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM savings");
    $stats['total_savings'] = $stmt->fetchColumn();

    // Active Loans
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'approved'");
    $stats['active_loans'] = $stmt->fetchColumn();

    // Recent Transactions (last 7 days)
    $stmt = $pdo->query("
        SELECT t.id, m.full_name, t.amount, t.transaction_date, t.transaction_type 
        FROM transactions t
        JOIN memberz m ON t.member_id = m.member_no
        ORDER BY t.transaction_date DESC 
        LIMIT 10
    ");
    $transactions = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "System error occurred. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        .stat-card {
            transition: transform 0.3s;
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-card.members {
            border-left-color: #4e73df;
        }
        .stat-card.savings {
            border-left-color: #1cc88a;
        }
        .stat-card.loans {
            border-left-color: #f6c23e;
        }
        .recent-transactions {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'partials/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </h1>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card members h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="text-uppercase text-muted mb-0">Total Members</h5>
                                        <h2 class="mb-0"><?= number_format($stats['total_members'] ?? 0) ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <a href="/savingssystem/members/memberslist.php" class="text-decoration-none">
                                    View all members <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card savings h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="text-uppercase text-muted mb-0">Total Savings</h5>
                                        <h2 class="mb-0">UGX <?= number_format($stats['total_savings'] ?? 0, 2) ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <a href="/savingssystem/savings/savingslist.php" class="text-decoration-none">
                                    View Savings Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card loans h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="text-uppercase text-muted mb-0">Active Loans</h5>
                                        <h2 class="mb-0"><?= number_format($stats['active_loans'] ?? 0) ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <a href="/savingssystem/loans/loanslist.php" class="text-decoration-none">
                                    Manage loans <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
               
                
                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="members/register.php" class="btn btn-primary w-100 py-3">
                            <i class="fas fa-user-plus me-2"></i>Register New Member
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="savings/savings.php" class="btn btn-success w-100 py-3">
                            <i class="fas fa-money-bill-wave me-2"></i>Record Savings
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="loans/newloan.php" class="btn btn-warning w-100 py-3">
                            <i class="fas fa-hand-holding-usd me-2"></i>Process Loan
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Simple error display from session
        <?php if (isset($_SESSION['error'])): ?>
            alert('<?= addslashes($_SESSION['error']) ?>');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>