<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

if (!isset($_SESSION['user']['member_id']) || empty($_SESSION['user']['member_id'])) {
    $_SESSION['error_message'] = "No member account is linked to your user profile. Please contact an administrator.";
    header('Location: ' . (BASE_URL ?? '/') . 'index.php');
    exit();
}

if (!has_role('Member')) {
    $_SESSION['error_message'] = "You do not have permission to view this page. This area is for members only.";
    header('Location: ' . (BASE_URL ?? '/') . 'index.php');
    exit();
}

$logged_in_member_id = $_SESSION['user']['member_id'];
$page_title = "My Savings Performance";

$member_total_savings = 0;
$average_savings = 0;
$percentile_rank = 0;
$all_members_savings_data = []; // For chart

try {
    // 1. Logged-in Member's Total Savings
    $stmt_my_savings = $pdo->prepare("SELECT SUM(amount) as total_savings FROM savings WHERE member_id = :logged_in_member_id");
    $stmt_my_savings->execute(['logged_in_member_id' => $logged_in_member_id]);
    $my_savings_result = $stmt_my_savings->fetch(PDO::FETCH_ASSOC);
    $member_total_savings = $my_savings_result['total_savings'] ?? 0;

    // 2. All Members' Total Savings (for comparison)
    $stmt_all_savings = $pdo->query("
        SELECT m.id as member_id, COALESCE(SUM(s.amount), 0) as total_savings
        FROM memberz m
        LEFT JOIN savings s ON m.id = s.member_id
        GROUP BY m.id
    ");
    $all_members_savings_list = $stmt_all_savings->fetchAll(PDO::FETCH_ASSOC);

    $total_members = count($all_members_savings_list);
    $sum_of_all_savings = 0;
    $members_below = 0;

    if ($total_members > 0) {
        foreach ($all_members_savings_list as $member_data) {
            $sum_of_all_savings += $member_data['total_savings'];
            if ($member_data['member_id'] != $logged_in_member_id && $member_data['total_savings'] < $member_total_savings) {
                $members_below++;
            }
            // Storing for potential chart, anonymized (though member_id is there, not displayed)
            $all_members_savings_data[] = $member_data['total_savings'];
        }

        $average_savings = $sum_of_all_savings / $total_members;

        if ($total_members == 1 && $member_total_savings > 0) { // Special case if only one member and they have savings
             $percentile_rank = 100; // They are above 0% (no one else) and effectively at the top.
        } elseif ($total_members > 1) {
            // To avoid issues if multiple members have the exact same savings as the current user,
            // a common approach is (members_below + 0.5 * members_equal) / total_members * 100
            // For simplicity here, using members_below.
            $percentile_rank = ($members_below / ($total_members -1) ) * 100;
            // ($total_members -1) because we compare against *other* members.
            // if $total_members -1 is 0 (i.e. only one member), this will cause division by zero.
            // Handled by the $total_members == 1 check earlier.
        } else if ($total_members > 0 && $member_total_savings == 0 && $members_below == 0){
             $percentile_rank = 0; // if they have 0 and everyone else has 0 or more.
        }
         $percentile_rank = min(100, max(0, $percentile_rank)); // Cap at 0-100

    }


} catch (PDOException $e) {
    error_log("Savings Performance PDOError for member_id " . $logged_in_member_id . ": " . $e->getMessage());
    $_SESSION['error_message'] = "Could not retrieve savings performance data due to a database error.";
    // Allow page to render but show error.
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars($settings['site_name'] ?? APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .performance-card { margin-bottom: 1.5rem; }
        .chart-container { max-width: 600px; margin: auto; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-line me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <?php if (!isset($_SESSION['error_message'])): // Only show stats if no major DB error ?>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card text-center performance-card">
                            <div class="card-body">
                                <h5 class="card-title">Your Total Savings</h5>
                                <p class="card-text fs-2 fw-bold">
                                    <?php echo htmlspecialchars($settings['currency_symbol'] ?? 'UGX'); ?> <?php echo number_format($member_total_savings, 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center performance-card">
                            <div class="card-body">
                                <h5 class="card-title">Average Member Savings</h5>
                                <p class="card-text fs-2 fw-bold">
                                    <?php echo htmlspecialchars($settings['currency_symbol'] ?? 'UGX'); ?> <?php echo number_format($average_savings, 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center performance-card">
                            <div class="card-body">
                                <h5 class="card-title">Your Percentile Rank</h5>
                                <p class="card-text fs-2 fw-bold">
                                    <?php echo number_format($percentile_rank, 1); ?>%
                                </p>
                                <small class="text-muted">You've saved more than <?php echo number_format($percentile_rank, 1); ?>% of other members.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h3 class="text-center mb-3">Savings Comparison Chart</h3>
                    <div class="chart-container">
                        <canvas id="savingsComparisonChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!isset($_SESSION['error_message']) && $total_members > 0): // Only init chart if data is available ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('savingsComparisonChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Your Savings', 'Average Savings'],
                    datasets: [{
                        label: 'Amount (<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'UGX'); ?>)',
                        data: [<?php echo $member_total_savings; ?>, <?php echo $average_savings; ?>],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.6)', // Blue for Your Savings
                            'rgba(255, 159, 64, 0.6)'  // Orange for Average Savings
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value, index, values) {
                                    return '<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'UGX'); ?> ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // Or true if you prefer
                        },
                        tooltip: {
                             callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += '<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'UGX'); ?> ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
