<?php
// Session is expected to be started by config.php
require_once __DIR__ . '/config.php'; // Ensures $pdo, BASE_URL, APP_NAME
require_once __DIR__ . '/helpers/auth.php'; // For require_login, has_role

require_login(); // Ensures user is logged in, redirects to login if not.

// Role check: Reports should be for admins
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to view reports.";
    // Redirect to a safe page
    header("Location: " . BASE_URL . (has_role('Member') ? "members/my_savings.php" : "landing.php"));
    exit;
}

// Initialize all filter variables with safe defaults
$report_type = $_GET['report_type'] ?? 'savings';  // Default to savings report
$from_date = $_GET['from_date'] ?? date('Y-m-01'); // Default to start of current month
$to_date = $_GET['to_date'] ?? date('Y-m-d');      // Default to today
$member_id = $_GET['member_id'] ?? null;
$status_filter = $_GET['status'] ?? null;

// Validate report type
$valid_report_types = ['savings', 'loans', 'members'];
if (!in_array($report_type, $valid_report_types)) {
    $report_type = 'savings'; // Fallback to default
}

// Get all members for dropdown
try {
    $stmt = $pdo->query("SELECT id, member_no, full_name FROM memberz ORDER BY full_name");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Member query failed: ".$e->getMessage());
    $members = [];
}

// Initialize report data
$data = [];
$summary = [];

