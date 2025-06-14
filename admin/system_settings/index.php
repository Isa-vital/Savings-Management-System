<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_core_admin();

// Define expected settings keys
$expected_settings = [
    'site_name' => ['label' => 'Site Name', 'type' => 'text', 'default' => 'MySacco App'],
    'interest_rate' => ['label' => 'Interest Rate (%)', 'type' => 'number', 'step' => '0.01', 'default' => '10'],
    'loan_processing_fee' => ['label' => 'Loan Processing Fee', 'type' => 'number', 'step' => '0.01', 'default' => '50'],
    'notification_email_from' => ['label' => 'Notification "From" Email', 'type' => 'email', 'default' => 'noreply@example.com'],
    'currency_symbol' => ['label' => 'Currency Symbol', 'type' => 'text', 'default' => 'UGX']
];
$settings = [];

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Log error or handle if settings table doesn't exist yet, though migration should create it
    $_SESSION['error_message'] = "Error fetching system settings: " . $e->getMessage();
    // For now, we'll proceed with defaults if table is missing or query fails
}

// Merge fetched settings with expected settings to ensure all keys exist for the form
foreach ($expected_settings as $key => $details) {
    if (!isset($settings[$key])) {
        $settings[$key] = $details['default']; // Use default if not in DB
    }
}

$page_title = "System Settings";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars($settings['site_name'] ?? APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add your custom CSS here if needed -->
</head>
<body>
    <?php include __DIR__ . '/../../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <form action="update_settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">

                    <?php foreach ($expected_settings as $key => $details): ?>
                        <div class="mb-3">
                            <label for="<?php echo $key; ?>" class="form-label"><?php echo htmlspecialchars($details['label']); ?></label>
                            <input
                                type="<?php echo htmlspecialchars($details['type']); ?>"
                                class="form-control"
                                id="<?php echo $key; ?>"
                                name="<?php echo $key; ?>"
                                value="<?php echo htmlspecialchars($settings[$key] ?? $details['default']); ?>"
                                <?php if ($details['type'] === 'number' && isset($details['step'])): ?>
                                    step="<?php echo htmlspecialchars($details['step']); ?>"
                                <?php endif; ?>
                                required
                            >
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>

                <?php
                // Simulate fetching current savings year status
                $current_savings_year_status = "No active savings year.";
                // Example of active year:
                // $current_savings_year_status = "Active Savings Year: 2023-2024 (Started: 2023-07-01)";

                // In a real implementation, this would be fetched from the database:
                // try {
                //     $stmt = $pdo->query("SELECT name, start_date, status FROM savings_years WHERE status = 'active' ORDER BY start_date DESC LIMIT 1");
                //     $active_year = $stmt->fetch(PDO::FETCH_ASSOC);
                //     if ($active_year) {
                //         $current_savings_year_status = "Active Savings Year: " . htmlspecialchars($active_year['name']) . " (Started: " . htmlspecialchars(date('Y-m-d', strtotime($active_year['start_date']))) . ")";
                //     } else {
                //         $stmt_latest_closed = $pdo->query("SELECT name, end_date FROM savings_years WHERE status = 'closed' ORDER BY end_date DESC LIMIT 1");
                //         $latest_closed_year = $stmt_latest_closed->fetch(PDO::FETCH_ASSOC);
                //         if ($latest_closed_year) {
                //             $current_savings_year_status = "No active savings year. Latest closed year: " . htmlspecialchars($latest_closed_year['name']) . " (Ended: " . htmlspecialchars(date('Y-m-d', strtotime($latest_closed_year['end_date']))) . ")";
                //         } else {
                //             $current_savings_year_status = "No active savings year and no past year records found.";
                //         }
                //     }
                // } catch (PDOException $e) {
                //     $current_savings_year_status = "Error fetching savings year status: " . $e->getMessage();
                //     // Log this error properly in a real application
                // }
                ?>
                <div class="alert alert-info mt-3" role="alert">
                    <strong>Current Status:</strong> <?php echo $current_savings_year_status; ?>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Savings Year Management</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Use the actions below to manage the savings year.</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Close Current Savings Year</h6>
                                        <p class="card-text small">This action will close the current active savings year, calculate interests, and prepare the system for a new savings period. Ensure all transactions for the current year are finalized.</p>
                                        <form action="close_savings_year.php" method="POST" onsubmit="return confirm('Are you sure you want to close the current savings year? This action cannot be undone easily.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                                            <button type="submit" class="btn btn-warning">Close Savings Year</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Start New Savings Year</h6>
                                        <p class="card-text small">This action will initiate a new savings year. This should typically be done after the previous year has been closed.</p>
                                        <form action="start_savings_year.php" method="POST" onsubmit="return confirm('Are you sure you want to start a new savings year?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                                            <button type="submit" class="btn btn-success">Start New Savings Year</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add your custom JS here if needed -->
</body>
</html>
