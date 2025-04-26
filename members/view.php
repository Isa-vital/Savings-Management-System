<?php
session_start();

// Authentication check
if (!isset($_SESSION['admin']['id'])) {
    header("Location: /savingssystem/auth/login.php");
    exit;
}

// Database connection
require_once __DIR__ . '/../config.php';
require_once '../includes/database.php';

// Get member ID from URL
$memberId = $_GET['id'] ?? null;
if (!$memberId || !is_numeric($memberId)) {
    header("Location: memberslist.php");
    exit;
}

// Fetch member details
try {
    // Member basic info
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            (SELECT SUM(amount) FROM savings WHERE member_id = m.member_no) as total_savings,
            (SELECT COUNT(*) FROM savings WHERE member_id = m.member_no) as deposit_count
        FROM memberz m
        WHERE m.member_no = ?
    ");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception("Member not found");
    }

    // Transaction history
    $stmt = $pdo->prepare("
        SELECT * FROM savings
        WHERE member_id = ?
        ORDER BY transaction_date DESC
    ");
    $stmt->execute([$memberId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attendance history
    $stmt = $pdo->prepare("
        SELECT * FROM attendance
        WHERE member_id = ?
        ORDER BY meeting_date DESC
    ");
    $stmt->execute([$memberId]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load member data";
    header("Location: members.php");
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: members.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Member Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .stat-card {
            border-left: 0.25rem solid #4e73df;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .transaction-row {
            border-left: 4px solid transparent;
            transition: all 0.2s;
        }
        .transaction-row.deposit {
            border-left-color: #1cc88a;
        }
        .transaction-row.withdrawal {
            border-left-color: #e74a3b;
        }
        .transaction-row:hover {
            background-color: #f8f9fa;
        }
        .attendance-present {
            background-color: rgba(28, 200, 138, 0.1);
        }
        .attendance-absent {
            background-color: rgba(231, 74, 59, 0.1);
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            color: #4e73df;
            border-bottom: 2px solid #4e73df;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <?php include 'partials/navbar.php'; ?>
        
        <div class="row">
            <?php include 'partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <!-- Back button -->
                <div class="d-flex mb-3">
                    <a href="members.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Members
                    </a>
                </div>

                <!-- Profile Header -->
                <div class="profile-header p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <div class="bg-white rounded-circle p-2 shadow-sm" style="width: 100px; height: 100px;">
                                <i class="fas fa-user fa-4x text-primary"></i>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h2 class="mb-1"><?= htmlspecialchars($member['full_name']) ?></h2>
                            <p class="mb-1">
                                <i class="fas fa-id-card me-1"></i> ID: <?= $member['member_no'] ?>
                                <span class="mx-2">|</span>
                                <i class="fas fa-phone me-1"></i> <?= $member['phone'] ?? 'N/A' ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-envelope me-1"></i> <?= $member['email'] ?? 'N/A' ?>
                                <span class="mx-2">|</span>
                                <i class="fas fa-map-marker-alt me-1"></i> <?= $member['address'] ?? 'N/A' ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group">
                                <a href="editmember.php?id=<?= $member['member_no'] ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <button class="btn btn-outline-primary" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Savings</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= number_format($member['total_savings'] ?? 0, 2) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Deposit Count</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $member['deposit_count'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Member Since</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= date('M j, Y', strtotime($member['registration_date'])) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Status</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <span class="badge bg-<?= $member['is_active'] ? 'success' : 'danger' ?>">
                                                <?= $member['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-4" id="memberTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                            <i class="fas fa-exchange-alt me-1"></i> Transactions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                            <i class="fas fa-calendar-check me-1"></i> Attendance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                            <i class="fas fa-file-alt me-1"></i> Documents
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="memberTabsContent">
                    <!-- Transactions Tab -->
                    <div class="tab-pane fade show active" id="transactions" role="tabpanel">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-exchange-alt me-1"></i> Transaction History
                                </h6>
                                <a href="addtransaction.php?member_id=<?= $memberId ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i> Add Transaction
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($transactions)): ?>
                                    <div class="alert alert-info">No transactions found for this member.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Description</th>
                                                    <th>Receipt No.</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transactions as $txn): ?>
                                                    <tr class="transaction-row <?= strtolower($txn['type']) ?>">
                                                        <td><?= date('M j, Y', strtotime($txn['transaction_date'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $txn['type'] === 'Deposit' ? 'success' : 'danger' ?>">
                                                                <?= $txn['type'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= number_format($txn['amount'], 2) ?></td>
                                                        <td><?= htmlspecialchars($txn['description'] ?? 'N/A') ?></td>
                                                        <td><?= $txn['receipt_no'] ?? 'N/A' ?></td>
                                                        <td>
                                                            <a href="edittransaction.php?id=<?= $txn['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
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

                    <!-- Attendance Tab -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-calendar-check me-1"></i> Attendance Record
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($attendance)): ?>
                                    <div class="alert alert-info">No attendance records found for this member.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Day</th>
                                                    <th>Status</th>
                                                    <th>Meeting Type</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($attendance as $record): ?>
                                                    <tr class="<?= $record['attended'] ? 'attendance-present' : 'attendance-absent' ?>">
                                                        <td><?= date('M j, Y', strtotime($record['meeting_date'])) ?></td>
                                                        <td><?= date('l', strtotime($record['meeting_date'])) ?></td>
                                                        <td>
                                                            <?php if ($record['attended']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fas fa-check"></i> Present
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">
                                                                    <i class="fas fa-times"></i> Absent
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= $record['meeting_type'] ?? 'Regular' ?></td>
                                                        <td><?= htmlspecialchars($record['notes'] ?? 'N/A') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Tab -->
                    <div class="tab-pane fade" id="documents" role="tabpanel">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-file-alt me-1"></i> Member Documents
                                </h6>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                    <i class="fas fa-upload me-1"></i> Upload Document
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Documents feature coming soon. You'll be able to upload ID copies, signatures, and other files.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select class="form-select">
                                <option>ID Copy</option>
                                <option>Signature</option>
                                <option>Contract</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select File</label>
                            <input type="file" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activate the first tab
        var firstTab = new bootstrap.Tab(document.getElementById('transactions-tab'));
        firstTab.show();
    </script>
</body>
</html>