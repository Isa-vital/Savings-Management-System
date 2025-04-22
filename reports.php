<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';

// Authentication check
if (!isset($_SESSION['admin']['id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$pdo = $conn;
$members = [];
$transactions = [];
$totalDeposits = 0;
$totalWithdrawals = 0;

try {
    // Fetch all members
    $stmt = $pdo->query("SELECT member_no, full_name FROM memberz ORDER BY full_name");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter parameters
    $member_id = $_GET['member_id'] ?? '';
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');

    // Build query
    $query = "
        SELECT t.*, m.full_name 
        FROM transactions t
        JOIN memberz m ON t.member_id = m.member_no
        WHERE 1=1
    ";
    $params = [];

    if (!empty($member_id)) {
        $query .= " AND t.member_id = :member_id";
        $params[':member_id'] = $member_id;
    }

    if (!empty($from_date) && !empty($to_date)) {
        $query .= " AND DATE(t.transaction_date) BETWEEN :from_date AND :to_date";
        $params[':from_date'] = $from_date;
        $params[':to_date'] = $to_date;
    }

    $query .= " ORDER BY t.transaction_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Calculate totals
    foreach ($transactions as $tx) {
        if ($tx['transaction_type'] === 'deposit') {
            $totalDeposits += $tx['amount'];
        } else {
            $totalWithdrawals += $tx['amount'];
        }
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    error_log("Report Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Transaction Reports</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .badge-deposit {
            background-color: #28a745;
        }
        .badge-withdrawal {
            background-color: #ffc107;
            color: #212529;
        }
        .summary-card {
            border-left: 4px solid;
        }
        .summary-deposits {
            border-left-color: #28a745;
        }
        .summary-withdrawals {
            border-left-color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'partials/navbar.php'; ?>
             <!-- Sidebar -->
             <?php include 'partials/sidebar.php'; ?>
            
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h2 class="h3"><i class="fas fa-file-invoice me-2"></i>Transaction Reports</h2>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="from_date" class="form-control" 
                                       value="<?= htmlspecialchars($from_date) ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="to_date" class="form-control" 
                                       value="<?= htmlspecialchars($to_date) ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Member</label>
                                <select name="member_id" class="form-select">
                                    <option value="">All Members</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?= htmlspecialchars($m['member_no']) ?>" 
                                            <?= ($member_id == $m['member_no']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card summary-card summary-deposits h-100">
                            <div class="card-body">
                                <h5 class="card-title text-success">
                                    <i class="fas fa-money-bill-wave me-2"></i>Total Deposits
                                </h5>
                                <h2 class="card-text">UGX <?= number_format($totalDeposits, 2) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card summary-card summary-withdrawals h-100">
                            <div class="card-body">
                                <h5 class="card-title text-warning">
                                    <i class="fas fa-hand-holding-usd me-2"></i>Total Withdrawals
                                </h5>
                                <h2 class="card-text">UGX <?= number_format($totalWithdrawals, 2) ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Transaction Details
                        </h5>
                        <button id="downloadPDF" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf me-1"></i> Export PDF
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" id="reportTable">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th>Type</th>
                                        <th>Amount (UGX)</th>
                                        <th>Date</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                No transactions found for selected criteria
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $i => $tx): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($tx['full_name']) ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?= $tx['transaction_type'] === 'deposit' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                        <?= ucfirst(htmlspecialchars($tx['transaction_type'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($tx['amount'], 2) ?></td>
                                                <td><?= date('M j, Y H:i', strtotime($tx['transaction_date'])) ?></td>
                                                <td><?= !empty($tx['reference']) ? htmlspecialchars($tx['reference']) : 'N/A' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($transactions)): ?>
                                    <tfoot>
                                        <tr class="table-active">
                                            <td colspan="3" class="text-end"><strong>Totals:</strong></td>
                                            <td><strong>UGX <?= number_format($totalDeposits - $totalWithdrawals, 2) ?></strong></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
        // PDF Export
        document.getElementById('downloadPDF').addEventListener('click', function() {
            const element = document.getElementById('reportTable');
            const opt = {
                margin: 10,
                filename: 'transaction_report_<?= date('Ymd') ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            
            html2pdf().set(opt).from(element).save();
        });

        // Date validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const fromDate = new Date(document.querySelector('[name="from_date"]').value);
            const toDate = new Date(document.querySelector('[name="to_date"]').value);
            
            if (fromDate > toDate) {
                alert('End date must be after start date!');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>