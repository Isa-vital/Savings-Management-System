<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

// Only allow access for Core Admins and Administrators
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header("Location: " . BASE_URL . "members/my_savings.php");
    } else {
        header("Location: " . BASE_URL . "landing.php");
    }
    exit;
}

$loan_id = intval($_GET['loan_id']);

// Function to generate reference number
function generateReferenceNumber($loan_number) {
    $prefix = 'PAY-';
    $date = date('Ymd');
    $random = strtoupper(substr(md5(uniqid()), 0, 6));
    return $prefix . $loan_number . '-' . $date . '-' . $random;
}

// Fetch loan details with interest calculation
try {
    $stmt = $pdo->prepare("SELECT l.*, m.full_name, m.member_no 
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

    // Calculate total loan amount (principal + interest)
    $total_loan_amount = $loan['amount'] + ($loan['amount'] * ($loan['interest_rate'] / 100));

    // Calculate total paid and outstanding balance
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total_paid FROM loan_repayments WHERE loan_id = :loan_id");
    $stmt->execute([':loan_id' => $loan_id]);
    $total_paid = $stmt->fetchColumn();
    $outstanding = max(0, $total_loan_amount - $total_paid); // Ensure not negative

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
        $payment_type = sanitize($_POST['payment_type']);
        $amount = floatval($_POST['amount']);
        $payment_date = sanitize($_POST['payment_date']);
        $payment_method = sanitize($_POST['payment_method']);
        $notes = sanitize($_POST['notes']);
        $admin_id = $_SESSION['user']['id'];
        
        // Auto-generate reference number
        $reference_number = generateReferenceNumber($loan['loan_number']);
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception("Amount must be positive");
        }
        
        if ($payment_type === 'installment') {
            $repayment_id = intval($_POST['repayment_id']);
            
            // Validate against installment amount
            $stmt = $pdo->prepare("SELECT amount FROM loan_repayments WHERE id = :id AND loan_id = :loan_id");
            $stmt->execute([':id' => $repayment_id, ':loan_id' => $loan_id]);
            $repayment = $stmt->fetch();
            
            if (!$repayment) {
                throw new Exception("Invalid repayment record");
            }

            if ($amount > $repayment['amount']) {
                throw new Exception("Amount cannot exceed the installment amount");
            }
            
            $repayment_data = [
                ':repayment_id' => $repayment_id,
                ':amount_paid' => $amount
            ];
        } else {
            // Manual payment - validate against outstanding balance
            if ($amount > $outstanding) {
                throw new Exception("Amount cannot exceed the outstanding balance of UGX " . number_format($outstanding, 2));
            }
            
            $repayment_data = [
                ':repayment_id' => null,
                ':amount_paid' => $amount
            ];
            
            // Create a new repayment record for manual payments
            $stmt = $pdo->prepare("INSERT INTO loan_repayments 
                                  (loan_id, amount, due_date, status, payment_date, amount_paid)
                                  VALUES 
                                  (:loan_id, :amount, :due_date, 'paid', :payment_date, :amount_paid)");
            $stmt->execute([
                ':loan_id' => $loan_id,
                ':amount' => $amount,
                ':due_date' => $payment_date,
                ':payment_date' => $payment_date,
                ':amount_paid' => $amount
            ]);
            
            $repayment_id = $pdo->lastInsertId();
            $repayment_data[':repayment_id'] = $repayment_id;
        }

        // Record payment
        $stmt = $pdo->prepare("INSERT INTO payments 
                              (loan_id, repayment_id, amount, payment_date, 
                               payment_method, reference_number, received_by, notes, payment_type)
                              VALUES 
                              (:loan_id, :repayment_id, :amount, :payment_date, 
                               :payment_method, :reference_number, :received_by, :notes, :payment_type)");
        $stmt->execute([
            ':loan_id' => $loan_id,
            ':repayment_id' => $repayment_data[':repayment_id'],
            ':amount' => $amount,
            ':payment_date' => $payment_date,
            ':payment_method' => $payment_method,
            ':reference_number' => $reference_number,
            ':received_by' => $admin_id,
            ':notes' => $notes,
            ':payment_type' => $payment_type
        ]);

        // Update repayment status if paying an installment
        if ($payment_type === 'installment') {
            $stmt = $pdo->prepare("UPDATE loan_repayments 
                                  SET status = 'paid', 
                                      payment_date = :payment_date,
                                      amount_paid = :amount_paid
                                  WHERE id = :repayment_id");
            $stmt->execute([
                ':payment_date' => $payment_date,
                ':amount_paid' => $repayment_data[':amount_paid'],
                ':repayment_id' => $repayment_data[':repayment_id']
            ]);
        }

        // Update loan status if fully paid
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total_paid FROM loan_repayments WHERE loan_id = :loan_id");
        $stmt->execute([':loan_id' => $loan_id]);
        $total_paid = $stmt->fetchColumn();
        
        if ($total_paid >= $total_loan_amount) {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'completed' WHERE id = :loan_id");
            $stmt->execute([':loan_id' => $loan_id]);
        }

        $pdo->commit();
        $_SESSION['success'] = "Payment recorded successfully. Reference: " . $reference_number;
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
        .payment-type-tabs .nav-link {
            cursor: pointer;
        }
        #manualPaymentFields, #installmentPaymentFields {
            display: none;
        }
        .balance-zero {
            color: #28a745;
            font-weight: bold;
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
                                <p><strong>Member:</strong> <?= htmlspecialchars($loan['full_name']) ?> (<?= $loan['member_no'] ?>)</p>
                                <p><strong>Principal Amount:</strong> UGX <?= number_format($loan['amount'], 2) ?></p>
                                <p><strong>Interest Rate:</strong> <?= $loan['interest_rate'] ?>%</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Loan ID:</strong> <?= $loan['loan_number'] ?></p>
                                <p><strong>Total Loan Amount:</strong> UGX <?= number_format($total_loan_amount, 2) ?></p>
                                <p><strong>Outstanding Balance:</strong> 
                                    <?php if ($outstanding <= 0): ?>
                                        <span class="balance-zero">UGX 0.00 (Fully Paid)</span>
                                    <?php else: ?>
                                        UGX <?= number_format($outstanding, 2) ?>
                                    <?php endif; ?>
                                </p>
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
                            <input type="hidden" name="payment_type" id="paymentType" value="installment">
                            
                            <ul class="nav nav-tabs payment-type-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link active" data-payment-type="installment">
                                        <i class="fas fa-calendar-check me-1"></i> Installment Payment
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-payment-type="manual">
                                        <i class="fas fa-hand-holding-usd me-1"></i> Manual Payment
                                    </a>
                                </li>
                            </ul>

                            <div id="installmentPaymentFields">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="repayment_id" class="form-label">Select Payment Installment</label>
                                        <select class="form-select" id="repayment_id" name="repayment_id">
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
                            </div>

                            <div id="manualPaymentFields">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            You can record a partial or full payment that doesn't match any scheduled installment.
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="manual_amount" class="form-label">Amount Paid</label>
                                        <div class="input-group">
                                            <span class="input-group-text">UGX</span>
                                            <input type="number" step="0.01" class="form-control" id="manual_amount" name="amount" 
                                                   max="<?= $outstanding ?>" placeholder="Enter payment amount" required>
                                        </div>
                                        <small class="text-muted">Maximum: UGX <?= number_format($outstanding, 2) ?></small>
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
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" value="Will be auto-generated" readonly>
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
        // Payment type tabs
        document.querySelectorAll('.payment-type-tabs .nav-link').forEach(tab => {
            tab.addEventListener('click', function() {
                const paymentType = this.getAttribute('data-payment-type');
                
                // Update active tab
                document.querySelectorAll('.payment-type-tabs .nav-link').forEach(t => {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update hidden field
                document.getElementById('paymentType').value = paymentType;
                
                // Show/hide fields
                if (paymentType === 'installment') {
                    document.getElementById('installmentPaymentFields').style.display = 'block';
                    document.getElementById('manualPaymentFields').style.display = 'none';
                    document.getElementById('amount').required = true;
                    document.getElementById('manual_amount').required = false;
                } else {
                    document.getElementById('installmentPaymentFields').style.display = 'none';
                    document.getElementById('manualPaymentFields').style.display = 'block';
                    document.getElementById('amount').required = false;
                    document.getElementById('manual_amount').required = true;
                }
            });
        });

        // Auto-set amount to full due amount when installment is selected
        document.getElementById('repayment_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const dueAmount = selectedOption.getAttribute('data-due-amount');
                document.getElementById('amount').value = dueAmount;
            }
        });

        // Initialize showing the correct fields
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('installmentPaymentFields').style.display = 'block';
        });
    </script>
</body>
</html>