<?php
session_start();

if (!isset($_SESSION['admin']['id'])) {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once '../helpers/auth.php';

// Check if loan ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid loan ID";
    header('Location: loanslist.php');
    exit;
}

$loan_id = intval($_GET['id']);

// Fetch loan details with member information
try {
    $stmt = $pdo->prepare("SELECT l.*, 
                          m.member_no, m.full_name, m.phone, m.email, m.district, m.village,
                          u.username as processed_by_name,
                          COALESCE(SUM(lr.amount), 0) as amount_paid,
                          (l.amount - COALESCE(SUM(lr.amount), 0)) as balance
                          FROM loans l
                          JOIN memberz m ON l.member_id = m.id
                          LEFT JOIN users u ON l.processed_by = u.user_id
                          LEFT JOIN loan_repayments lr ON l.id = lr.loan_id
                          WHERE l.id = :loan_id");
    $stmt->execute([':loan_id' => $loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        $_SESSION['error'] = "Loan not found";
        header('Location: loanslist.php');
        exit;
    }

    // Fetch repayment schedule
    $stmt = $pdo->prepare("SELECT * FROM loan_repayments 
                          WHERE loan_id = :loan_id 
                          ORDER BY due_date ASC");
    $stmt->execute([':loan_id' => $loan_id]);
    $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch payment history
    $stmt = $pdo->prepare("SELECT p.*, u.username as received_by 
                          FROM payments p
                          LEFT JOIN users u ON p.received_by = u.user_id
                          WHERE p.loan_id = :loan_id
                          ORDER BY payment_date DESC");
    $stmt->execute([':loan_id' => $loan_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: loanslist.php');
    exit;
}

// Calculate loan summary
$total_interest = $loan['amount'] * ($loan['interest_rate'] / 100) * ($loan['term_months'] / 12);
$total_payable = $loan['amount'] + $total_interest;
$completion_percentage = ($loan['amount_paid'] / $total_payable) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Details - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loan-header {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-completed { background-color: #d1ecf1; color: #0c5460; }
        .progress { height: 25px; }
        .repayment-card { border-left: 4px solid #6f42c1; }
        .payment-card { border-left: 4px solid #28a745; }
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Loan Details
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="loanslist.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Loans
                        </a>
                        <a href="editloan.php?id=<?= $loan_id ?>" class="btn btn-sm btn-primary me-2">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                        <?php if ($loan['status'] === 'approved'): ?>
                            <a href="addrepayment.php?loan_id=<?= $loan_id ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-money-bill-wave me-1"></i> Record Payment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <!-- Loan Summary Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Loan Summary
                            <span class="status-badge status-<?= $loan['status'] ?> float-end">
                                <?= ucfirst($loan['status']) ?>
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6>Loan Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="40%">Loan Number</th>
                                            <td><?= htmlspecialchars($loan['loan_number']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Member</th>
                                            <td>
                                                <?= htmlspecialchars($loan['full_name']) ?> 
                                                (<?= htmlspecialchars($loan['member_no']) ?>)
                                                <br>
                                                <a href="tel:<?= htmlspecialchars($loan['phone']) ?>">
                                                    <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($loan['phone']) ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Application Date</th>
                                           
<?= !empty($loan['application_date']) && $loan['application_date'] !== '0000-00-00' 
    ? date('d M Y', strtotime($loan['application_date'])) 
    : '<span class="text-muted">Not submitted</span>' ?>
                                        </tr>
                                        <tr>
                                            <th>Processed By</th>
                                            <td><?= $loan['processed_by_name'] ?? 'Not processed' ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6>Financial Details</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="40%">Principal Amount</th>
                                            <td>UGX <?= number_format($loan['amount'], 2) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Interest Rate</th>
                                            <td><?= $loan['interest_rate'] ?>%</td>
                                        </tr>
                                        <tr>
                                            <th>Loan Term</th>
                                            <td><?= $loan['term_months'] ?> months</td>
                                        </tr>
                                        <tr>
                                            <th>Total Interest</th>
                                            <td>UGX <?= number_format($total_interest, 2) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Total Payable</th>
                                            <td>UGX <?= number_format($total_payable, 2) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-3">
                            <h6>Repayment Progress</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar progress-bar-striped bg-success" 
                                     role="progressbar" 
                                     style="width: <?= $completion_percentage ?>%" 
                                     aria-valuenow="<?= $completion_percentage ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?= round($completion_percentage, 1) ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Paid: UGX <?= number_format($loan['amount_paid'], 2) ?></small>
                                <small>Balance: UGX <?= number_format($loan['balance'], 2) ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Repayment Schedule -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Repayment Schedule
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Paid Date</th>
                                        <th>Amount Paid</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($repayments)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No repayment schedule found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $running_balance = $total_payable;
                                        foreach ($repayments as $repayment): 
                                            $paid_amount = $repayment['amount_paid'] ?? 0;
                                            $balance = $repayment['amount'] - $paid_amount;
                                            $running_balance -= $paid_amount;
                                        ?>
                                            <tr class="<?= $repayment['status'] === 'paid' ? 'table-success' : ($repayment['status'] === 'overdue' ? 'table-danger' : '') ?>">
                                                <td><?= $repayment['id'] ?></td>
                                                <td><?= date('d M Y', strtotime($repayment['due_date'])) ?></td>
                                                <td>UGX <?= number_format($repayment['amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $repayment['status'] === 'paid' ? 'success' : 
                                                        ($repayment['status'] === 'overdue' ? 'danger' : 'warning') 
                                                    ?>">
                                                        <?= ucfirst($repayment['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= $repayment['payment_date'] ? date('d M Y', strtotime($repayment['payment_date'])) : '--' ?>
                                                </td>
                                                <td>
                                                    <?= $repayment['amount_paid'] ? 'UGX ' . number_format($repayment['amount_paid'], 2) : '--' ?>
                                                </td>
                                                <td>
                                                    UGX <?= number_format($balance, 2) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Payment History
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Received By</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No payment history found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?= $payment['id'] ?></td>
                                                <td><?= date('d M Y', strtotime($payment['payment_date'])) ?></td>
                                                <td>UGX <?= number_format($payment['amount'], 2) ?></td>
                                                <td><?= ucfirst($payment['payment_method']) ?></td>
                                                <td><?= $payment['reference_number'] ?? '--' ?></td>
                                                <td><?= $payment['received_by'] ?? 'System' ?></td>
                                                <td><?= $payment['notes'] ? htmlspecialchars($payment['notes']) : '--' ?></td>
                                                <td>
                                                    <a href="editpayment.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="#" onclick="confirmDeletePayment(<?= $payment['id'] ?>)" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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

    <!-- Delete Payment Modal -->
    <div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Payment Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this payment record?</p>
                    <p class="fw-bold">This action cannot be undone and will affect the loan balance!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeletePayment" class="btn btn-danger">Delete Payment</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment deletion confirmation
        function confirmDeletePayment(paymentId) {
            document.getElementById('confirmDeletePayment').href = `deletepayment.php?id=${paymentId}&loan_id=<?= $loan_id ?>`;
            const modal = new bootstrap.Modal(document.getElementById('deletePaymentModal'));
            modal.show();
        }
    </script>
</body>
</html>