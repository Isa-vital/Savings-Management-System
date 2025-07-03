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

// ==================== DASHBOARD STATISTICS ====================
$stats = [];
$transactions = [];


if (!isset($monthly_savings)) $monthly_savings = ['labels'=>[], 'data'=>[]];
if (!isset($top_members)) $top_members = ['labels'=>[], 'data'=>[]];

try {
    // Total Members
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM memberz");
    $stats['total_members'] = $stmt->fetchColumn();

    // Total Savings
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM savings");
    $stats['total_savings'] = $stmt->fetchColumn();

    // Active Loans
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'approved'");
    $stats['active_loans'] = $stmt->fetchColumn();

    // Recent Transactions (last 10)
    $stmt = $pdo->query("
     SELECT s.member_id, m.full_name, s.amount, s.date
    FROM savings s
    JOIN memberz m ON s.member_id = m.id
    ORDER BY s.date DESC
    LIMIT 10
");
$transactions = $stmt->fetchAll();
    // Top 5 Saving Members
    $top_members = ['labels' => [], 'data' => []];
    $stmt = $pdo->query("
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $top_members['labels'][] = $row['full_name'];
        $top_members['data'][] = (float)$row['total'];
    }

    // Monthly Savings (last 6 months)
    $monthly_savings = ['labels' => [], 'data' => []];
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(date, '%b %Y') as month, SUM(amount) as total
        FROM savings
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY MIN(date) ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthly_savings['labels'][] = $row['month'];
        $monthly_savings['data'][] = (float)$row['total'];
    }

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



                <!-- Chart Section -->
                <!-- ...inside your <main> ... -->
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

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart Script -->
<script>
    // Pass PHP arrays to JS
    const monthlySavingsData = <?= json_encode($monthly_savings) ?>;
    const topMembersData = <?= json_encode($top_members) ?>;
    // Debugging output for JS arrays
    console.log("Monthly Savings Data:", monthlySavingsData);
    console.log("Top Members Data:", topMembersData);

        // Monthly Savings Bar Chart
        const monthlySavingsCtx = document.getElementById('monthlySavingsChart').getContext('2d');
        new Chart(monthlySavingsCtx, {
            type: 'bar',
            data: {
                labels: monthlySavingsData.labels || [],
                datasets: [{
                    label: 'UGX Saved',
                    data: monthlySavingsData.data || [],
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

        // Top Members Pie Chart
        const topMembersCtx = document.getElementById('topMembersChart').getContext('2d');
        new Chart(topMembersCtx, {
            type: 'pie',
            data: {
                labels: topMembersData.labels || [],
                datasets: [{
                    label: 'Total Savings',
                    data: topMembersData.data || [],
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
