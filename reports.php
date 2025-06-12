<?php
// Session is now started by config.php
// session_start();

// Verify the session file exists and is writable
// This check might be problematic if session.save_path is not standard or accessible for direct check.
// Consider removing if it causes issues or if server config ensures writability.
// For now, commenting out as config.php handles session start, and this check might be too strict/problematic.
/*
if (!file_exists(session_save_path()) || !is_writable(session_save_path())) {
die('Session directory not writable: ' . session_save_path());
*/

// Standardize session check
// config.php should be included first to make BASE_URL available.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/auth.php'; // Include the new auth helpers

// Session path debugging block removed.

// BASE_URL is now expected to be reliably defined in config.php, so local fallback is removed.

if (!isset($_SESSION['user']['id'])) { // Check the new session structure
    // Redirect to landing page if not logged in, as login page is for explicit login action.
    // Or, redirect to login page if that's preferred flow. Landing page seems more user-friendly.
    header("Location: " . BASE_URL . "landing.php");
    exit;
}

// $pdo is already available from config.php, so no need for includes/database.php or $pdo=$conn;

// Debug session - focus on the user part
error_log("Index session data for user: " . print_r($_SESSION['user'] ?? 'No user session', true));

// Check if the user has the required role(s) for this admin dashboard
if (!has_role(['Core Admin', 'Administrator'])) {
    // If logged in but not an admin/core_admin, redirect to landing or a member dashboard.
    $_SESSION['error_message'] = "You do not have permission to access this dashboard."; // Use the new session key
    // Potentially redirect to a member-specific dashboard if one exists and user is a member
    if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header('Location: ' . BASE_URL . 'members/my_savings.php'); // Example member page
    } else {
        header('Location: ' . BASE_URL . 'landing.php'); // Default redirect for non-privileged users
    }
    exit;
}


// Verify database connection
try {
    $test = $pdo->query("SELECT 1");
} catch (PDOException $e) {
    die("<h2>Database Connection Failed</h2><p>".htmlspecialchars($e->getMessage())."</p>");
}

// Initialize variables with modern filtering
$report_type = isset($_GET['report_type']) 
    ? htmlspecialchars($_GET['report_type']) 
    : 'savings';

$status_filter = isset($_GET['status']) 
    ? htmlspecialchars($_GET['status']) 
    : '';

// Validate dates
$from_date = isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])
    ? $_GET['from_date']
    : date('Y-m-01');

$to_date = isset($_GET['to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])
    ? $_GET['to_date']
    : date('Y-m-d');

// Member ID should be an integer
$member_id = isset($_GET['member_id']) 
    ? (int)$_GET['member_id'] 
    : null;

// Validate dates
if (!strtotime($from_date) || !strtotime($to_date)) {
    die("Invalid date format");
}

// Initialize data arrays
$data = [];
$summary = [];
$members = [];

// Get all members for dropdown
try {
    $stmt = $pdo->query("SELECT id, member_no, full_name FROM memberz ORDER BY full_name");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Member query failed: ".$e->getMessage());
    $members = [];
}

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

            if ($member_id) {
                $query .= " AND s.member_id = :member_id";
                $params[':member_id'] = $member_id;
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

            if ($member_id) {
                $query .= " AND l.member_id = :member_id";
                $params[':member_id'] = $member_id;
            }

            if ($status_filter) {
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

        default:
            die("Invalid report type");
    }
} catch (PDOException $e) {
    die("<h2>Report Generation Failed</h2><p>".htmlspecialchars($e->getMessage())."</p>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Reports Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
            --dark-color: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
           /* font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif; */
        }
        
    
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .badge-success {
            background-color: var(--success-color);
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: #212529;
        }
        
        .badge-primary {
            background-color: var(--primary-color);
        }
        
        .summary-card {
            border-left: 0.25rem solid;
            transition: transform 0.3s;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
        }
        
        .savings-card {
            border-left-color: var(--success-color);
        }
        
        .loans-card {
            border-left-color: var(--warning-color);
        }
        
        .members-card {
            border-left-color: var(--info-color);
        }
        
        .dataTables_wrapper {
            padding: 0;
        }
        
        .dataTables_filter input {
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .table-responsive {
            border-radius: 0.35rem;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fc;
            color: var(--dark-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .filter-section {
            background-color: white;
            border-radius: 0.35rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .export-btn {
            border-radius: 0.35rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include __DIR__ . '/partials/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-lg-9 ms-sm-auto px-md-4 py-4">
                <!-- Page Heading -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 mb-0 text-gray-800">
                        <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>
                        Reports Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="exportExcel">
                                <i class="bi bi-file-earmark-excel me-1"></i> Excel
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="exportPDF">
                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Report Type Navigation -->
                <div class="card mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Report Selection</h6>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-pills nav-fill">
                            <li class="nav-item">
                                <a class="nav-link <?= $report_type === 'savings' ? 'active' : '' ?>" 
                                   href="?report_type=savings">
                                   <i class="bi bi-piggy-bank me-2"></i>Savings Report
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $report_type === 'loans' ? 'active' : '' ?>" 
                                   href="?report_type=loans">
                                   <i class="bi bi-cash-coin me-2"></i>Loans Report
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $report_type === 'members' ? 'active' : '' ?>" 
                                   href="?report_type=members">
                                   <i class="bi bi-people-fill me-2"></i>Members Report
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" class="form-control" 
                                   value="<?= htmlspecialchars($from_date) ?>" 
                                   <?= $report_type === 'members' ? 'disabled' : '' ?>>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" class="form-control" 
                                   value="<?= htmlspecialchars($to_date) ?>" 
                                   <?= $report_type === 'members' ? 'disabled' : '' ?>>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Member</label>
                            <select name="member_id" class="form-select" <?= $report_type === 'members' ? 'disabled' : '' ?>>
                                <option value="">All Members</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= htmlspecialchars($m['id']) ?>" 
                                        <?= ($member_id == $m['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['member_no']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($report_type === 'loans'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Loan Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-12 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i> Apply Filters
                            </button>
                            <a href="reports.php?report_type=<?= htmlspecialchars($report_type) ?>" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                            </a>
                        </div>
                    </form>
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

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#dataTable').DataTable({
                responsive: true,
                dom: '<"top"lf>rt<"bottom"ip>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search records...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
            
            // Export to Excel
            $('#exportExcel').click(function() {
                const table = document.getElementById('dataTable');
                const workbook = XLSX.utils.table_to_book(table, {sheet: "Report"});
                XLSX.writeFile(workbook, '<?= $report_type ?>_report_<?= date('Ymd') ?>.xlsx');
            });
            
            // Export to PDF
            $('#exportPDF').click(function() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                doc.text('<?= ucfirst($report_type) ?> Report - <?= date('M j, Y') ?>', 14, 15);
                doc.autoTable({
                    html: '#dataTable',
                    startY: 25,
                    theme: 'grid',
                    headStyles: {
                        fillColor: [78, 115, 223],
                        textColor: 255,
                        fontStyle: 'bold'
                    },
                    alternateRowStyles: {
                        fillColor: [248, 249, 252]
                    }
                });
                
                doc.save('<?= $report_type ?>_report_<?= date('Ymd') ?>.pdf');
            });
            
            // Date validation
            $('form').submit(function(e) {
                const fromDate = new Date($('[name="from_date"]').val());
                const toDate = new Date($('[name="to_date"]').val());
                
                if (fromDate > toDate) {
                    alert('End date must be after start date!');
                    e.preventDefault();
                }
            });
        });
    </script>
     <?php include 'partials/footer.php'; ?>
</body>
</html>