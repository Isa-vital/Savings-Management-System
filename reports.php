<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';

if (!isset($_SESSION['admin']['id'])) {
    header("Location: /savingssystem/auth/login.php");
    exit;
}

$pdo = $conn;
$members = [];
$transactions = [];

try {
    // Fetch all members
    $stmt = $pdo->query("SELECT member_no, full_name FROM memberz");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter logic
    $member_id = $_GET['member_id'] ?? '';
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';

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
        $query .= " AND DATE(t.transaction_date) BETWEEN :from AND :to";
        $params[':from'] = $from_date;
        $params[':to'] = $to_date;
    }

    $query .= " ORDER BY t.transaction_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}
?>


<div class="container-fluid">
    <div class="row">
        <?php include '..savingssystem/partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>Reports</h2>

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
            </form>

            <!-- Report Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Transaction Report</strong>
                    <button id="downloadPDF" class="btn btn-sm btn-danger">
                        <i class="fas fa-file-pdf me-1"></i> Generate PDF
                    </button>
                </div>
                <div class="card-body table-responsive" id="reportTable">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Type</th>
                                <th>Amount (UGX)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $i => $tx): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($tx['full_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $tx['transaction_type'] === 'deposit' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($tx['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td>UGX <?= number_format($tx['amount'], 2) ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($tx['transaction_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No transactions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
        html2pdf().from(element).save("member_report.pdf");
    });
</script>

<?php include '../partials/footer.php'; ?>
