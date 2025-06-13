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

$limit = 10; // records per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total records
$totalStmt = $pdo->query("SELECT COUNT(*) FROM savings");
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch paginated savings records
$stmt = $pdo->prepare("
    SELECT s.id, s.amount, s.date, s.receipt_no, s.notes, m.full_name, m.member_no
    FROM savings s
    INNER JOIN memberz m ON s.member_id = m.id
    ORDER BY s.date DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$savings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Savings Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto px-md-4 py-4">
            <h1 class="h4 mb-4"><i class="fas fa-list me-2"></i> All Savings Records</h1>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <input type="text" id="searchInput" class="form-control w-25" placeholder="Search...">
                <div>
            <!--saving deposit button--->
                    <a href="<?= BASE_URL ?>savings/savings.php" class="btn btn-sm btn-outline-success me-2">
                        <i class="fas fa-plus me-1"></i> Add Savings
                    </a>
                    <button onclick="exportCSV()" class="btn btn-sm btn-outline-success me-2">Export CSV</button>
                    <button onclick="downloadPDF()" class="btn btn-sm btn-outline-primary">Download PDF</button>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0" id="savingsTableWrapper">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered mb-0" id="savingsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Member No</th>
                                    <th>Amount (UGX)</th>
                                    <th>Receipt No</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($savings as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['date']) ?></td>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><?= htmlspecialchars($s['member_no']) ?></td>
                                        <td><?= number_format($s['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($s['receipt_no'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($s['notes']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <nav aria-label="Savings pagination" class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </main>
    </div>
</div>

<script>
    // Filter rows
    document.getElementById("searchInput").addEventListener("keyup", function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll("#savingsTable tbody tr");
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? "" : "none";
        });
    });

    // Export CSV
    function exportCSV() {
        const rows = [...document.querySelectorAll("#savingsTable tr")];
        const csv = rows.map(row => {
            const cells = [...row.querySelectorAll("th, td")];
            return cells.map(cell => `"${cell.textContent.trim()}"`).join(",");
        }).join("\n");

        const blob = new Blob([csv], { type: "text/csv" });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "savings_list.csv";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Download PDF
    function downloadPDF() {
        const element = document.getElementById("savingsTableWrapper");
        const opt = {
            margin: 0.5,
            filename: 'savings_list.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
        };
        html2pdf().from(element).set(opt).save();
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
