<?php
/**
 * Rukindo Kweyamba System - Dashboard
 * 
 * Displays overview statistics and recent transactions
 */
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    redirect('auth/login.php');
}

// Check user role for authorization
if ($_SESSION['user']['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    redirect('members/list.php');
}

// ==================== DASHBOARD STATISTICS ====================
$stats = [];

// Total Members
$result = $conn->query("SELECT COUNT(*) as total FROM memberz");
$stats['total_members'] = $result->fetch_assoc()['total'];

// Total Savings
$result = $conn->query("SELECT SUM(amount) as total FROM savings");
$stats['total_savings'] = $result->fetch_assoc()['total'] ?? 0;

// Active Loans
$result = $conn->query("SELECT COUNT(*) as total FROM loans WHERE status = 'active'");
$stats['active_loans'] = $result->fetch_assoc()['total'];

// Recent Transactions (last 7 days)
$transactions = $conn->query("
    SELECT t.id, m.full_name, t.amount, t.transaction_date, t.transaction_type 
    FROM transactions t
    JOIN memberz m ON t.member_id = m.member_no
    ORDER BY t.transaction_date DESC 
    LIMIT 10
");

// ==================== HTML DASHBOARD ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Dashboard</title>
    
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
                                        <h2 class="mb-0"><?= number_format($stats['total_members']) ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <a href="memberslist.php" class="text-decoration-none">
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
                                        <h2 class="mb-0">UGX <?= number_format($stats['total_savings'], 2) ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <a href="savings/transactions.php" class="text-decoration-none">
                                    View transactions <i class="fas fa-arrow-right ms-1"></i>
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
                                        <h2 class="mb-0"><?= number_format($stats['active_loans']) ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <a href="loans/loanslist.php" class="text-decoration-none">
                                    Manage loans <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-exchange-alt me-2"></i>Recent Transactions
                            </h5>
                            <a href="savings/transactions.php" class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body recent-transactions">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transaction = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $transaction['id'] ?></td>
                                        <td><?= htmlspecialchars($transaction['name']) ?></td>
                                        <td class="<?= $transaction['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger' ?>">
                                            UGX <?= number_format($transaction['amount'], 2) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $transaction['transaction_type'] === 'deposit' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($transaction['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y g:i a', strtotime($transaction['transaction_date'])) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
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
                        <a href="loans/apply.php" class="btn btn-warning w-100 py-3">
                            <i class="fas fa-hand-holding-usd me-2"></i>Process Loan
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js for future analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Dashboard Scripts -->
    <script>
        // Simple chart example (can be expanded)
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('savingsChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Total Savings',
                            data: [12000, 19000, 3000, 5000, 2000, 30000],
                            borderColor: '#1cc88a',
                            backgroundColor: 'rgba(28, 200, 138, 0.1)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>