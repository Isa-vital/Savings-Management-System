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

// Fetch admin signature and full name
$adminStmt = $pdo->prepare("SELECT full_name, signature FROM admins WHERE id = 1");
$adminStmt->execute();
$admin = $adminStmt->fetch();
$signature_path = __DIR__ . '/../assets/uploads/' . $admin['signature'];
$signature_web_path = '../assets/uploads/' . $admin['signature'];

$admin_name = $admin['full_name'] ?? 'Producer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Savings Receipt</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <style>
    body {
      background-color: #f8f9fa;
      position: relative;
    }
    .watermark {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-30deg);
      font-size: 80px;
      color: rgba(0, 0, 0, 0.05);
      z-index: 0;
      pointer-events: none;
      white-space: nowrap;
    }
    .receipt-container {
      max-width: 800px;
      margin: 30px auto;
      padding: 40px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      position: relative;
      z-index: 1;
    }
    .header {
      text-align: center;
      margin-bottom: 20px;
    }
    .header h3 {
      margin: 0;
      font-weight: bold;
    }
    .header p {
      margin: 0;
      font-size: 14px;
      color: #555;
    }
    .signature {
      max-height: 80px;
    }
  </style>
</head>
<body>

<div class="watermark">My Savings Group</div>

<div class="receipt-container" id="receipt">
  <div class="header">
    <h3>Rukindo Kweyamba Savings Group</h3>
    <p>Rukindo Village, Mbarara, Uganda</p>
    <p>Tel: +256 700 123 456 | Email: info@rksavingsgroup.org</p>
  </div>

  <h4 class="text-center mb-4">SAVINGS RECEIPT</h4>
  <table class="table table-bordered">
    <tr><th>Receipt No</th><td><?= htmlspecialchars($receipt['receipt_no']) ?></td></tr>
    <tr><th>Date</th><td><?= htmlspecialchars($receipt['date']) ?></td></tr>
    <tr><th>Member Name</th><td><?= htmlspecialchars($receipt['full_name']) ?></td></tr>
    <tr><th>Member No</th><td><?= htmlspecialchars($receipt['member_no']) ?></td></tr>
    <tr><th>Amount</th><td>UGX <?= number_format($receipt['amount'], 2) ?></td></tr>
    <tr><th>Notes</th><td><?= htmlspecialchars($receipt['notes']) ?></td></tr>
  </table>

  <div class="text-end mt-2">
    <p><strong>Received By:</strong></p>
    <?php if ($admin['signature'] && file_exists($signature_path)): ?>
  <img src="<?= $signature_web_path ?>" alt="Signature" class="signature"><br>
<?php else: ?>
  <p><em>No signature available</em></p>
<?php endif; ?>

    <p><strong><?= htmlspecialchars($admin_name) ?></strong></p>
  </div>
</div>

<div class="text-center mt-4">
  <button class="btn btn-success" onclick="downloadPDF()">Download PDF</button>
  <a href="savings.php" class="btn btn-secondary">Back</a>
</div>

<script>
function downloadPDF() {
  const element = document.getElementById('receipt');
  const opt = {
    margin:       0.5,
    filename:     'Savings_Receipt_<?= htmlspecialchars($receipt['receipt_no']) ?>.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2 },
    jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
  };
  html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>
