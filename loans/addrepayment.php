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
if (!isset($_GET['loan_id']) || !is_numeric($_GET['loan_id'])) {
    $_SESSION['error'] = "Invalid loan ID";
    header('Location: loanslist.php');
    exit;
}

$loan_id = intval($_GET['loan_id']);

// Fetch loan details
try {
    $stmt = $pdo->prepare("SELECT l.*, m.full_name 
                          FROM loans l
                          JOIN memberz m ON l.member_id = m.id
                          WHERE l.id = :loan_id AND l.status = 'approved'");
    $stmt->execute([':loan_id' => $loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        $_SESSION['error'] = "Loan not found or not approved";
        header('Location: loanslist.php');
        exit;
    }

    // Fetch pending repayments
    $stmt = $pdo->prepare("SELECT * FROM loan_repayments 
                          WHERE loan_id = :loan_id AND status = 'pending'
                          ORDER BY due_date ASC");
    $stmt->execute([':loan_id' => $loan_id]);
    $pending_repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: loanslist.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    
    try {
        $repayment_id = intval($_POST['repayment_id']);
        $amount = floatval($_POST['amount']);
        $payment_date = sanitize($_POST['payment_date']);
        $payment_method = sanitize($_POST['payment_method']);
        $reference_number = sanitize($_POST['reference_number']);
        $notes = sanitize($_POST['notes']);
        $admin_id = $_SESSION['admin']['id'];

        // Validate amount
        $stmt = $pdo->prepare("SELECT amount FROM loan_repayments WHERE id = :id AND loan_id = :loan_id");
        $stmt->execute([':id' => $repayment_id, ':loan_id' => $loan_id]);
        $repayment = $stmt->fetch();
        
        if (!$repayment) {
            throw new Exception("Invalid repayment record");
        }

        if ($amount <= 0 || $amount > $repayment['amount']) {
            throw new Exception("Amount must be positive and not exceed the due amount");
        }

        // Record payment
        $stmt = $pdo->prepare("INSERT INTO payments 
                              (loan_id, repayment_id, amount, payment_date, 
                               payment_method, reference_number, received_by, notes)
                              VALUES 
                              (:loan_id, :repayment_id, :amount, :payment_date, 
                               :payment_method, :reference_number, :received_by, :notes)");
        $stmt->execute([
            ':loan_id' => $loan_id,
            ':repayment_id' => $repayment_id,
            ':amount' => $amount,
            ':payment_date' => $payment_date,
            ':payment_method' => $payment_method,
            ':reference_number' => $reference_number,
            ':received_by' => $admin_id,
            ':notes' => $notes
        ]);

        // Update repayment status
        $stmt = $pdo->prepare("UPDATE loan_repayments 
                              SET status = 'paid', 
                                  payment_date = :payment_date,
                                  amount_paid = :amount
                              WHERE id = :id");
        $stmt->execute([
            ':payment_date' => $payment_date,
            ':amount' => $amount,
            ':id' => $repayment_id
        ]);

        $pdo->commit();
        $_SESSION['success'] = "Payment recorded successfully";
        header("Location: viewloan.php?id=$loan_id");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error recording payment: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-card {
            border-left: 4px solid #28a745;
        }
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
                        <i class="fas fa-money-bill-wave me-2"></i>Record Loan Payment
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="viewloan.php?id=<?= $loan_id ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Loan
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="card payment-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Loan Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Member:</strong> <?= htmlspecialchars($loan['full_name']) ?></p>
                                <p><strong>Loan Amount:</strong> UGX <?= number_format($loan['amount'], 2) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Loan ID:</strong> <?= $loan['loan_number'] ?></p>
                                <p><strong>Outstanding Balance:</strong> UGX <?= number_format($loan['amount'] - ($loan['amount_paid'] ?? 0), 2) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>Payment Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="repayment_id" class="form-label">Select Payment Installment</label>
                                    <select class="form-select" id="repayment_id" name="repayment_id" required>
                                        <option value="">-- Select installment --</option>
                                        <?php foreach ($pending_repayments as $repayment): ?>
                                            <option value="<?= $repayment['id'] ?>" 
                                                data-due-amount="<?= $repayment['amount'] ?>">
                                                Installment due <?= date('d M Y', strtotime($repayment['due_date'])) ?> - 
                                                UGX <?= number_format($repayment['amount'], 2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="amount" class="form-label">Amount Paid</label>
                                    <div class="input-group">
                                        <span class="input-group-text">UGX</span>
                                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="payment_date" class="form-label">Payment Date</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="mobile_money">Mobile Money</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="check">Check</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="reference_number" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="reference_number" name="reference_number">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Record Payment
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-set amount to full due amount when installment is selected
        document.getElementById('repayment_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const dueAmount = selectedOption.getAttribute('data-due-amount');
                document.getElementById('amount').value = dueAmount;
            }
        });
    </script>
</body>
</html>