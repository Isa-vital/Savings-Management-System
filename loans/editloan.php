<?php
session_start();

if (!isset($_SESSION['admin']['id'])) {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once '../helpers/auth.php';
require_once '../helpers/loans.php'; // For createRepaymentSchedule()

// Check if loan ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid loan ID";
    header('Location: loanslist.php');
    exit;
}

$loan_id = intval($_GET['id']);

// Fetch loan details
try {
    $stmt = $pdo->prepare("SELECT l.*, m.full_name, m.member_no 
                          FROM loans l
                          JOIN memberz m ON l.member_id = m.id
                          WHERE l.id = :loan_id");
    $stmt->execute([':loan_id' => $loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        $_SESSION['error'] = "Loan not found";
        header('Location: loanslist.php');
        exit;
    }

    // Fetch all members for dropdown
    $stmt = $pdo->prepare("SELECT id, full_name, member_no FROM memberz ORDER BY full_name");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: loanslist.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    
    try {
        $member_id = intval($_POST['member_id']);
        $loan_number = sanitize($_POST['loan_number']);
        $amount = floatval($_POST['amount']);
        $interest_rate = floatval($_POST['interest_rate']);
        $term_months = intval($_POST['term_months']);
        $purpose = sanitize($_POST['purpose']);
        $application_date = sanitize($_POST['application_date']);
        $status = sanitize($_POST['status']);
        $admin_id = $_SESSION['admin']['id'];
        $current_status = $loan['status'];

        // Validate inputs
        if ($amount <= 0 || $interest_rate < 0 || $term_months <= 0) {
            throw new Exception("Invalid loan parameters");
        }

        // Prevent editing of completed loans
        if ($current_status === 'completed') {
            throw new Exception("Completed loans cannot be modified");
        }

        // Update loan
        $stmt = $pdo->prepare("UPDATE loans SET 
                              member_id = :member_id,
                              loan_number = :loan_number,
                              amount = :amount,
                              interest_rate = :interest_rate,
                              term_months = :term_months,
                              purpose = :purpose,
                              application_date = :application_date,
                              status = :status,
                              updated_by = :updated_by,
                              updated_at = NOW()
                              WHERE id = :loan_id");
        
        $stmt->execute([
            ':member_id' => $member_id,
            ':loan_number' => $loan_number,
            ':amount' => $amount,
            ':interest_rate' => $interest_rate,
            ':term_months' => $term_months,
            ':purpose' => $purpose,
            ':application_date' => $application_date,
            ':status' => $status,
            ':updated_by' => $admin_id,
            ':loan_id' => $loan_id
        ]);

        // Handle status changes
        if ($status === 'approved' && $current_status !== 'approved') {
            // Create repayment schedule for newly approved loans
            createRepaymentSchedule($pdo, $loan_id);
            
            // Record who approved the loan
            $stmt = $pdo->prepare("UPDATE loans SET 
                                  processed_by = :admin_id,
                                  processed_at = NOW()
                                  WHERE id = :loan_id");
            $stmt->execute([
                ':admin_id' => $admin_id,
                ':loan_id' => $loan_id
            ]);
        } elseif ($status !== 'approved' && $current_status === 'approved') {
            // Remove repayment schedule if changing from approved to another status
            $stmt = $pdo->prepare("DELETE FROM loan_repayments WHERE loan_id = :loan_id");
            $stmt->execute([':loan_id' => $loan_id]);
        }

        $pdo->commit();
        $_SESSION['success'] = "Loan updated successfully";
        header("Location: viewloan.php?id=$loan_id");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating loan: " . $e->getMessage();
        header("Location: editloan.php?id=$loan_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Loan - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loan-card {
            border-left: 4px solid #6f42c1;
        }
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
        .status-completed { color: #17a2b8; }
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
                        <i class="fas fa-edit me-2"></i>Edit Loan
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

                <div class="card loan-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Loan Information
                            <span class="float-end status-<?= $loan['status'] ?>">
                                Current Status: <?= ucfirst($loan['status']) ?>
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="member_id" class="form-label">Member</label>
                                    <select class="form-select" id="member_id" name="member_id" required>
                                        <option value="">-- Select Member --</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?= $member['id'] ?>" 
                                                <?= $member['id'] == $loan['member_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($member['full_name']) ?> (<?= $member['member_no'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="loan_number" class="form-label">Loan Number</label>
                                    <input type="text" class="form-control" id="loan_number" name="loan_number" 
                                           value="<?= htmlspecialchars($loan['loan_number']) ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="amount" class="form-label">Amount (UGX)</label>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                                           value="<?= htmlspecialchars($loan['amount']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="interest_rate" class="form-label">Interest Rate (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="interest_rate" name="interest_rate" 
                                           value="<?= htmlspecialchars($loan['interest_rate']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="term_months" class="form-label">Term (Months)</label>
                                    <input type="number" class="form-control" id="term_months" name="term_months" 
                                           value="<?= htmlspecialchars($loan['term_months']) ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="purpose" class="form-label">Purpose</label>
                                    <input type="text" class="form-control" id="purpose" name="purpose" 
                                           value="<?= htmlspecialchars($loan['purpose']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="application_date" class="form-label">Application Date</label>
                                    <input type="date" class="form-control" id="application_date" name="application_date" 
                                           value="<?= !empty($loan['application_date']) && $loan['application_date'] !== '0000-00-00' 
                                               ? htmlspecialchars($loan['application_date']) 
                                               : date('Y-m-d') ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Change Status</label>
                                    <select class="form-select" id="status" name="status" <?= $loan['status'] === 'completed' ? 'disabled' : '' ?>>
                                        <option value="pending" <?= $loan['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= $loan['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="rejected" <?= $loan['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        <?php if ($loan['status'] === 'completed'): ?>
                                            <option value="completed" selected>Completed (cannot be changed)</option>
                                        <?php endif; ?>
                                    </select>
                                    <?php if ($loan['status'] === 'completed'): ?>
                                        <small class="text-muted">Completed loans cannot change status</small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                                <a href="viewloan.php?id=<?= $loan_id ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const interest = parseFloat(document.getElementById('interest_rate').value);
            const term = parseInt(document.getElementById('term_months').value);
            
            if (amount <= 0 || interest < 0 || term <= 0) {
                e.preventDefault();
                alert('Please enter valid values for amount, interest rate, and term');
            }
            
            const currentStatus = "<?= $loan['status'] ?>";
            const newStatus = document.getElementById('status').value;
            
            if (currentStatus === 'approved' && newStatus !== 'approved') {
                if (!confirm('Changing from Approved status will DELETE the repayment schedule. Continue?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>