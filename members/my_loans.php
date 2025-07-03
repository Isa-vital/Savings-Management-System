<?php
session_start();

// Check authentication
if (!isset($_SESSION['user']['id'])) {
    header("Location: /auth/login.php");
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/auth.php';

$current_member_id = $_SESSION['user']['member_id'];
$page_title = "My Loans";
$currency_symbol = defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : '$';

// Fetch member's loans
try {
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.loan_number,
            l.amount,
            l.total_repayment,
            l.status,
            l.application_date,
            COALESCE(SUM(lr.amount_paid), 0) as amount_paid
        FROM loans l
        LEFT JOIN loan_repayment lr ON l.id = lr.loan_id AND lr.status = 'paid'
        WHERE l.member_id = ?
        GROUP BY l.id
        ORDER BY l.application_date DESC
    ");
    $stmt->execute([$current_member_id]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate outstanding balances
    foreach ($loans as &$loan) {
        $loan['outstanding'] = $loan['total_repayment'] - $loan['amount_paid'];
        $loan['can_pay'] = ($loan['status'] == 'approved' && $loan['outstanding'] > 0);
    }
    unset($loan); // Break reference
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load loan data. Please try again later.";
    $loans = [];
}

// Status badge styling
function getStatusBadge($status) {
    $classes = [
        'pending' => 'bg-secondary',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'completed' => 'bg-info'
    ];
    return '<span class="badge ' . ($classes[$status] ?? 'bg-warning') . '">' . ucfirst($status) . '</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../partials/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-file-invoice-dollar me-2"></i><?= htmlspecialchars($page_title) ?></h1>
            <a href="<?= BASE_URL ?>loans/apply.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Apply for Loan
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($loans)): ?>
            <div class="alert alert-info">
                You don't have any loans yet. <a href="<?= BASE_URL ?>loans/apply.php">Apply for a loan</a> to get started.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Loan #</th>
                            <th>Amount</th>
                            <th>Total Repayment</th>
                            <th>Amount Paid</th>
                            <th>Outstanding</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['loan_number']) ?></td>
                            <td><?= htmlspecialchars($currency_symbol . number_format($loan['amount'], 2)) ?></td>
                            <td><?= htmlspecialchars($currency_symbol . number_format($loan['total_repayment'], 2)) ?></td>
                            <td><?= htmlspecialchars($currency_symbol . number_format($loan['amount_paid'], 2)) ?></td>
                            <td><?= htmlspecialchars($currency_symbol . number_format($loan['outstanding'], 2)) ?></td>
                            <td><?= getStatusBadge($loan['status']) ?></td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($loan['application_date']))) ?></td>
                            <td>
                                <?php if ($loan['can_pay']): ?>
                                    <a href="<?= BASE_URL ?>payments/make_payment.php?loan_id=<?= $loan['id'] ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="fas fa-money-bill-wave me-1"></i> Make Payment
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>loans/details.php?id=<?= $loan['id'] ?>" 
                                   class="btn btn-sm btn-info">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>