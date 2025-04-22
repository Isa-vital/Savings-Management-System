<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin']['id'])) {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT m.id, m.full_name, m.member_no, COALESCE(SUM(s.amount), 0) as total_savings
        FROM memberz m
        LEFT JOIN savings s ON m.id = s.member_id
        GROUP BY m.id
        ORDER BY m.full_name ASC
    ");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching members: " . $e->getMessage();
    $members = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Savings List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
<?php include '../partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include '../partials/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto px-md-4 py-4">
            <h1 class="h4 mb-4"><i class="fas fa-list me-2"></i> Member Savings Summary</h1>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <button onclick="exportCSV()" class="btn btn-sm btn-outline-primary">Export CSV</button>
                <button onclick="downloadPDF()" class="btn btn-sm btn-outline-danger">Download PDF</button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm" id="savingsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Member No</th>
                            <th>Total Savings (UGX)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $index => $member): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($member['full_name']) ?></td>
                                <td><?= htmlspecialchars($member['member_no']) ?></td>
                                <td><?= number_format($member['total_savings'], 2) ?></td>
                                <td>
                                    <a href="savings.php?member_id=<?= $member['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                                    <button 
                                        class="btn btn-sm btn-success" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#addSavingsModal<?= $member['id'] ?>">Add Savings</button>
                                </td>
                            </tr>

                            <!-- Modal for Add Savings -->
                            <div class="modal fade" id="addSavingsModal<?= $member['id'] ?>" tabindex="-1" aria-labelledby="addSavingsModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="POST" action="add-savings.php">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Add Savings for <?= htmlspecialchars($member['full_name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Amount (UGX)</label>
                                                    <input type="number" name="amount" class="form-control" min="1000" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Date</label>
                                                    <input type="date" name="date" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Notes</label>
                                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="add_saving" class="btn btn-success">Save</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<script>
function exportCSV() {
    const rows = [["#", "Full Name", "Member No", "Total Savings"]];
    const table = document.querySelector("#savingsTable tbody");
    for (let i = 0; i < table.rows.length; i++) {
        const cells = table.rows[i].cells;
        rows.push([
            cells[0].innerText,
            cells[1].innerText,
            cells[2].innerText,
            cells[3].innerText
        ]);
    }

    let csvContent = rows.map(e => e.join(",")).join("\n");
    const blob = new Blob([csvContent], {type: "text/csv;charset=utf-8;"});
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "savings_summary.csv";
    link.click();
}

function downloadPDF() {
    import('jspdf').then(jsPDF => {
        const doc = new jsPDF.jsPDF();
        doc.text("Savings Summary Report", 20, 20);
        let y = 30;

        const table = document.querySelector("#savingsTable tbody");
        for (let i = 0; i < table.rows.length; i++) {
            const cells = table.rows[i].cells;
            const line = `${cells[0].innerText}. ${cells[1].innerText} - ${cells[2].innerText} - UGX ${cells[3].innerText}`;
            doc.text(line, 20, y);
            y += 10;
        }

        doc.save("savings_summary.pdf");
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
