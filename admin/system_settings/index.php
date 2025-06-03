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

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add your custom JS here if needed -->
</body>
</html>
