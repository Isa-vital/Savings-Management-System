<?php
require_once __DIR__ . '/../config.php';

if (!isset($_GET['receipt_no'])) {
    die('No receipt number provided.');
}

$receipt_no = $_GET['receipt_no'];

// Fetch the receipt data
$stmt = $pdo->prepare("SELECT s.*, m.full_name, m.member_no FROM savings s 
                       JOIN memberz m ON s.member_id = m.id 
                       WHERE s.receipt_no = ?");
$stmt->execute([$receipt_no]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die('Receipt not found.');
}

// Get producer's signature (assuming admin with id = 1)
$adminStmt = $pdo->prepare("SELECT signature, username FROM admins WHERE id = 1");
$adminStmt->execute();
$admin = $adminStmt->fetch();
$signature_path = $admin && $admin['signature'] ? 'uploads/signatures/' . $admin['signature'] : null;
$admin_name = $admin['full_name'] ?? 'Producer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Savings Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
        }
        .receipt {
            max-width: 600px;
            margin: 20px auto;
            padding: 30px;
            border: 1px solid #ccc;
            border-radius: 10px;
            background: #fff;
        }
    </style>
</head>
<body>
<div class="receipt">
    <h2 class="text-center">SAVINGS RECEIPT</h2>
    <hr>
    <p><strong>Receipt No:</strong> <?= htmlspecialchars($receipt['receipt_no']) ?></p>
    <p><strong>Date:</strong> <?= htmlspecialchars($receipt['date']) ?></p>
    <p><strong>Member Name:</strong> <?= htmlspecialchars($receipt['full_name']) ?></p>
    <p><strong>Member No:</strong> <?= htmlspecialchars($receipt['member_no']) ?></p>
    <p><strong>Amount:</strong> UGX <?= number_format($receipt['amount'], 2) ?></p>
    <p><strong>Notes:</strong> <?= htmlspecialchars($receipt['notes']) ?></p>

    <hr>
    <div class="mt-4 text-end">
        <p><strong>Approved By:</strong></p>
        <?php if ($signature_path && file_exists($signature_path)): ?>
            <img src="<?= $signature_path ?>" alt="Signature" style="max-height: 80px;"><br>
        <?php else: ?>
            <p><em>No signature available</em></p>
        <?php endif; ?>
        <p class="mt-2"><strong><?= htmlspecialchars($admin_name) ?></strong></p>
    </div>

    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
        <a href="savings.php" class="btn btn-secondary">Back</a>
    </div>
</div>
</body>
</html>
