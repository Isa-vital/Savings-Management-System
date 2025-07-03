<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user']['id'])) {
    header("Location: /savingssystem/auth/login.php");
    exit;
}

// Load dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

if (!has_role('Member') || !isset($_SESSION['user']['member_id']) || empty($_SESSION['user']['member_id'])) {
    $_SESSION['error_message'] = "You must be a logged-in member to make a loan repayment.";
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$current_user_id = $_SESSION['user']['id'];
$current_member_id = $_SESSION['user']['member_id'];
$page_title = "Make Loan Repayment";
$currency_symbol = defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX';

$active_loans = [];
$loans_with_balances = []; 

try {
    // Fetch member's approved/completed loans with outstanding balances
    $stmt_loans = $pdo->prepare("
        SELECT 
            l.id as loan_id, 
            l.loan_number, 
            l.amount as amount_approved, 
            l.total_repayment,
            l.status as loan_status,
            l.application_date,
            COALESCE(SUM(CASE WHEN lr.status = 'paid' THEN lr.amount_paid ELSE 0 END), 0) as total_paid_verified
        FROM loans l
        LEFT JOIN loan_repayment lr ON l.id = lr.loan_id
        WHERE l.member_id = :member_id 
          AND l.status IN ('approved', 'completed')
        GROUP BY l.id, l.loan_number, l.amount, l.total_repayment, l.status, l.application_date
        HAVING COALESCE(SUM(lr.amount_paid), 0) < l.total_repayment
        ORDER BY l.application_date ASC
    ");
    
    $stmt_loans->bindParam(':member_id', $current_member_id, PDO::PARAM_INT);
    $stmt_loans->execute();
    $active_loans = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);

    foreach ($active_loans as $loan) {
        $outstanding_balance = $loan['total_repayment'] - $loan['total_paid_verified'];
        if ($outstanding_balance > 0.001) {
            $loan['outstanding_balance'] = $outstanding_balance;
            $loans_with_balances[] = $loan;
        }
    }
    
} catch (PDOException $e) {
    error_log("Error fetching member's active loans: " . $e->getMessage());
    $_SESSION['error_message'] = "Could not load your loan details. Please try again later.";
}

// Notification handling
$sa_error = $_SESSION['error_message'] ?? '';
if(isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
$sa_success = $_SESSION['success_message'] ?? '';
if(isset($_SESSION['success_message'])) unset($_SESSION['success_message']);

$form_errors_for_sa = $_SESSION['form_errors'] ?? [];
$form_values = $_SESSION['form_values'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);

$payment_methods = ['Mobile Money', 'Airtel Money', 'Bank Deposit', 'Cash at Office', 'Other'];
$current_date = date('Y-m-d');

// Process repayment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_values = $_POST;

    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'CSRF token validation failed. Please try again.';
        header("Location: " . BASE_URL . "members/make_repayment.php"); 
        exit;
    }

    $loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
    $amount_paid = filter_input(INPUT_POST, 'amount_paid', FILTER_VALIDATE_FLOAT);
    $repayment_date = sanitize($_POST['repayment_date'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? '');
    $transaction_reference = sanitize($_POST['transaction_reference'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $proof_document_file = $_FILES['proof_document'] ?? null;
    $proof_document_path_to_save = null;
    $form_errors = [];

    // Validation
    if (empty($loan_id)) {
        $form_errors['loan_id'] = "Please select the loan you are repaying.";
    } else {
        $valid_loan = false;
        foreach ($loans_with_balances as $loan) {
            if ($loan['loan_id'] == $loan_id) {
                $valid_loan = true;
                break;
            }
        }
        if (!$valid_loan) {
            $form_errors['loan_id'] = "Invalid loan selected for repayment.";
        }
    }

    if ($amount_paid === false || $amount_paid <= 0) {
        $form_errors['amount_paid'] = "Amount paid must be a positive number.";
    }

    if (empty($repayment_date)) {
        $form_errors['repayment_date'] = "Repayment date is required.";
    } elseif (strtotime($repayment_date) === false) {
        $form_errors['repayment_date'] = "Invalid repayment date format.";
    }

    if (empty($payment_method) || !in_array($payment_method, $payment_methods)) {
        $form_errors['payment_method'] = "Please select a valid payment method.";
    }

    if (empty($transaction_reference)) {
        $form_errors['transaction_reference'] = "Transaction reference is required.";
    }

    // File upload validation
    if (!isset($proof_document_file) || $proof_document_file['error'] == UPLOAD_ERR_NO_FILE) {
        $form_errors['proof_document'] = "Proof of payment document is required.";
    } elseif ($proof_document_file['error'] == UPLOAD_ERR_OK) {
        $allowed_mime_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $proof_document_file['tmp_name']);
        finfo_close($file_info);
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            $form_errors['proof_document'] = "Invalid file type. Only PDF, JPG, PNG are allowed.";
        } elseif ($proof_document_file['size'] > $max_file_size) {
            $form_errors['proof_document'] = "File is too large. Maximum size is 5MB.";
        } else {
            $upload_dir = __DIR__ . '/../assets/uploads/repayment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }
            
            $file_extension = pathinfo($proof_document_file['name'], PATHINFO_EXTENSION);
            $unique_file_name = 'repayment_' . $loan_id . '_' . time() . '.' . $file_extension;
            $destination_path = $upload_dir . $unique_file_name;
            
            if (move_uploaded_file($proof_document_file['tmp_name'], $destination_path)) {
                $proof_document_path_to_save = 'assets/uploads/repayment_proofs/' . $unique_file_name;
            } else {
                $form_errors['proof_document'] = "Failed to upload proof document. Please try again.";
            }
        }
    }

    if (empty($form_errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert repayment record
            $stmt = $pdo->prepare("
                INSERT INTO loan_repayment (
                    loan_id, 
                    amount, 
                    amount_paid, 
                    payment_date, 
                    status,
                    created_at
                ) VALUES (
                    :loan_id, 
                    :amount, 
                    :amount_paid, 
                    :payment_date, 
                    'paid',
                    NOW()
                )
            ");
            
            $stmt->execute([
                ':loan_id' => $loan_id,
                ':amount' => $amount_paid,
                ':amount_paid' => $amount_paid,
                ':payment_date' => $repayment_date
            ]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Loan repayment submitted successfully!";
            header("Location: " . BASE_URL . "members/make_repayment.php");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Repayment submission failed: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to submit repayment. Please try again.";
        }
    } else {
        $_SESSION['form_errors'] = $form_errors;
        $_SESSION['form_values'] = $form_values;
        $_SESSION['error_message'] = "Please correct the errors in the form.";
        header("Location: " . BASE_URL . "members/make_repayment.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) . ' - ' . htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .loan-card {
            border-left: 4px solid #4e73df;
            margin-bottom: 1rem;
        }
        .invalid-feedback {
            display: block;
        }
        .is-invalid {
            border-color: #e74a3b;
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
                    <h1 class="h2"><i class="fas fa-money-check-alt me-2"></i><?= htmlspecialchars($page_title) ?></h1>
                </div>

                <?php if (empty($loans_with_balances)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You have no active loans with an outstanding balance.
                    </div>
                <?php else: ?>
                    <div class="mb-4">
                        <h5 class="mb-3">Your Active Loans with Outstanding Balances:</h5>
                        <?php foreach ($loans_with_balances as $loan): ?>
                            <div class="card loan-card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Loan #:</strong> <?= htmlspecialchars($loan['loan_number']) ?></div>
                                        <div class="col-md-3"><strong>Amount:</strong> <?= htmlspecialchars($currency_symbol . ' ' . number_format($loan['amount_approved'], 2)) ?></div>
                                        <div class="col-md-3"><strong>Paid:</strong> <?= htmlspecialchars($currency_symbol . ' ' . number_format($loan['total_paid_verified'], 2)) ?></div>
                                        <div class="col-md-3"><strong>Balance:</strong> <?= htmlspecialchars($currency_symbol . ' ' . number_format($loan['outstanding_balance'], 2)) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            Submit Repayment Details
                        </div>
                        <div class="card-body">
                            <form method="POST" action="make_repayment.php" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateToken()) ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="loan_id" class="form-label">Select Loan <span class="text-danger">*</span></label>
                                        <select name="loan_id" id="loan_id" class="form-select <?= isset($form_errors_for_sa['loan_id']) ? 'is-invalid' : '' ?>" required>
                                            <option value="">-- Select Loan --</option>
                                            <?php foreach ($loans_with_balances as $loan): ?>
                                                <option value="<?= htmlspecialchars($loan['loan_id']) ?>" <?= (isset($form_values['loan_id']) && $form_values['loan_id'] == $loan['loan_id']) ? 'selected' : '' ?>>
                                                    Loan #<?= htmlspecialchars($loan['loan_number']) ?> (Balance: <?= htmlspecialchars($currency_symbol . ' ' . number_format($loan['outstanding_balance'], 2)) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($form_errors_for_sa['loan_id'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($form_errors_for_sa['loan_id']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="amount_paid" class="form-label">Amount Paid <span class="text-danger">*</span></label>
                                        <input type="number" name="amount_paid" id="amount_paid" class="form-control <?= isset($form_errors_for_sa['amount_paid']) ? 'is-invalid' : '' ?>" 
                                               value="<?= htmlspecialchars($form_values['amount_paid'] ?? '') ?>" required min="0.01" step="0.01">
                                        <?php if (isset($form_errors_for_sa['amount_paid'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($form_errors_for_sa['amount_paid']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="repayment_date" class="form-label">Repayment Date <span class="text-danger">*</span></label>
                                        <input type="date" name="repayment_date" id="repayment_date" class="form-control <?= isset($form_errors_for_sa['repayment_date']) ? 'is-invalid' : '' ?>" 
                                               value="<?= htmlspecialchars($form_values['repayment_date'] ?? $current_date) ?>" required max="<?= $current_date ?>">
                                        <?php if (isset($form_errors_for_sa['repayment_date'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($form_errors_for_sa['repayment_date']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                        <select name="payment_method" id="payment_method" class="form-select <?= isset($form_errors_for_sa['payment_method']) ? 'is-invalid' : '' ?>" required>
                                            <option value="">-- Select Method --</option>
                                            <?php foreach ($payment_methods as $method): ?>
                                                <option value="<?= htmlspecialchars($method) ?>" <?= (isset($form_values['payment_method']) && $form_values['payment_method'] == $method) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($method) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($form_errors_for_sa['payment_method'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($form_errors_for_sa['payment_method']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="transaction_reference" class="form-label">Transaction Reference <span class="text-danger">*</span></label>
                                    <input type="text" name="transaction_reference" id="transaction_reference" class="form-control <?= isset($form_errors_for_sa['transaction_reference']) ? 'is-invalid' : '' ?>" 
                                           value="<?= htmlspecialchars($form_values['transaction_reference'] ?? '') ?>" required>
                                    <small class="text-muted">E.g., Mobile Money TX ID, Bank Slip No.</small>
                                    <?php if (isset($form_errors_for_sa['transaction_reference'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($form_errors_for_sa['transaction_reference']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="proof_document" class="form-label">Proof of Payment <span class="text-danger">*</span></label>
                                    <input type="file" name="proof_document" id="proof_document" class="form-control <?= isset($form_errors_for_sa['proof_document']) ? 'is-invalid' : '' ?>" 
                                           required accept="application/pdf,image/jpeg,image/png">
                                    <small class="text-muted">Max file size: 5MB. Allowed types: PDF, JPG, PNG</small>
                                    <?php if (isset($form_errors_for_sa['proof_document'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($form_errors_for_sa['proof_document']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes (Optional)</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="3"><?= htmlspecialchars($form_values['notes'] ?? '') ?></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary me-md-2">
                                        <i class="fas fa-check-circle me-1"></i> Submit Repayment
                                    </button>
                                    <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($sa_error)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?= addslashes($sa_error) ?>',
                });
            <?php endif; ?>
            
            <?php if (!empty($sa_success)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: '<?= addslashes($sa_success) ?>',
                });
            <?php endif; ?>
            
            <?php if (!empty($form_errors_for_sa)): ?>
                let errorList = '<ul style="text-align: left;">';
                <?php foreach ($form_errors_for_sa as $error): ?>
                    errorList += '<li><?= addslashes($error) ?></li>';
                <?php endforeach; ?>
                errorList += '</ul>';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Form Errors',
                    html: errorList,
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>