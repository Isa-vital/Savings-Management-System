<?php
require_once __DIR__ . '/../config.php';
require_once '../helpers/auth.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access. Admin privileges required.";
    header('Location: ../index.php');
    exit();
}

// Handle loan status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $loan_id = intval($_POST['loan_id']);
        $new_status = sanitize($_POST['new_status']);
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE loans SET status = :status, processed_by = :processed_by, processed_at = NOW() WHERE id = :loan_id");
            $stmt->execute([
                ':status' => $new_status,
                ':processed_by' => $_SESSION['user']['id'],
                ':loan_id' => $loan_id
            ]);
            
            // If approved, create repayment schedule
            if ($new_status === 'approved') {
                createRepaymentSchedule($pdo, $loan_id);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Loan status updated successfully";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating loan: " . $e->getMessage();
        }
        redirect('loanslist.php');
    }
}

// Handle loan deletion
if (isset($_GET['delete'])) {
    $loan_id = intval($_GET['delete']);
    
    $pdo->beginTransaction();
    try {
        // First delete repayments
        $stmt = $pdo->prepare("DELETE FROM loan_repayments WHERE loan_id = :loan_id");
        $stmt->execute([':loan_id' => $loan_id]);
        
        // Then delete the loan
        $stmt = $pdo->prepare("DELETE FROM loans WHERE id = :loan_id");
        $stmt->execute([':loan_id' => $loan_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Loan deleted successfully";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting loan: " . $e->getMessage();
    }
    redirect('loanslist.php');
}

// Search and filter functionality
$search = '';
$status_filter = '';
$where_conditions = [];
$params = [];

if (isset($_GET['search'])) {
    $search = sanitize($_GET['search']);
    if (!empty($search)) {
        $where_conditions[] = "(m.member_no LIKE :search OR m.full_name LIKE :search OR l.loan_number LIKE :search)";
        $params[':search'] = "%$search%";
    }
}

if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected', 'completed'])) {
    $status_filter = sanitize($_GET['status']);
    $where_conditions[] = "l.status = :status";
    $params[':status'] = $status_filter;
}

$where = '';
if (!empty($where_conditions)) {
    $where = "WHERE " . implode(" AND ", $where_conditions);
}

// Fetch loans with member details
$query = "SELECT l.*, 
          m.member_no, m.full_name, m.phone,
          u.username as processed_by_name,
          COALESCE(SUM(lr.amount), 0) as amount_paid,
          (l.amount - COALESCE(SUM(lr.amount), 0)) as balance
          FROM loans l
          JOIN memberz m ON l.member_id = m.id
          LEFT JOIN users u ON l.processed_by = u.user_id
          LEFT JOIN loan_repayments lr ON l.id = lr.loan_id
          $where
          GROUP BY l.id
          ORDER BY l.application_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to create repayment schedule
function createRepaymentSchedule($pdo, $loan_id) {
    // Fetch loan details
    $stmt = $pdo->prepare("SELECT amount, interest_rate, term_months FROM loans WHERE id = :loan_id");
    $stmt->execute([':loan_id' => $loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        throw new Exception("Loan not found");
    }
    
    $principal = $loan['amount'];
    $interest_rate = $loan['interest_rate'] / 100;
    $term = $loan['term_months'];
    
    // Calculate monthly payment (simple interest)
    $total_interest = $principal * $interest_rate * ($term / 12);
    $total_payment = $principal + $total_interest;
    $monthly_payment = $total_payment / $term;
    
    // Create repayment schedule
    $due_date = date('Y-m-d', strtotime('+1 month'));
    for ($i = 1; $i <= $term; $i++) {
        $stmt = $pdo->prepare("INSERT INTO loan_repayments 
                              (loan_id, due_date, amount, status) 
                              VALUES (:loan_id, :due_date, :amount, 'pending')");
        $stmt->execute([
            ':loan_id' => $loan_id,
            ':due_date' => $due_date,
            ':amount' => $monthly_payment
        ]);
        $due_date = date('Y-m-d', strtotime($due_date . ' +1 month'));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Loans - <?= APP_NAME ?></title>
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
        .search-box { max-width: 400px; }
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
                        <i class="fas fa-hand-holding-usd me-2"></i>Loan Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="newloan.php" class="btn btn-success">
                            <i class="fas fa-plus-circle me-1"></i> New Loan
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <div class="row">
                            <div class="col-md-6">
                                <form method="GET" class="search-box">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" 
                                            placeholder="Search by member name, number or loan ID" 
                                            value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-outline-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search) || !empty($status_filter)): ?>
                                            <a href="loanslist.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group">
                                    <a href="loanslist.php?status=pending" class="btn btn-sm <?= $status_filter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
                                        Pending
                                    </a>
                                    <a href="loanslist.php?status=approved" class="btn btn-sm <?= $status_filter === 'approved' ? 'btn-success' : 'btn-outline-success' ?>">
                                        Approved
                                    </a>
                                    <a href="loanslist.php?status=rejected" class="btn btn-sm <?= $status_filter === 'rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">
                                        Rejected
                                    </a>
                                    <a href="loanslist.php?status=completed" class="btn btn-sm <?= $status_filter === 'completed' ? 'btn-info' : 'btn-outline-info' ?>">
                                        Completed
                                    </a>
                                    <a href="loanslist.php" class="btn btn-sm btn-outline-secondary">
                                        All
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Loan #</th>
                                        <th>Member</th>
                                        <th>Phone</th>
                                        <th>Amount</th>
                                        <th>Interest</th>
                                        <th>Term</th>
                                        <th>Applied</th>
                                        <th>Status</th>
                                        <th>Balance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($loans)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">No loans found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($loans as $loan): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($loan['loan_number']) ?></td>
                                                <td>
                                                    <a href="viewmember.php?id=<?= $loan['member_id'] ?>">
                                                        <?= htmlspecialchars($loan['full_name']) ?><br>
                                                        <small class="text-muted"><?= htmlspecialchars($loan['member_no']) ?></small>
                                                    </a>
                                                </td>
                                                <td><?= formatUgandanPhone($loan['phone']) ?></td>
                                                <td>UGX <?= number_format($loan['amount'], 2) ?></td>
                                                <td><?= $loan['interest_rate'] ?>%</td>
                                                <td><?= $loan['term_months'] ?> months</td>
                                                <td><?= date('d M Y', strtotime($loan['application_date'])) ?></td>
                                                <td>
                                                    <span class="status-<?= $loan['status'] ?>">
                                                        <?= ucfirst($loan['status']) ?>
                                                        <?php if ($loan['processed_by_name']): ?>
                                                            <br><small>by <?= htmlspecialchars($loan['processed_by_name']) ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
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
                                                                    <form method="POST" class="dropdown-item">
                                                                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                                        <input type="hidden" name="new_status" value="approved">
                                                                        <button type="submit" name="update_status" class="btn btn-link p-0 text-start w-100">
                                                                            <i class="fas fa-check-circle me-1 text-success"></i> Approve
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" class="dropdown-item">
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
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?= $loan['id'] ?>)">
                                                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this loan?</p>
                    <p class="fw-bold">This action cannot be undone and will also delete all related repayments!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteBtn" class="btn btn-danger">Delete Loan</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete confirmation
        function confirmDelete(loanId) {
            const deleteBtn = document.getElementById('deleteBtn');
            deleteBtn.href = `loanslist.php?delete=${loanId}`;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>