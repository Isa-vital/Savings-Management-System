<?php
session_start();

if (!isset($_SESSION['admin']['id'])) {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

// Check if member_no is provided
if (!isset($_GET['member_no']) || empty($_GET['member_no'])) {
    $_SESSION['error'] = "Member not specified";
    header('Location: memberslist.php');
    exit;
}

$member_no = sanitize($_GET['member_no']);

try {
    // Fetch member details with additional fields
    $stmt = $pdo->prepare("
        SELECT m.*, 
        COALESCE(SUM(s.amount), 0) as total_savings,
        COUNT(l.id) as total_loans
        FROM memberz m
        LEFT JOIN savings s ON m.id = s.member_id
        LEFT JOIN loans l ON m.id = l.member_id
        WHERE m.member_no = ?
        GROUP BY m.id
    ");
    $stmt->execute([$member_no]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['error'] = "Member not found";
        header('Location: memberslist.php');
        exit;
    }

    // Fetch savings history
    $savings_stmt = $pdo->prepare("
        SELECT amount, date, received_by 
        FROM savings 
        WHERE member_id = ?
        ORDER BY date DESC
    ");
    $savings_stmt->execute([$member['id']]);
    $savings = $savings_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch loan history
    $loans_stmt = $pdo->prepare("
        SELECT l.loan_number, l.amount, l.status, l.application_date
        FROM loans l
        WHERE l.member_id = ?
        ORDER BY l.application_date DESC
    ");
    $loans_stmt->execute([$member['id']]);
    $loans = $loans_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: memberslist.php');
    exit;
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mpdf = new \Mpdf\Mpdf();
    $html = generatePdfContent($member, $savings, $loans);
    $mpdf->WriteHTML($html);
    $mpdf->Output('member_'.$member_no.'_'.date('Ymd').'.pdf', 'D');
    exit;
}

function generatePdfContent($member, $savings, $loans) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial; font-size: 12px; }
            h1 { color: #333; text-align: center; }
            .header { margin-bottom: 20px; }
            .section { margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 5px; }
            th { background-color: #f2f2f2; }
            .label { font-weight: bold; }
            .footer { margin-top: 30px; font-size: 10px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?= htmlspecialchars(APP_NAME) ?></h1>
            <h2>Member Details Report</h2>
            <p>Generated on: <?= date('d M Y H:i') ?></p>
        </div>

        <div class="section">
            <h3>Basic Information</h3>
            <table>
                <tr>
                    <td class="label">Member No:</td>
                    <td><?= htmlspecialchars($member['member_no']) ?></td>
                    <td class="label">Full Name:</td>
                    <td><?= htmlspecialchars($member['full_name']) ?></td>
                </tr>
                <tr>
                    <td class="label">Phone:</td>
                    <td><?= htmlspecialchars($member['phone'] ?? 'N/A') ?></td>
                    <td class="label">Email:</td>
                    <td><?= htmlspecialchars($member['email'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td class="label">NIN Number:</td>
                    <td><?= htmlspecialchars($member['nin_number'] ?? 'N/A') ?></td>
                    <td class="label">Status:</td>
                    <td><?= htmlspecialchars($member['status'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td class="label">District:</td>
                    <td><?= htmlspecialchars($member['district'] ?? 'N/A') ?></td>
                    <td class="label">Subcounty:</td>
                    <td><?= htmlspecialchars($member['subcounty'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td class="label">Village:</td>
                    <td><?= htmlspecialchars($member['village'] ?? 'N/A') ?></td>
                    <td class="label">Registration Date:</td>
                    <td><?= !empty($member['reg_date']) ? date('d M Y', strtotime($member['reg_date'])) : 'N/A' ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h3>Financial Summary</h3>
            <table>
                <tr>
                    <td class="label">Total Savings:</td>
                    <td>UGX <?= number_format($member['total_savings'], 2) ?></td>
                    <td class="label">Total Loans:</td>
                    <td><?= $member['total_loans'] ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h3>Savings History</h3>
            <?php if (!empty($savings)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($savings as $index => $saving): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>UGX <?= number_format($saving['amount'], 2) ?></td>
                                <td><?= date('d M Y', strtotime($saving['date'])) ?></td>
                                <td><?= htmlspecialchars($saving['received_by'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No savings records found</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h3>Loan History</h3>
            <?php if (!empty($loans)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Loan Number</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Application Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $index => $loan): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($loan['loan_number']) ?></td>
                                <td>UGX <?= number_format($loan['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($loan['status']) ?></td>
                                <td><?= date('d M Y', strtotime($loan['application_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No loan records found</p>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Report generated by <?= htmlspecialchars(APP_NAME) ?> on <?= date('d M Y H:i') ?></p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Details - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .member-card { border-left: 4px solid #4e73df; }
        .savings-card { border-left: 4px solid #1cc88a; }
        .loans-card { border-left: 4px solid #f6c23e; }
        .info-label { font-weight: 600; color: #5a5c69; }
        .transaction-table th { background-color: #f8f9fc; }
        .profile-img { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 5px solid #e3e6f0; }
    </style>
</head>
<body>
    <?php include '../partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user me-2"></i>Member Details
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="edit.php?member_no=<?= urlencode($member['member_no']) ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-1"></i> Edit Member
                        </a>
                        <a href="view.php?member_no=<?= urlencode($member['member_no']) ?>&export=pdf" class="btn btn-danger me-2">
                            <i class="fas fa-file-pdf me-1"></i> Export PDF
                        </a>
                        <a href="memberslist.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Members
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <!-- Member Profile Card -->
                <div class="card member-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-id-card me-2"></i>Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-4 mb-md-0">
                                <img src="../assets/default-profile.jpg" alt="Profile" class="profile-img mb-3">
                                <h4><?= htmlspecialchars($member['full_name']) ?></h4>
                                <h5 class="text-muted"><?= htmlspecialchars($member['member_no']) ?></h5>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><span class="info-label">Phone:</span> <?= htmlspecialchars($member['phone'] ?? 'N/A') ?></p>
                                        <p><span class="info-label">Email:</span> <?= htmlspecialchars($member['email'] ?? 'N/A') ?></p>
                                        <p><span class="info-label">Gender:</span> <?= htmlspecialchars($member['gender'] ?? 'N/A') ?></p>
                                        <p><span class="info-label">Date of Birth:</span> 
                                            <?= !empty($member['dob']) && $member['dob'] !== '0000-00-00' 
                                                ? date('d M Y', strtotime($member['dob'])) 
                                                : 'N/A' ?>
                                        </p>
                                        <p><span class="info-label">NIN Number:</span> 
                                            <?= !empty($member['nin_number']) 
                                                ? htmlspecialchars($member['nin_number']) 
                                                : 'N/A' ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><span class="info-label">Occupation:</span> <?= htmlspecialchars($member['occupation'] ?? 'N/A') ?></p>
                                        <p><span class="info-label">District:</span> <?= htmlspecialchars($member['district'] ?? 'N/A') ?></p>
                                        <p><span class="info-label">Subcounty:</span> <?= htmlspecialchars($member['subcounty'] ?? 'N/A') ?></p>
                                        <p><span class="info-label">Village:</span> <?= htmlspecialchars($member['village'] ?? 'N/A') ?></p>
                                        <p><span class="info-label">Registration Date:</span> 
                                            <?= !empty($member['reg_date']) && $member['reg_date'] !== '0000-00-00' 
                                                ? date('d M Y', strtotime($member['reg_date'])) 
                                                : 'Not available' ?>
                                        </p>
                                        <p><span class="info-label">Status:</span> 
                                            <?php if (!empty($member['status'])): ?>
                                                <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                    <?= ucfirst(htmlspecialchars($member['status'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Unknown</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card savings-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-piggy-bank me-2"></i>Savings Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <h3 class="text-success">UGX <?= number_format($member['total_savings'], 2) ?></h3>
                                <p class="text-muted">Total Savings Balance</p>
                                <a href="../savings/savings.php?member_no=<?= urlencode($member['member_no']) ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-plus me-1"></i> Add Savings
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card loans-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-hand-holding-usd me-2"></i>Loans Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <h3><?= $member['total_loans'] ?></h3>
                                <p class="text-muted">Total Loans Taken</p>
                                <a href="../loans/newloan.php?member_no=<?= urlencode($member['member_no']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus me-1"></i> New Loan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Savings History -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Savings History
                            </h5>
                            <a href="../savings/savings.php?member_no=<?= urlencode($member['member_no']) ?>" class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table transaction-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Received By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($savings)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No savings records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($savings as $index => $saving): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td class="text-success">UGX <?= number_format($saving['amount'], 2) ?></td>
                                                <td><?= date('d M Y', strtotime($saving['date'])) ?></td>
                                                <td><?= htmlspecialchars($saving['received_by'] ?? 'System') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Loan History -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Loan History
                            </h5>
                            <a href="../loans/loanslist.php?search=<?= urlencode($member['member_no']) ?>" class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table transaction-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Loan Number</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Application Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($loans)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No loan records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($loans as $index => $loan): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($loan['loan_number']) ?></td>
                                                <td>UGX <?= number_format($loan['amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $loan['status'] === 'approved' ? 'success' : 
                                                        ($loan['status'] === 'pending' ? 'warning' : 'danger') 
                                                    ?>">
                                                        <?= ucfirst(htmlspecialchars($loan['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= !empty($member['reg_date']) && $member['reg_date'] !== '0000-00-00' 
    ? date('d M Y', strtotime($member['reg_date'])) 
    : 'N/A' ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
