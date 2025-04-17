<?php
session_start(); 
if (!isset($_SESSION['admin']['id'])) {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit;
}

// Initialize session and check authentication
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/database.php';

// Initialize variables
$error = '';
$success = '';
$members = [];
$loanData = [
    'member_id' => '',
    'amount' => '',
    'interest_rate' => '10', // Default interest rate
    'term_months' => '12',   // Default loan term
    'purpose' => ''
];

// Fetch all active members for dropdown
try {
    $stmt = $pdo->query("SELECT id, member_no, full_name FROM memberz  ORDER BY full_name");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load member list: " . $e->getMessage();
}

// Process loan application form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_loan'])) {
    $loanData = [
        'member_id' => $_POST['member_id'],
        'amount' => $_POST['amount'],
        'interest_rate' => $_POST['interest_rate'],
        'term_months' => $_POST['term_months'],
        'purpose' => trim($_POST['purpose'])
    ];

    // Validate inputs
    try {
        // Basic validation
        if (empty($loanData['member_id']) || empty($loanData['amount']) || empty($loanData['term_months'])) {
            throw new Exception("All required fields must be filled");
        }

        if (!is_numeric($loanData['amount']) || $loanData['amount'] <= 0) {
            throw new Exception("Loan amount must be a positive number");
        }

        if (!is_numeric($loanData['interest_rate']) || $loanData['interest_rate'] < 0) {
            throw new Exception("Interest rate must be a positive number");
        }

        if (!is_numeric($loanData['term_months']) || $loanData['term_months'] < 1) {
            throw new Exception("Loan term must be at least 1 month");
        }

        // Generate loan number (format: LN-YYYYMMDD-XXXX)
        $loanNumber = 'LN-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Calculate repayment amount (simple interest)
        $principal = $loanData['amount'];
        $interest = $principal * ($loanData['interest_rate'] / 100) * ($loanData['term_months'] / 12);
        $totalRepayment = $principal + $interest;
        $monthlyRepayment = $totalRepayment / $loanData['term_months'];

        // Start transaction
        $pdo->beginTransaction();

        // Insert loan record
        $stmt = $pdo->prepare("
            INSERT INTO loans (
                member_id, 
                loan_number, 
                amount, 
                interest_rate, 
                term_months, 
                purpose, 
                monthly_repayment,
                total_repayment,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $loanData['member_id'],
            $loanNumber,
            $loanData['amount'],
            $loanData['interest_rate'],
            $loanData['term_months'],
            $loanData['purpose'],
            $monthlyRepayment,
            $totalRepayment,
            $_SESSION['admin']['id']
        ]);

        $loanId = $pdo->lastInsertId();

        // Create repayment schedule
        $dueDate = date('Y-m-d', strtotime('+1 month'));
        $stmt = $pdo->prepare("
            INSERT INTO loan_repayments (
                loan_id, 
                due_date, 
                amount, 
                status
            ) VALUES (?, ?, ?, 'pending')
        ");

        for ($i = 1; $i <= $loanData['term_months']; $i++) {
            $stmt->execute([$loanId, $dueDate, $monthlyRepayment]);
            $dueDate = date('Y-m-d', strtotime($dueDate . ' +1 month'));
        }

        // Commit transaction
        $pdo->commit();

        $success = "Loan created successfully! Loan Number: $loanNumber";
        $loanData = []; // Clear form on success

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating loan: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Loan Application - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .loan-card {
            border-left: 5px solid #2c3e50;
        }
        .form-label.required:after {
            content: " *";
            color: #dc3545;
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
                        <i class="fas fa-hand-holding-usd me-2"></i>New Loan Application
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="loanslist.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Loans
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="card loan-card shadow mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>Loan Application Form
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Member</label>
                                    <select class="form-select" name="member_id" required>
                                        <option value="">Select Member</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?= $member['id'] ?>" 
                                                <?= ($loanData['member_id'] ?? '') == $member['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($member['full_name']) ?> (<?= $member['member_no'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Loan Amount (UGX)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">UGX</span>
                                        <input type="number" class="form-control" name="amount" 
                                            value="<?= htmlspecialchars($loanData['amount'] ?? '') ?>" 
                                            min="10000" step="1000" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label required">Interest Rate (%)</label>
                                    <input type="number" class="form-control" name="interest_rate" 
                                        value="<?= htmlspecialchars($loanData['interest_rate'] ?? '') ?>" 
                                        min="5" max="30" step="0.5" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Term (Months)</label>
                                    <select class="form-select" name="term_months" required>
                                        <option value="6" <?= ($loanData['term_months'] ?? '') == 6 ? 'selected' : '' ?>>6 Months</option>
                                        <option value="12" <?= ($loanData['term_months'] ?? '') == 12 ? 'selected' : '' ?>>12 Months</option>
                                        <option value="24" <?= ($loanData['term_months'] ?? '') == 24 ? 'selected' : '' ?>>24 Months</option>
                                        <option value="36" <?= ($loanData['term_months'] ?? '') == 36 ? 'selected' : '' ?>>36 Months</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Purpose</label>
                                    <input type="text" class="form-control" name="purpose" 
                                        value="<?= htmlspecialchars($loanData['purpose'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                                <button type="submit" name="submit_loan" class="btn btn-success">
                                    <i class="fas fa-check-circle me-1"></i> Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const amount = parseFloat(document.querySelector('[name="amount"]').value);
            if (isNaN(amount) || amount <= 0) {
                alert('Please enter a valid loan amount');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>