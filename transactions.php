<?php
// Initialize session and check authentication
session_start();
if (!isset($_SESSION['admin']['id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';

// Initialize variables
$transactions = [];
$members = [];
$filters = [
    'type' => $_GET['type'] ?? '',
    'member_id' => $_GET['member_id'] ?? '',
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d')
];

try {
    // Get members list
    $stmt = $pdo->query("SELECT member_no, full_name FROM memberz ORDER BY full_name");
    $members = $stmt->fetchAll();

    // Build transactions query
    $query = "SELECT t.*, m.full_name FROM transactions t 
              JOIN memberz m ON t.member_id = m.member_no 
              WHERE 1=1";
    $params = [];

    if (!empty($filters['type'])) {
        $query .= " AND t.transaction_type = ?";
        $params[] = $filters['type'];
    }

    if (!empty($filters['member_id'])) {
        $query .= " AND t.member_id = ?";
        $params[] = $filters['member_id'];
    }

    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(t.transaction_date) >= ?";
        $params[] = $filters['start_date'];
    }

    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(t.transaction_date) <= ?";
        $params[] = $filters['end_date'];
    }

    $query .= " ORDER BY t.transaction_date DESC LIMIT 200";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Records</title>
    
    <!-- Local Bootstrap CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    
    <style>
        .badge-deposit { background-color: #28a745; }
        .badge-withdrawal { background-color: #dc3545; }
        .badge-loan { background-color: #ffc107; color: #212529; }
        .table-container { max-height: 70vh; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'partials/navbar.php'; ?>
        
        <div class="row">
            <?php include 'partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <h2 class="mb-4">Transaction Records</h2>
                
                <!-- Filter Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="deposit" <?= $filters['type'] === 'deposit' ? 'selected' : '' ?>>Saving Deposits</option>
                                    <option value="dividend" <?= $filters['type'] === 'dividend' ? 'selected' : '' ?>>Dividend Payments</option>
                                    <option value="loan_disbursement" <?= $filters['type'] === 'loan_disbursement' ? 'selected' : '' ?>>Loan Disbursements</option>
                                    <option value="loan_repayment" <?= $filters['type'] === 'loan_repayment' ? 'selected' : '' ?>>Loan Repayments</option>
                                    <option value="savings_interest" <?= $filters['type'] === 'savings_interest' ? 'selected' : '' ?>>Savings Interest</option>
                                    <option value="fine_payment" <?= $filters['type'] === 'fine_payment' ? 'selected' : '' ?>>Fine Payments</option>
                                    <option value="withdrawal" <?= $filters['type'] === 'withdrawal' ? 'selected' : '' ?>>Savings Withdrawals</option>
                                    <option value="other_transaction" <?= $filters['type'] === 'other_transaction' ? 'selected' : '' ?>>Other Transactions</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="member_id" class="form-select">
                                    <option value="">All Members</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?= $m['member_no'] ?>" <?= $filters['member_id'] == $m['member_no'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="start_date" class="form-control" value="<?= $filters['start_date'] ?>">
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="end_date" class="form-control" value="<?= $filters['end_date'] ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="transactions.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0">Transactions</h5>
                            <span class="badge bg-primary">
                                <?= count($transactions) ?> records
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-container">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">No transactions found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $i => $tx): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= date('M d, Y', strtotime($tx['transaction_date'])) ?></td>
                                                <td><?= htmlspecialchars($tx['full_name']) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $tx['transaction_type'] ?>">
                                                        <?= ucfirst($tx['transaction_type']) ?>
                                                    </span>
                                                </td>
                                                <td class="<?= $tx['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger' ?>">
                                                    <?= $tx['transaction_type'] === 'deposit' ? '+' : '-' ?>
                                                    <?= number_format($tx['amount'], 2) ?>
                                                </td>
                                                <td><?= $tx['reference'] ?? 'N/A' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Local Bootstrap JS -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const start = document.querySelector('[name="start_date"]').value;
            const end = document.querySelector('[name="end_date"]').value;
            
            if (start && end && start > end) {
                alert('End date must be after start date');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>