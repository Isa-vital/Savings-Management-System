<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if loan ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: loanslist.php?error=invalid_id");
    exit();
}

$loanId = (int)$_GET['id'];

try {
    // Get loan details
    $stmt = $conn->prepare("
        SELECT l.*, 
               m.full_name, m.phone, m.email,
               a.id as processed_by_name
        FROM loans l
        JOIN memberz m ON l.member_id = m.id
        LEFT JOIN admins a ON l.processed_by = a.id
        WHERE l.id = :id
    ");
    $stmt->bindParam(':id', $loanId, PDO::PARAM_INT);
    $stmt->execute();
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        header("Location: loanslist.php?error=loan_not_found");
        exit();
    }

    // Get repayment history
    $stmt = $conn->prepare("
        SELECT * FROM loan_repayments 
        WHERE loan_id = :loan_id 
        ORDER BY payment_date DESC
    ");
    $stmt->bindParam(':loan_id', $loanId, PDO::PARAM_INT);
    $stmt->execute();
    $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Loan #<?= $loanId ?> | SACCO Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../partials/navbar.php'; ?>
    

    <?php include '../partials/sidebar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Loan Details <?= $loanId ?></h2>
                    <a href="loanslist.php" class="btn btn-secondary">Back to Loans</a>
                </div>

                <!-- Loan Details Card -->
               <!-- Loan Information Section -->
<div class="card-body">
    <div class="row">
        <div class="col-md-6">
            <p><strong>Member:</strong> <?= htmlspecialchars($loan['full_name'] ?? 'N/A') ?></p>
            <p><strong>Phone:</strong> <?= formatUgandanPhone($loan['phone'] ?? '') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($loan['email'] ?? 'N/A') ?></p>
            <p><strong>Loan Type:</strong> <?= htmlspecialchars($loan['loan_type'] ?? 'N/A') ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Amount:</strong> <?= number_format($loan['amount'] ?? 0, 2) ?> UGX</p>
            <p><strong>Interest Rate:</strong> <?= $loan['interest_rate'] ?? '0' ?>%</p>
            <p><strong>Status:</strong> <?= $loan['status'] ?? 'Unknown' ?></p>
            <p><strong>Application Date:</strong> <?= formatDate($loan['application_date'] ?? null) ?></p>
        </div>
    </div>
</div>

<!--repayment history--->
<div class="card-body">
    <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Repayment History</h5>
        <span class="badge bg-light text-dark">
            Total Paid: <?= number_format($loan['amount_paid'] ?? 0, 2) ?> UGX
        </span>
    </div>
    
    <?php if (!empty($repayments)): ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Received By</th>
                <th>Receipt No.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repayments as $payment): ?>
            <tr>
                <td><?= formatDate($payment['payment_date'] ?? null) ?></td>
                <td><?= number_format($payment['amount'] ?? 0, 2) ?> UGX</td>
                <td><?= htmlspecialchars($payment['payment_method'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($payment['received_by'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-info">No repayment history found.</div>
    <?php endif; ?>
          <!-- Action Buttons -->
          <div class="mt-4 d-flex justify-content-end gap-2">
                    <?php if ($loan['status'] == 'Pending'): ?>
                        <a href="approveloan.php?id=<?= $loanId ?>" class="btn btn-success">Approve Loan</a>
                    <?php endif; ?>
                    <?php if ($loan['status'] == 'Active' && $loan['amount'] < $loan['amount']): ?>
                        <a href="addrepayment.php?loan_id=<?= $loanId ?>" class="btn btn-primary">Add Repayment</a>
                    <?php endif; ?>
                    <a href="editloan.php?id=<?= $loanId ?>" class="btn btn-warning">Edit Details</a>
                    <a href="printloan.php?id=<?= $loanId ?>" target="_blank" class="btn btn-secondary">Print Details</a>
                </div>
            </div>
        </div>
    </div>
</div>

          
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>