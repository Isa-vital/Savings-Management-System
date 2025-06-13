<?php
require_once __DIR__ . '/../config.php';      // For $pdo, BASE_URL, APP_NAME, sanitize()
require_once __DIR__ . '/../helpers/auth.php';

require_login(); // Redirects if not logged in

// Check if member ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid member ID";
    header("Location: " . BASE_URL . "members/list.php");
    exit;
}

$member_id = intval($_GET['id']);

// Fetch member details
$stmt = $pdo->prepare("SELECT * FROM memberz WHERE id = :id");
$stmt->execute([':id' => $member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    $_SESSION['error_message'] = "Member not found";
    header("Location: " . BASE_URL . "members/list.php");
    exit;
}

// Fetch loans for this member
$query = "SELECT l.*, 
          COALESCE(SUM(lr.amount), 0) as amount_paid,
          (l.amount - COALESCE(SUM(lr.amount), 0)) as balance,
          u.username as processed_by_name
          FROM loans l
          LEFT JOIN users u ON l.processed_by = u.id
          LEFT JOIN loan_repayments lr ON l.id = lr.loan_id
          WHERE l.member_id = :member_id
          GROUP BY l.id
          ORDER BY l.application_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([':member_id' => $member_id]);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_loans = 0;
$total_paid = 0;
$total_balance = 0;

foreach ($loans as $loan) {
    $total_loans += $loan['amount'];
    $total_paid += $loan['amount_paid'];
    $total_balance += $loan['balance'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($member['full_name']) ?>'s Loans - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .uganda-flag {
            background: linear-gradient(to right, 
                #000 0%, #000 33%, 
                #FFC90D 33%, #FFC90D 66%, 
                #DE2010 66%, #DE2010 100%);
            height: 5px;
            margin-bottom: 20px;
        }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .status-completed { color: #17a2b8; font-weight: bold; }
        .loan-card { border-left: 5px solid #6f42c1; }
        .member-header { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="uganda-flag"></div>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-hand-holding-usd me-2"></i>Loans for <?= htmlspecialchars($member['full_name']) ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="newloan.php?member_id=<?= $member_id ?>" class="btn btn-success">
                            <i class="fas fa-plus-circle me-1"></i> New Loan
                        </a>
                        <a href="<?= BASE_URL ?>members/list.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Members
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <!-- Member Summary Card -->
                <div class="card mb-4 member-header">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title">
                                    <?= htmlspecialchars($member['full_name']) ?>
                                    <small class="text-muted">#<?= htmlspecialchars($member['member_no']) ?></small>
                                </h5>
                                <p class="card-text mb-1">
                                    <i class="fas fa-phone me-2"></i> <?= htmlspecialchars($member['phone']) ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($member['email'] ?? 'N/A') ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h6 class="text-muted">Total Loans</h6>
                                        <h4>UGX <?= number_format($total_loans, 2) ?></h4>
                                    </div>
                                    <div class="col-4">
                                        <h6 class="text-muted">Total Paid</h6>
                                        <h4 class="text-success">UGX <?= number_format($total_paid, 2) ?></h4>
                                    </div>
                                    <div class="col-4">
                                        <h6 class="text-muted">Balance</h6>
                                        <h4 class="text-danger">UGX <?= number_format($total_balance, 2) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loans Table -->
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-1"></i> Loan History
                        </h6>
                        <span class="badge bg-primary">
                            <?= count($loans) ?> <?= count($loans) === 1 ? 'Loan' : 'Loans' ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($loans)): ?>
                            <div class="alert alert-info">No loans found for this member.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>Loan #</th>
                                            <th>Amount</th>
                                            <th>Interest</th>
                                            <th>Term</th>
                                            <th>Applied</th>
                                            <th>Status</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loans as $loan): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($loan['loan_number']) ?></td>
                                                <td>UGX <?= number_format($loan['amount'], 2) ?></td>
                                                <td><?= $loan['interest_rate'] ?>%</td>
                                                <td><?= $loan['term_months'] ?> months</td>
                                                <td>
                                                    <?= !empty($loan['application_date']) && $loan['application_date'] !== '0000-00-00' 
                                                        ? date('d M Y', strtotime($loan['application_date'])) 
                                                        : '<span class="text-muted">Not Submitted</span>' ?>
                                                </td>
                                                <td>
                                                    <span class="status-<?= $loan['status'] ?>">
                                                        <?= ucfirst($loan['status']) ?>
                                                        <?php if ($loan['processed_by_name']): ?>
                                                            <br><small>by <?= htmlspecialchars($loan['processed_by_name']) ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>UGX <?= number_format($loan['amount_paid'], 2) ?></td>
                                                <td>
                                                    <?php if ($loan['status'] === 'approved'): ?>
                                                        UGX <?= number_format($loan['balance'], 2) ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="loanActions" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="loanActions">
                                                            <li>
                                                                <a class="dropdown-item" href="viewloan.php?id=<?= $loan['id'] ?>">
                                                                    <i class="fas fa-eye me-1"></i> View Details
                                                                </a>
                                                            </li>
                                                            <?php if ($loan['status'] === 'pending'): ?>
                                                                <li>
                                                                    <form method="POST" action="loanslist.php" class="dropdown-item">
                                                                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                                        <input type="hidden" name="new_status" value="approved">
                                                                        <button type="submit" name="update_status" class="btn btn-link p-0 text-start w-100">
                                                                            <i class="fas fa-check-circle me-1 text-success"></i> Approve
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" action="loanslist.php" class="dropdown-item">
                                                                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                                        <input type="hidden" name="new_status" value="rejected">
                                                                        <button type="submit" name="update_status" class="btn btn-link p-0 text-start w-100">
                                                                            <i class="fas fa-times-circle me-1 text-danger"></i> Reject
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if ($loan['status'] === 'approved'): ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="addrepayment.php?loan_id=<?= $loan['id'] ?>">
                                                                        <i class="fas fa-money-bill-wave me-1"></i> Add Payment
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li>
                                                                <a class="dropdown-item" href="editloan.php?id=<?= $loan['id'] ?>">
                                                                    <i class="fas fa-edit me-1"></i> Edit
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>