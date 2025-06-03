<?php
require_once __DIR__ . '/../config.php'; // Defines $pdo, APP_NAME, BASE_URL
require_once __DIR__ . '/../helpers/auth.php'; // For require_login, has_role

require_login();

if (!isset($_GET['receipt_no'])) {
    // It's better to display an error within the HTML structure if possible, but die() is simple.
    $_SESSION['error_message'] = 'No receipt number provided.';
    header('Location: ' . (BASE_URL ?? '../') . 'index.php'); // Redirect to a safe page
    exit();
}

$receipt_no = trim($_GET['receipt_no']);
if (empty($receipt_no)) {
    $_SESSION['error_message'] = 'Receipt number cannot be empty.';
    header('Location: ' . (BASE_URL ?? '../') . 'index.php');
    exit();
}

// Fetch the receipt data
// Added s.member_id to the select list for authorization check
$stmt = $pdo->prepare("SELECT s.id, s.amount, s.date, s.receipt_no, s.notes, s.member_id,
                              m.full_name AS member_full_name, m.member_no
                       FROM savings s
                       JOIN memberz m ON s.member_id = m.id 
                       WHERE s.receipt_no = :receipt_no");
$stmt->execute(['receipt_no' => $receipt_no]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    $_SESSION['error_message'] = 'Receipt not found.';
    header('Location: ' . (BASE_URL ?? '../') . 'index.php');
    exit();
}

// Authorization Check
$user_member_id = $_SESSION['user']['member_id'] ?? null;
$is_owner = ($user_member_id && $user_member_id == $receipt['member_id']);
$is_admin_type = has_role(['Core Admin', 'Administrator']);

if ($is_admin_type) {
    // Admins can view any receipt
} elseif (has_role('Member') && $is_owner) {
    // Members can view their own receipts
} else {
    $_SESSION['error_message'] = "Access Denied: You do not have permission to view this receipt.";
    header('Location: ' . (BASE_URL ?? '../') . 'index.php');
    exit();
}

// Static "Received By" information
$received_by_name = APP_NAME ?? 'Rukindo Kweyamba Savings Group';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Savings Receipt - <?php echo htmlspecialchars($receipt['receipt_no']); ?></title>
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
    <h3><?php echo htmlspecialchars(APP_NAME ?? 'Rukindo Kweyamba Savings Group'); ?></h3>
    <p>Rukindo Village, Mbarara, Uganda</p> <!-- Consider making these configurable if needed -->
    <p>Tel: +256 700 123 456 | Email: info@rksavingsgroup.org</p>
  </div>

  <h4 class="text-center mb-4">SAVINGS RECEIPT</h4>
  <table class="table table-bordered">
    <tr><th>Receipt No</th><td><?php echo htmlspecialchars($receipt['receipt_no']); ?></td></tr>
    <tr><th>Date</th><td><?php echo htmlspecialchars(date("d M, Y", strtotime($receipt['date']))); ?></td></tr>
    <tr><th>Member Name</th><td><?php echo htmlspecialchars($receipt['member_full_name']); ?></td></tr>
    <tr><th>Member No</th><td><?php echo htmlspecialchars($receipt['member_no']); ?></td></tr>
    <tr><th>Amount</th><td><?php echo htmlspecialchars($settings['currency_symbol'] ?? 'UGX'); ?> <?php echo number_format($receipt['amount'], 2); ?></td></tr>
    <tr><th>Notes</th><td><?php echo htmlspecialchars($receipt['notes']); ?></td></tr>
  </table>

  <div class="text-end mt-4 pt-4 border-top">
    <p class="mb-1"><strong>Received By:</strong></p>
    <p class="mb-0"><em><?php echo htmlspecialchars($received_by_name); ?></em></p>
    <p class="text-muted small mt-2">System Generated Receipt</p>
  </div>
</div>

<div class="text-center mt-4 mb-4 d-print-none">
  <button class="btn btn-success" onclick="downloadPDF()"><i class="fas fa-download me-2"></i>Download PDF</button>
  <a href="<?php echo (BASE_URL ?? '../') . 'index.php'; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
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
