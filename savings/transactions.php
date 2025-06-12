<?php
require_once __DIR__ . '/../config.php';      // For $pdo, BASE_URL, APP_NAME, sanitize()
require_once __DIR__ . '/../helpers/auth.php';

require_login(); // Redirects if not logged in

// Only allow access for Core Admins and Administrators
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";

    // Redirect based on role
    if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header("Location: " . BASE_URL . "members/my_savings.php");
    } else {
        header("Location: " . BASE_URL . "landing.php");
    }
    exit;
}

// Page content for Core Admins and Administrators continues below...

// Initialize variables
$transactions = [];
$filters = [
    'type' => $_GET['type'] ?? '',
    'member_id' => $_GET['member_id'] ?? '',
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d')
];

try {
    // Build base query
    $query = "
        SELECT 
            t.id,
            t.transaction_date,
            t.amount,
            t.transaction_type,
            t.reference,
            m.full_name,
            m.member_no,
            CASE 
                WHEN t.transaction_type = 'loan' THEN l.loan_number
                ELSE NULL
            END AS loan_ref
        FROM transactions t
        JOIN memberz m ON t.member_id = m.id
        LEFT JOIN loans l ON t.loan_id = l.id
        WHERE 1=1
    ";

    $params = [];

    // Apply filters
    if (!empty($filters['type'])) {
        $query .= " AND t.transaction_type = :type";
        $params[':type'] = $filters['type'];
    }

    if (!empty($filters['member_id'])) {
        $query .= " AND t.member_id = :member_id";
        $params[':member_id'] = $filters['member_id'];
    }

    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $query .= " AND DATE(t.transaction_date) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $filters['start_date'];
        $params[':end_date'] = $filters['end_date'];
    }

    $query .= " ORDER BY t.transaction_date DESC LIMIT 500";

    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Get members for dropdown (use ID!)
    $memberStmt = $pdo->query("SELECT id, member_no, full_name FROM memberz ORDER BY full_name");
    $members = $memberStmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    error_log("Transactions Error: " . $e->getMessage());
}
?>

<!-- HTML starts here -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Transaction Records</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .transaction-card { border-left: 4px solid; }
        .transaction-deposit { border-left-color: #28a745; }
        .transaction-withdrawal { border-left-color: #dc3545; }
        .transaction-loan { border-left-color: #ffc107; }
        .badge-deposit { background-color: #28a745; }
        .badge-withdrawal { background-color: #dc3545; }
        .badge-loan { background-color: #ffc107; color: #212529; }
        .table-responsive { max-height: 70vh; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../partials/navbar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center border-bottom mb-4">
                <h2><i class="fas fa-exchange-alt me-2"></i>Transaction Records</h2>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newTransactionModal">
                    <i class="fas fa-plus me-1"></i> New Transaction
                </button>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Transaction Type</label>
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <option value="deposit" <?= $filters['type'] === 'deposit' ? 'selected' : '' ?>>Deposits</option>
                                <option value="withdrawal" <?= $filters['type'] === 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                                <option value="loan" <?= $filters['type'] === 'loan' ? 'selected' : '' ?>>Loan Payments</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Member</label>
                            <select name="member_id" class="form-select">
                                <option value="">All Members</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= $member['id'] ?>" <?= $filters['member_id'] == $member['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($member['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filters['start_date']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filters['end_date']) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card transaction-card transaction-deposit">
                        <div class="card-body">
                            <h6 class="text-muted">Total Deposits</h6>
                            <h3>UGX <?= number_format(array_sum(array_map(fn($t) => $t['transaction_type'] === 'deposit' ? $t['amount'] : 0, $transactions)), 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card transaction-card transaction-withdrawal">
                        <div class="card-body">
                            <h6 class="text-muted">Total Withdrawals</h6>
                            <h3>UGX <?= number_format(array_sum(array_map(fn($t) => $t['transaction_type'] === 'withdrawal' ? $t['amount'] : 0, $transactions)), 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card transaction-card transaction-loan">
                        <div class="card-body">
                            <h6 class="text-muted">Total Loan Payments</h6>
                            <h3>UGX <?= number_format(array_sum(array_map(fn($t) => $t['transaction_type'] === 'loan' ? $t['amount'] : 0, $transactions)), 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-list me-2"></i>Transaction History</h5>
                    <span class="badge bg-primary"><?= count($transactions) ?> records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date/Time</th>
                                    <th>Member</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th class="text-end">Amount (UGX)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No transactions found matching your criteria</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $i => $tx): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($tx['transaction_date'])) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>members/view.php?id=<?= $tx['member_no'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($tx['full_name']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill badge-<?= $tx['transaction_type'] ?>">
                                                    <?= ucfirst($tx['transaction_type']) ?>
                                                    <?= $tx['transaction_type'] === 'loan' && !empty($tx['loan_ref']) ? ' #' . $tx['loan_ref'] : '' ?>
                                                </span>
                                            </td>
                                            <td><?= !empty($tx['reference']) ? htmlspecialchars($tx['reference']) : 'N/A' ?></td>
                                            <td class="text-end <?= $tx['transaction_type'] === 'withdrawal' ? 'text-danger' : 'text-success' ?>">
                                                <?= $tx['transaction_type'] === 'withdrawal' ? '-' : '+' ?>
                                                <?= number_format($tx['amount'], 2) ?>
                                            </td>
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

<!-- Modal Placeholder -->
<div class="modal fade" id="newTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-center py-4">Transaction form will appear here</p>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const startDate = new Date(document.querySelector('[name="start_date"]').value);
        const endDate = new Date(document.querySelector('[name="end_date"]').value);

        if (startDate > endDate) {
            alert('End date must be after start date');
            e.preventDefault();
        }
    });
</script>
</body>
</html>
