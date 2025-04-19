<?php
session_start();

// Verify the session file exists and is writable
if (!file_exists(session_save_path()) || !is_writable(session_save_path())) {
    die('Session directory not writable: ' . session_save_path());
}

// Standardize session check
if (!isset($_SESSION['admin']['id'])) {
    header("Location: /savingssystem/auth/login.php");
    exit;
}

// Database connection
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
$pdo = $conn;

// Debug session
error_log("Index session data: " . print_r($_SESSION, true));

// Check user role
if ($_SESSION['admin']['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: /savingssystem/auth/login.php');
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
               <!-- Chat Section -->
<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Admin Chat</h5>
    </div>
    <div class="card-body" style="max-height: 300px; overflow-y: auto;" id="chat-messages">
        <!-- Sample messages -->
        <div class="mb-2"><strong>You:</strong> Welcome to the system!</div>
        <div class="mb-2"><strong>System:</strong> Hello Admin! How can I assist you?</div>
    </div>
    <div class="card-footer bg-light">
        <form id="chat-form" class="d-flex">
            <input type="text" id="chat-input" class="form-control me-2" placeholder="Type a message..." required>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </div>
</div>


                <!-- Chart Section -->
                <div class="row mt-5">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <strong>Monthly Savings Trend</strong>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlySavingsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <strong>Top Saving Members</strong>
                            </div>
                            <div class="card-body">
                                <canvas id="topMembersChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart Script -->
    <script>
        const monthlySavingsCtx = document.getElementById('monthlySavingsChart').getContext('2d');
        const monthlySavingsChart = new Chart(monthlySavingsCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], // Replace with dynamic data
                datasets: [{
                    label: 'UGX Saved',
                    data: [500000, 700000, 300000, 900000, 600000, 800000], // Replace with real data
                    backgroundColor: '#1cc88a'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Savings per Month' }
                }
            }
        });

        const topMembersCtx = document.getElementById('topMembersChart').getContext('2d');
        const topMembersChart = new Chart(topMembersCtx, {
            type: 'pie',
            data: {
                labels: ['John', 'Mary', 'Alex', 'Jane', 'Paul'], // Replace with real member names
                datasets: [{
                    label: 'Total Savings',
                    data: [1200000, 950000, 870000, 820000, 770000], // Replace with real totals
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e',
                        '#e74a3b'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Top Saving Members'
                    }
                }
            }
        });
    </script>
</body>
</html>
