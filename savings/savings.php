<?php
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    redirect('../index.php');
}

// Check if member ID is provided
if (!isset($_GET['member_id'])) {
    $_SESSION['error'] = "Member ID not specified";
    redirect('memberslist.php');
}

$member_id = intval($_GET['member_id']);

// Fetch member details
$stmt = $conn->prepare("SELECT id, member_no, full_name FROM members WHERE id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

if (!$member) {
    $_SESSION['error'] = "Member not found";
    redirect('memberslist.php');
}

// Handle savings record deletion
if (isset($_GET['delete_saving'])) {
    $saving_id = intval($_GET['delete_saving']);
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM savings WHERE id = ?");
        $stmt->bind_param("i", $saving_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Savings record deleted successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting savings record: " . $e->getMessage();
    }
    redirect('savings.php?member_id=' . $member_id);
}

// Process new savings record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_saving'])) {
    $conn->begin_transaction();
    
    try {
        $amount = floatval($_POST['amount']);
        $date = sanitize($_POST['date'] ?? '');
        $receipt_no = sanitize($_POST['receipt_no'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        // Validate required fields
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }
        
        if (empty($date)) {
            throw new Exception("Date is required");
        }

        // Insert new savings record
        $stmt = $conn->prepare("INSERT INTO savings 
            (member_id, amount, date, receipt_no, notes) 
            VALUES (?, ?, ?, ?, ?)");
        
        $stmt->bind_param("idsss", 
            $member_id,
            $amount,
            $date,
            $receipt_no,
            $notes
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to add savings record");
        }
        
        $conn->commit();
        $_SESSION['success'] = "Savings record added successfully";
        redirect('savings.php?member_id=' . $member_id);
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Fetch savings history
$stmt = $conn->prepare("SELECT * FROM savings WHERE member_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$savings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total savings
$total_savings = array_sum(array_column($savings, 'amount'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Savings - Ugandan SACCO</title>
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
        .total-savings {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .member-info-card {
            border-left: 5px solid #28a745;
        }
    </style>
</head>
<body>
    <?php include '../partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="uganda-flag"></div>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-wallet me-2"></i>Manage Savings
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="view.php?id=<?= $member['id'] ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Member
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="card member-info-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1 text-muted">Member Number</p>
                                <p class="fw-bold"><?= htmlspecialchars($member['member_no']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1 text-muted">Full Name</p>
                                <p class="fw-bold"><?= htmlspecialchars($member['full_name']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1 text-muted">Total Savings</p>
                                <p class="fw-bold total-savings">UGX <?= number_format($total_savings, 2) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>Add New Savings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label required-field">Amount (UGX)</label>
                                        <input type="number" class="form-control" name="amount" 
                                            min="1000" step="100" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required-field">Date</label>
                                        <input type="date" class="form-control" name="date" 
                                            max="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Receipt Number</label>
                                        <input type="text" class="form-control" name="receipt_no">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="notes" rows="2"></textarea>
                                    </div>
                                    
                                    <button type="submit" name="add_saving" class="btn btn-success w-100">
                                        <i class="fas fa-save me-1"></i> Add Savings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Savings History
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($savings)): ?>
                                    <p class="text-muted">No savings records found</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Receipt No</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($savings as $saving): ?>
                                                    <tr>
                                                        <td><?= date('d M Y', strtotime($saving['date'])) ?></td>
                                                        <td>UGX <?= number_format($saving['amount'], 2) ?></td>
                                                        <td><?= htmlspecialchars($saving['receipt_no']) ?: 'N/A' ?></td>
                                                        <td>
                                                            <button onclick="confirmDelete(<?= $saving['id'] ?>)" 
                                                                    class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
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
                    <p>Are you sure you want to delete this savings record?</p>
                    <p class="fw-bold">This action cannot be undone!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteBtn" class="btn btn-danger">Delete Record</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete confirmation
        function confirmDelete(savingId) {
            const deleteBtn = document.getElementById('deleteBtn');
            deleteBtn.href = `savings.php?member_id=<?= $member_id ?>&delete_saving=${savingId}`;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        // Set default date to today
        document.querySelector('input[name="date"]').valueAsDate = new Date();
    </script>
</body>
</html>