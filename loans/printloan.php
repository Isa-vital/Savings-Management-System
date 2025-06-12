<?php
require_once __DIR__ . '/../config.php';      // For $pdo, BASE_URL, APP_NAME, sanitize()
require_once __DIR__ . '/../helpers/auth.php';

require_login(); // Redirects if not logged in

// Only allow access for Core Admins and Administrators
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";

    // Redirect based on role
    if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header("Location: " . BASE_URL . "members/my_savings.php");
    } else {
        header("Location: " . BASE_URL . "landing.php");
    }
    exit;
}

// Page content for Core Admins and Administrators continues below...
$loanId = (int)$_GET['id'];

try {
    // Get loan details with additional information
    $stmt = $conn->prepare("
        SELECT 
            l.*, 
            m.member_name, m.phone, m.email, m.member_number,
            m.physical_address, m.id_number,
            s.staff_name as processed_by_name,
            lt.name as loan_type_name,
            (l.amount + (l.amount * l.interest_rate / 100)) as total_repayable,
            (SELECT SUM(amount) FROM loan_repayments WHERE loan_id = l.id) as total_paid
        FROM loans l
        JOIN members m ON l.member_id = m.id
        LEFT JOIN staff s ON l.processed_by = s.id
        LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
        WHERE l.id = :id
    ");
    $stmt->bindParam(':id', $loanId, PDO::PARAM_INT);
    $stmt->execute();
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        die("Loan not found");
    }

    // Get repayment schedule (if available)
    $stmt = $conn->prepare("
        SELECT * FROM loan_repayment_schedule
        WHERE loan_id = :loan_id
        ORDER BY due_date ASC
    ");
    $stmt->bindParam(':loan_id', $loanId, PDO::PARAM_INT);
    $stmt->execute();
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get actual repayments
    $stmt = $conn->prepare("
        SELECT * FROM loan_repayments
        WHERE loan_id = :loan_id
        ORDER BY payment_date DESC
    ");
    $stmt->bindParam(':loan_id', $loanId, PDO::PARAM_INT);
    $stmt->execute();
    $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Set headers for PDF download (optional)
// header('Content-Type: application/pdf');
// header('Content-Disposition: attachment; filename="loan_'.$loanId.'_details.pdf"');

// Define getSaccoName() if not already defined
if (!function_exists('getSaccoName')) {
    function getSaccoName() {
        // Replace this with the actual logic to get your SACCO name, e.g. from config or database
        return defined('APP_NAME') ? APP_NAME : 'SACCO Name';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan #<?= $loanId ?> - Print View</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .print-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .logo {
            max-width: 150px;
            max-height: 80px;
        }
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .section-title {
            background-color: #f5f5f5;
            padding: 5px 10px;
            font-weight: bold;
            border-left: 4px solid #333;
            margin-bottom: 10px;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }
        .col-6 {
            width: 50%;
            box-sizing: border-box;
            padding: 0 5px;
        }
        .label {
            font-weight: bold;
            min-width: 120px;
            display: inline-block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #333;
            font-size: 0.8em;
            text-align: center;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                font-size: 12pt;
            }
            .print-container {
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Header with SACCO logo and info -->
        <div class="header">
            <img src="../assets/images/logo.png" alt="SACCO Logo" class="logo">
            <h2><?= htmlspecialchars(getSaccoName()) ?></h2>
            <p>Loan Details Document</p>
            <p>Generated on: <?= date('d M Y H:i') ?></p>
        </div>

        <!-- Loan Information Section -->
        <div class="section">
            <div class="section-title">Loan Information</div>
            <div class="row">
                <div class="col-6">
                    <p><span class="label">Loan Number:</span> <?= $loanId ?></p>
                    <p><span class="label">Member Name:</span> <?= htmlspecialchars($loan['member_name']) ?></p>
                    <p><span class="label">Member No:</span> <?= htmlspecialchars($loan['member_number']) ?></p>
                    <p><span class="label">ID Number:</span> <?= htmlspecialchars($loan['id_number']) ?></p>
                </div>
                <div class="col-6">
                    <p><span class="label">Loan Type:</span> <?= htmlspecialchars($loan['loan_type_name'] ?? $loan['loan_type']) ?></p>
                    <p><span class="label">Loan Amount:</span> <?= number_format($loan['amount'], 2) ?> UGX</p>
                    <p><span class="label">Interest Rate:</span> <?= $loan['interest_rate'] ?>%</p>
                    <p><span class="label">Total Repayable:</span> <?= number_format($loan['total_repayable'], 2) ?> UGX</p>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <p><span class="label">Application Date:</span> <?= formatDate($loan['application_date']) ?></p>
                    <p><span class="label">Approval Date:</span> <?= formatDate($loan['approval_date']) ?></p>
                </div>
                <div class="col-6">
                    <p><span class="label">Disbursement Date:</span> <?= formatDate($loan['disbursement_date']) ?></p>
                    <p><span class="label">Due Date:</span> <?= formatDate($loan['due_date']) ?></p>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <p><span class="label">Loan Purpose:</span></p>
                    <p><?= nl2br(htmlspecialchars($loan['purpose'])) ?></p>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <p><span class="label">Loan Status:</span> <?= $loan['status'] ?></p>
                </div>
                <div class="col-6">
                    <p><span class="label">Processed By:</span> <?= htmlspecialchars($loan['processed_by_name'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>

        <!-- Repayment Schedule Section -->
        <?php if (!empty($schedule)): ?>
        <div class="section">
            <div class="section-title">Repayment Schedule</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th>Amount Due</th>
                        <th>Principal</th>
                        <th>Interest</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $index => $installment): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= formatDate($installment['due_date']) ?></td>
                        <td class="text-right"><?= number_format($installment['amount_due'], 2) ?> UGX</td>
                        <td class="text-right"><?= number_format($installment['principal_amount'], 2) ?> UGX</td>
                        <td class="text-right"><?= number_format($installment['interest_amount'], 2) ?> UGX</td>
                        <td><?= $installment['status'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="2" class="text-right"><strong>Total:</strong></td>
                        <td class="text-right"><strong><?= number_format($loan['total_repayable'], 2) ?> UGX</strong></td>
                        <td class="text-right"><strong><?= number_format($loan['amount'], 2) ?> UGX</strong></td>
                        <td class="text-right"><strong><?= number_format($loan['total_repayable'] - $loan['amount'], 2) ?> UGX</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Repayment History Section -->
        <div class="section">
            <div class="section-title">Repayment History</div>
            <?php if (!empty($repayments)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Receipt No.</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repayments as $index => $payment): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= formatDate($payment['payment_date']) ?></td>
                            <td class="text-right"><?= number_format($payment['amount'], 2) ?> UGX</td>
                            <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                            <td><?= htmlspecialchars($payment['receipt_number']) ?></td>
                            <td><?= htmlspecialchars($payment['received_by']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="2" class="text-right"><strong>Total Paid:</strong></td>
                            <td class="text-right"><strong><?= number_format($loan['total_paid'] ?? 0, 2) ?> UGX</strong></td>
                            <td colspan="3"></td>
                        </tr>
                        <?php if (isset($loan['total_repayable'])): ?>
                        <tr>
                            <td colspan="2" class="text-right"><strong>Balance:</strong></td>
                            <td class="text-right"><strong><?= number_format($loan['total_repayable'] - ($loan['total_paid'] ?? 0), 2) ?> UGX</strong></td>
                            <td colspan="3"></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No repayment history found for this loan.</p>
            <?php endif; ?>
        </div>

        <!-- Summary Section -->
        <div class="section">
            <div class="section-title">Loan Summary</div>
            <div class="row">
                <div class="col-6">
                    <p><span class="label">Principal Amount:</span> <?= number_format($loan['amount'], 2) ?> UGX</p>
                    <p><span class="label">Total Interest:</span> <?= number_format($loan['total_repayable'] - $loan['amount'], 2) ?> UGX</p>
                </div>
                <div class="col-6">
                    <p><span class="label">Total Repayable:</span> <?= number_format($loan['total_repayable'], 2) ?> UGX</p>
                    <p><span class="label">Total Paid:</span> <?= number_format($loan['total_paid'] ?? 0, 2) ?> UGX</p>
                    <p><span class="label">Outstanding Balance:</span> <?= number_format($loan['total_repayable'] - ($loan['total_paid'] ?? 0), 2) ?> UGX</p>
                </div>
            </div>
        </div>

        <!-- Signatures Section -->
        <div class="section">
            <div class="section-title">Authorized Signatures</div>
            <div class="row" style="margin-top: 50px;">
                <div class="col-6">
                    <p class="signature-line">_________________________</p>
                    <p>Member's Signature</p>
                </div>
                <div class="col-6">
                    <p class="signature-line">_________________________</p>
                    <p>SACCO Representative</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer generated document. No signature is required.</p>
            <p>Printed on: <?= date('d M Y H:i') ?></p>
        </div>

        <!-- Print Button (visible only when not printing) -->
        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" class="btn btn-primary">Print Document</button>
            <button onclick="window.close()" class="btn btn-secondary">Close Window</button>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        window.onload = function() {
            // Uncomment to auto-print
            // setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>