// Process report data
try {
    switch ($report_type) {
        case 'savings':
            $query = "SELECT s.*, m.member_no, m.full_name 
                     FROM savings s
                     JOIN memberz m ON s.member_id = m.id
                     WHERE s.date BETWEEN :from_date AND :to_date";
            
            $params = [
                ':from_date' => $from_date,
                ':to_date' => $to_date
            ];

            if ($member_id && is_numeric($member_id)) {
                $query .= " AND s.member_id = :member_id";
                $params[':member_id'] = (int)$member_id;
            }

            $query .= " ORDER BY s.date DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            // Summary
            $summary_query = "SELECT 
                            COUNT(*) as total_transactions,
                            SUM(amount) as total_amount
                            FROM savings
                            WHERE date BETWEEN :from_date AND :to_date";
            $summary_stmt = $pdo->prepare($summary_query);
            $summary_stmt->execute([':from_date' => $from_date, ':to_date' => $to_date]);
            $summary = $summary_stmt->fetch() ?: ['total_transactions' => 0, 'total_amount' => 0];
            break;

        case 'loans':
            $query = "SELECT l.*, m.member_no, m.full_name 
                     FROM loans l
                     JOIN memberz m ON l.member_id = m.id
                     WHERE l.application_date BETWEEN :from_date AND :to_date";
            
            $params = [
                ':from_date' => $from_date,
                ':to_date' => $to_date
            ];

            if ($member_id && is_numeric($member_id)) {
                $query .= " AND l.member_id = :member_id";
                $params[':member_id'] = (int)$member_id;
            }

            if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
                $query .= " AND l.status = :status";
                $params[':status'] = $status_filter;
            }

            $query .= " ORDER BY l.application_date DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            // Summary
            $summary_query = "SELECT 
                            status,
                            COUNT(*) as count,
                            SUM(amount) as total_amount
                            FROM loans
                            WHERE application_date BETWEEN :from_date AND :to_date
                            GROUP BY status";
            $summary_stmt = $pdo->prepare($summary_query);
            $summary_stmt->execute([':from_date' => $from_date, ':to_date' => $to_date]);
            $summary = $summary_stmt->fetchAll();
            break;

        case 'members':
            $query = "SELECT m.*, 
                     COUNT(DISTINCT s.id) as savings_count,
                     COUNT(DISTINCT l.id) as loans_count
                     FROM memberz m
                     LEFT JOIN savings s ON m.id = s.member_id
                     LEFT JOIN loans l ON m.id = l.member_id
                     GROUP BY m.id
                     ORDER BY m.full_name";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;
    }
} catch (PDOException $e) {
    error_log("Report generation failed: " . $e->getMessage());
    $_SESSION['error_message'] = "Error generating report. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        .stat-card {
            transition: transform 0.3s;
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-card.members {
            border-left-color: #4e73df;
        }
        .stat-card.savings {
            border-left-color: #1cc88a;
        }
        .stat-card.loans {
            border-left-color: #f6c23e;
        }
        .recent-transactions {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'partials/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>Financial Reports</h2>

            <!-- Filters -->
            <form class="row g-3 mb-4" method="GET">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Member</label>
                    <select name="member_id" class="form-select">
                        <option value="">-- All Members --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['member_no'] ?>" <?= ($member_id == $m['member_no']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <?php if ($report_type === 'savings'): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card savings-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Savings</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            UGX <?= number_format($summary['total_amount'] ?? 0, 2) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-piggy-bank fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Transactions</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $summary['total_transactions'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-list-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Start Date</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= date('M j, Y', strtotime($from_date)) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar-date fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            End Date</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= date('M j, Y', strtotime($to_date)) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($report_type === 'loans'): ?>
                        <?php foreach ($summary as $stat): ?>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card summary-card loans-card h-100">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1" 
                                                 style="color: <?= 
                                                    $stat['status'] === 'approved' ? 'var(--success-color)' : 
                                                    ($stat['status'] === 'pending' ? 'var(--warning-color)' : '#e74a3b')
                                                 ?>">
                                                <?= ucfirst($stat['status']) ?> Loans
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?= $stat['count'] ?>
                                            </div>
                                            <div class="mt-2 text-xs font-weight-bold">
                                                UGX <?= number_format($stat['total_amount'], 2) ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-cash-stack fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif ($report_type === 'members'): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card members-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Members</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= count($data) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people-fill fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Members</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= count(array_filter($data, fn($m) => $m['status'] === 'active')) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-person-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Savings</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= array_sum(array_column($data, 'savings_count')) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-piggy-bank fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Total Loans</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= array_sum(array_column($data, 'loans_count')) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-cash-coin fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Report Data Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?= ucfirst($report_type) ?> Report Details
                        </h6>
                        <div class="dropdown no-arrow">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical text-gray-400"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuLink">
                                <li><a class="dropdown-item" href="#" id="exportExcel"><i class="bi bi-file-earmark-excel me-2"></i>Export to Excel</a></li>
                                <li><a class="dropdown-item" href="#" id="exportPDF"><i class="bi bi-file-earmark-pdf me-2"></i>Export to PDF</a></li>
                                <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print Report</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($data)): ?>
                            <div class="alert alert-warning text-center py-4">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                No records found for the selected criteria
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead class="thead-light">
                                        <tr>
                                            <?php if ($report_type === 'savings'): ?>
                                                <th>Date</th>
                                                <th>Member</th>
                                                <th>Amount</th>
                                                <th>Receipt No</th>
                                                <th>Received By</th>
                                            <?php elseif ($report_type === 'loans'): ?>
                                                <th>Loan #</th>
                                                <th>Member</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Applied On</th>
                                                <th>Purpose</th>
                                            <?php else: ?>
                                                <th>Member #</th>
                                                <th>Name</th>
                                                <th>Contact</th>
                                                <th>Savings</th>
                                                <th>Loans</th>
                                                <th>Status</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data as $row): ?>
                                        <tr>
                                            <?php if ($report_type === 'savings'): ?>
                                                <td><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($row['full_name']) ?></strong>
                                                    <div class="text-muted small"><?= htmlspecialchars($row['member_no']) ?></div>
                                                </td>
                                                <td class="text-success font-weight-bold">UGX <?= number_format($row['amount'], 2) ?></td>
                                                <td><?= htmlspecialchars($row['receipt_no'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($row['received_by'] ?? 'System') ?></td>
                                            <?php elseif ($report_type === 'loans'): ?>
                                                <td><?= htmlspecialchars($row['loan_number']) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($row['full_name']) ?></strong>
                                                    <div class="text-muted small"><?= htmlspecialchars($row['member_no']) ?></div>
                                                </td>
                                                <td class="font-weight-bold">UGX <?= number_format($row['amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge rounded-pill bg-<?= 
                                                        $row['status'] === 'approved' ? 'success' : 
                                                        ($row['status'] === 'pending' ? 'warning' : 
                                                        ($row['status'] === 'rejected' ? 'danger' : 'info')) 
                                                    ?>">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($row['application_date'])) ?></td>
                                                <td><?= !empty($row['purpose']) ? htmlspecialchars($row['purpose']) : 'N/A' ?></td>
                                            <?php else: ?>
                                                <td><?= htmlspecialchars($row['member_no']) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($row['full_name']) ?></strong>
                                                    <div class="text-muted small"><?= htmlspecialchars($row['email'] ?? '') ?></div>
                                                </td>
                                                <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                                                <td>
                                                    <span class="badge bg-success rounded-pill">
                                                        <?= $row['savings_count'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning text-dark rounded-pill">
                                                        <?= $row['loans_count'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $row['status'] === 'active' ? 'primary' : 'secondary' ?> rounded-pill">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
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

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    document.getElementById("downloadPDF").addEventListener("click", () => {
        const element = document.getElementById("reportTable");
        const reportTitle = "Financial_Report_<?= date('Y-m-d') ?>";
        const opt = {
            margin:       0.5,
            filename:     reportTitle + '.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
        };
        html2pdf().from(element).set(opt).save();
    });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
