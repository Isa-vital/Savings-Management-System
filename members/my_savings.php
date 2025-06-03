<?php
require_once __DIR__ . '/../config.php'; // For DB, BASE_URL
require_once __DIR__ . '/../helpers/auth.php'; // For require_login, has_role

require_login();

if (!isset($_SESSION['user']['member_id']) || empty($_SESSION['user']['member_id'])) {
    $_SESSION['error_message'] = "No member account is linked to your user profile. Please contact an administrator.";
    // Redirect to a more appropriate page, like the main index or profile page
    header('Location: ' . (BASE_URL ?? '/') . 'index.php');
    exit();
}

if (!has_role('Member')) {
    // This check ensures only users with the 'Member' role can access this page.
    // Admins who might have a linked member_id but not the 'Member' role would be excluded.
    // If Admins should also access this for their linked member account, this check needs adjustment
    // e.g., if (has_role('Member') || (is_admin() && isset($_SESSION['user']['member_id'])))
    // For now, sticking to the strict 'Member' role requirement.
    $_SESSION['error_message'] = "You do not have permission to view this page. This area is for members only.";
    header('Location: ' . (BASE_URL ?? '/') . 'index.php');
    exit();
}

$member_id_session = $_SESSION['user']['member_id'];
$page_title = "My Savings Records";

$limit = 10; // records per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total records for this member
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM savings WHERE member_id = :member_id");
$totalStmt->execute(['member_id' => $member_id_session]);
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch paginated savings records for this member
$stmt = $pdo->prepare("
    SELECT id, amount, date, receipt_no, notes
    FROM savings
    WHERE member_id = :member_id
    ORDER BY date DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':member_id', $member_id_session, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$savings_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars($settings['site_name'] ?? APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <!-- Include Font Awesome if icons are used and not globally available -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/../partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h1 class="h4 mb-4"><i class="fas fa-wallet me-2"></i> <?php echo htmlspecialchars($page_title); ?></h1>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <input type="text" id="searchInputMySavings" class="form-control w-25" placeholder="Search my savings...">
                <div>
                    <button onclick="exportMySavingsCSV()" class="btn btn-sm btn-outline-success me-2">Export CSV</button>
                    <button onclick="downloadMySavingsPDF()" class="btn btn-sm btn-outline-primary">Download PDF</button>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0" id="mySavingsTableWrapper">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered mb-0" id="mySavingsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Amount (<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'UGX'); ?>)</th>
                                    <th>Receipt No</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($savings_records)): ?>
                                    <tr><td colspan="5" class="text-center">You have no savings records yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($savings_records as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date("d M, Y", strtotime($s['date']))); ?></td>
                                            <td><?php echo number_format($s['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($s['receipt_no'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($s['notes'] ?: 'N/A'); ?></td>
                                            <td>
                                                <?php if (!empty($s['receipt_no'])): ?>
                                                    <a href="<?php echo BASE_URL ?? '../'; ?>savings/printreceipt.php?receipt_no=<?php echo htmlspecialchars($s['receipt_no']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-receipt me-1"></i>View Receipt
                                                    </a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="My Savings pagination" class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    // Filter rows for My Savings
    document.getElementById("searchInputMySavings").addEventListener("keyup", function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll("#mySavingsTable tbody tr");
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? "" : "none";
        });
    });

    // Export My Savings CSV
    function exportMySavingsCSV() {
        const table = document.getElementById("mySavingsTable");
        const rows = [...table.querySelectorAll("tr")];
        // Ensure headers are correctly captured, especially if they differ from savingslist.php
        const csv = rows.map(row => {
            const cells = [...row.querySelectorAll("th, td")];
             // Exclude the "Action" column from CSV if it mostly contains buttons/links
            const cellsToExport = cells.slice(0, cells.length -1);
            return cellsToExport.map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`).join(",");
        }).join("\n");

        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "my_savings_records.csv";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Download My Savings PDF
    function downloadMySavingsPDF() {
        const element = document.getElementById("mySavingsTableWrapper"); // Or just mySavingsTable if wrapper isn't needed
        const opt = {
            margin: 0.5,
            filename: 'my_savings_records.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
            // Consider 'portrait' if table is narrow
        };
        // Clone the table and remove the "Action" column for PDF export
        const tableClone = document.getElementById("mySavingsTable").cloneNode(true);
        const actionHeaderIndex = Array.from(tableClone.querySelectorAll("thead th")).findIndex(th => th.textContent.trim() === "Action");
        if (actionHeaderIndex !== -1) {
            Array.from(tableClone.querySelectorAll("tr")).forEach(row => {
                 if(row.cells.length > actionHeaderIndex) row.deleteCell(actionHeaderIndex);
            });
        }
        // Use the cloned table for PDF generation
        html2pdf().from(tableClone).set(opt).save();
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
