<?php
// config.php should be the first include to define BASE_URL, APP_NAME and start the session.
require_once __DIR__ . '/../config.php'; 
require_once __DIR__ . '/../helpers/auth.php';

require_login(); // Ensures user is logged in

// Restrict access to Admins
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: " . BASE_URL . "index.php"); // Or landing.php
    exit;
}
?>
<?php
function generateReceiptNo($pdo) {
    $today = date('Ymd');
    $prefix = "RCPT-$today-";
    
    // Get count of today's receipts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM savings WHERE DATE(date) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetchColumn() + 1;

    return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
}


try {
    // Fetch all members for the dropdown
    $stmt = $pdo->query("SELECT id, full_name, member_no FROM memberz ORDER BY full_name ASC");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching members: " . $e->getMessage();
}

$selected_member_id = $_POST['member_id'] ?? null;
$member = null;
$savings = [];
$total_savings = 0;
$error = '';

// If a member has been selected
if ($selected_member_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM memberz WHERE id = :id");
        $stmt->execute([':id' => $selected_member_id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            $error = "Selected member not found.";
        } else {
            // Process savings submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_saving'])) {
                $pdo->beginTransaction();
                try {
                    $amount = floatval($_POST['amount']);
                    $date = $_POST['date'] ?? '';
                    $receipt_no = generateReceiptNo($pdo);

                    $notes = $_POST['notes'] ?? '';

                    if ($amount <= 0 || empty($date)) {
                        throw new Exception("Amount and date are required.");
                    }

                    $stmt = $pdo->prepare("INSERT INTO savings 
                        (member_id, amount, date, receipt_no, notes) 
                        VALUES (:member_id, :amount, :date, :receipt_no, :notes)");
                    $stmt->execute([
                        ':member_id' => $selected_member_id,
                        ':amount' => $amount,
                        ':date' => $date,
                        ':receipt_no' => $receipt_no,
                        ':notes' => $notes
                    ]);

                    $pdo->commit();
                    $_SESSION['success'] = "Savings recorded successfully.";
                    header("Location: savings.php");
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = $e->getMessage();
                }
            }

            // Fetch savings
            $stmt = $pdo->prepare("SELECT * FROM savings WHERE member_id = :id ORDER BY date DESC");
            $stmt->execute([':id' => $selected_member_id]);
            $savings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_savings = array_sum(array_column($savings, 'amount'));
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Savings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto px-md-4 py-4">
            <h1 class="h4 mb-4"><i class="fas fa-wallet me-2"></i> Manage Member Savings</h1>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="mb-4">
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Select Member</label>
                        <select name="member_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">-- Choose a member --</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($m['id'] == $selected_member_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['member_no']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <?php if ($member): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>Member Info</h5>
                        <p><strong>Name:</strong> <?= htmlspecialchars($member['full_name']) ?></p>
                        <p><strong>Member No:</strong> <?= htmlspecialchars($member['member_no']) ?></p>
                        <p><strong>Total Savings:</strong> UGX <?= number_format($total_savings, 2) ?></p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header">Add New Savings</div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="member_id" value="<?= $selected_member_id ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Amount (UGX)</label>
                                        <input type="number" name="amount" class="form-control" min="1000" step="100" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Date</label>
                                        <input type="date" name="date" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea name="notes" class="form-control" rows="2"></textarea>
                                    </div>
                                    <button type="submit" name="add_saving" class="btn btn-success w-100">Save</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header">Savings History</div>
                            <div class="card-body">
                                <?php if (empty($savings)): ?>
                                    <p class="text-muted">No savings recorded.</p>
                                <?php else: ?>
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Receipt</th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($savings as $s): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($s['date']) ?></td>
                                                    <td>UGX <?= number_format($s['amount'], 2) ?></td>
                                                    <td>
    <?= htmlspecialchars($s['receipt_no'] ?: 'N/A') ?>
    <?php if ($s['receipt_no']): ?>
        <a href="<?= htmlspecialchars(BASE_URL . 'savings/printreceipt.php?receipt_no=' . urlencode($s['receipt_no'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2">
            <i class="fas fa-print" onclick="downloadPDF()"></i> Print
        </a>
    <?php endif; ?>
</td>

                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
