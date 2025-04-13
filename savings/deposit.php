<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/auth.php';

// Authentication check - only allow logged in users
require_login();

// Check if member ID is provided
if (!isset($_GET['member_id'])) {
    $_SESSION['error'] = "Member ID not specified";
    header('Location: memberslist.php');
    exit();
}

$member_id = intval($_GET['member_id']);

try {
    // Fetch member details
    $stmt = $pdo->prepare("SELECT id, member_no, full_name FROM memberz WHERE id = :member_id");
    $stmt->execute([':member_id' => $member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['error'] = "Member not found";
        header('Location: savingssystem/members/memberslist.php');
        exit();
    }

    // Process deposit form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deposit'])) {
        $pdo->beginTransaction();
        
        try {
            $amount = floatval($_POST['amount']);
            $date = sanitize($_POST['date'] ?? '');
            $receipt_no = sanitize($_POST['receipt_no'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            $deposit_type = sanitize($_POST['deposit_type'] ?? 'regular');

            // Validate required fields
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than 0");
            }
            
            if (empty($date)) {
                throw new Exception("Date is required");
            }

            // Insert new deposit record
            $stmt = $pdo->prepare("INSERT INTO savings 
                (member_id, amount, date, receipt_no, notes, deposit_type, recorded_by) 
                VALUES (:member_id, :amount, :date, :receipt_no, :notes, :deposit_type, :recorded_by)");
            
            $stmt->execute([
                ':member_id' => $member_id,
                ':amount' => $amount,
                ':date' => $date,
                ':receipt_no' => $receipt_no,
                ':notes' => $notes,
                ':deposit_type' => $deposit_type,
                ':recorded_by' => $_SESSION['user']['id']
            ]);
            
            $pdo->commit();
            $_SESSION['success'] = "Deposit recorded successfully";
            header("Location: deposit.php?member_id=$member_id");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    // Fetch savings history
    $stmt = $pdo->prepare("SELECT * FROM savings WHERE member_id = :member_id ORDER BY date DESC");
    $stmt->execute([':member_id' => $member_id]);
    $savings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total savings
    $total_savings = array_sum(array_column($savings, 'amount'));

} catch (PDOException $e) {
    error_log("Database error in deposit.php: " . $e->getMessage());
    $_SESSION['error'] = "A database error occurred. Please try again.";
    header('Location: ../members/memberslist.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Deposit - <?= htmlspecialchars($member['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .member-info-card {
            border-left: 5px solid #28a745;
        }
        .savings-table th {
            background-color: #f8f9fa;
        }
        .total-savings {
            font-size: 1.2rem;
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
                        <i class="fas fa-money-bill-wave me-2"></i>Record Deposit
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="memberslist.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Members
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
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
                                <p class="fw-bold total-savings">UGX <?= htmlspecialchars(number_format($total_savings, 2)) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>New Deposit
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Amount (UGX)</label>
                                        <input type="number" class="form-control" name="amount" 
                                            min="1000" step="100" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Date</label>
                                        <input type="date" class="form-control" name="date" 
                                            max="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Receipt Number</label>
                                        <input type="text" class="form-control" name="receipt_no" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Deposit Type</label>
                                        <select class="form-select" name="deposit_type" required>
                                            <option value="regular">Regular Savings</option>
                                            <option value="special">Special Deposit</option>
                                            <option value="registration">Registration Fee</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Notes</label>
                                        <textarea class="form-control" name="notes" rows="2"></textarea>
                                    </div>
                                    
                                    <button type="submit" name="add_deposit" class="btn btn-success w-100 py-2 fw-bold">
                                        <i class="fas fa-save me-1"></i> Record Deposit
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Deposit History
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($savings)): ?>
                                    <div class="alert alert-info">No deposit records found</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table savings-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Receipt No</th>
                                                    <th>Type</th>
                                                    <th>Recorded By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($savings as $deposit): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars(date('d M Y', strtotime($deposit['date']))) ?></td>
                                                        <td class="fw-bold">UGX <?= htmlspecialchars(number_format($deposit['amount'], 2)) ?></td>
                                                        <td><?= htmlspecialchars($deposit['receipt_no']) ?></td>
                                                        <td><?= htmlspecialchars(ucfirst($deposit['deposit_type'])) ?></td>
                                                        <td><?= htmlspecialchars(getUserNameById($pdo, $deposit['recorded_by'])) ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set default date to today
        document.querySelector('input[name="date"]').valueAsDate = new Date();
        
        // Format amount field on input
        document.querySelector('input[name="amount"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>