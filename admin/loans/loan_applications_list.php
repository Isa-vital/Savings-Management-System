<?php
require_once __DIR__ . '/../../config.php'; // Defines $pdo, BASE_URL, APP_NAME, starts session
require_once __DIR__ . '/../../helpers/auth.php'; // For require_login, has_role

require_login();
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$page_title = "Loan Applications List";

// Filters
$filter_status = sanitize($_GET['status'] ?? ''); // sanitize from config.php or helpers

$pdo_params = [];
$where_clauses = [];

if (!empty($filter_status)) {
    $where_clauses[] = "l.status = :status";
    $pdo_params[':status'] = $filter_status;
}

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// Pagination variables
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

$loans = [];
$total_records = 0;
$total_pages = 0;
$page_errors = []; // For page-specific errors not from session

try {
    // Get total records for pagination
    $stmt_count = $pdo->prepare("SELECT COUNT(l.id) FROM loans l" . $sql_where);
    $stmt_count->execute($pdo_params);
    $total_records = $stmt_count->fetchColumn();

    if ($total_records > 0) {
        $total_pages = ceil($total_records / $records_per_page);
        // Adjust page if out of bounds
        if ($page > $total_pages) $page = $total_pages;
        if ($page < 1) $page = 1; // Should be caught by max(1,...) but good to be sure
        $offset = ($page - 1) * $records_per_page; // Recalculate offset if page was adjusted

        // Fetch loan applications with applicant's name
        $stmt_loans = $pdo->prepare(
            "SELECT l.id, l.loan_number, l.amount as amount_applied, l.application_date, l.status, m.full_name as applicant_name
             FROM loans l
             JOIN memberz m ON l.member_id = m.id"
             . $sql_where .
            " ORDER BY l.application_date DESC, l.id DESC
             LIMIT :limit OFFSET :offset"
        );
        // Bind filter params for main query
        foreach ($pdo_params as $key => $value) {
            $stmt_loans->bindValue($key, $value);
        }
        $stmt_loans->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt_loans->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_loans->execute();
        $loans = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error fetching loan applications: " . $e->getMessage());
    // Use $page_errors for errors specific to this page load, not session for this.
    $page_errors[] = "Database error fetching loan applications. Please try again later.";
}

$loan_statuses = ['pending', 'approved', 'rejected', 'active', 'completed', 'defaulted']; // For filter dropdown

// For SweetAlerts from session (e.g., after an action on another page)
$sa_error = $_SESSION['error_message'] ?? '';
if(isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
$sa_success = $_SESSION['success_message'] ?? '';
if(isset($_SESSION['success_message'])) unset($_SESSION['success_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Savings App'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-list-ul me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <?php if (!empty($page_errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($page_errors as $err): echo '<p class="mb-0">' . htmlspecialchars($err) . '</p>'; endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Applications</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="loan_applications_list.php" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($loan_statuses as $status_val): ?>
                                        <option value="<?php echo htmlspecialchars($status_val); ?>" <?php echo ($filter_status === $status_val) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst($status_val)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 align-self-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Filter</button>
                                <a href="loan_applications_list.php" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i>Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                         <h5 class="mb-0">Applications</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Loan #</th>
                                        <th>Applicant</th>
                                        <th>Amount Applied (<?php echo htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX'); ?>)</th>
                                        <th>Application Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($loans)): ?>
                                        <tr><td colspan="6" class="text-center">No loan applications found matching your criteria.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($loans as $loan): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($loan['loan_number']); ?></td>
                                                <td><?php echo htmlspecialchars($loan['applicant_name']); ?></td>
                                                <td class="text-end"><?php echo htmlspecialchars(number_format($loan['amount_applied'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($loan['application_date']))); ?></td>
                                                <td>
                                                    <?php
                                                    $status_badge = 'secondary'; // Default
                                                    if ($loan['status'] === 'pending') $status_badge = 'warning text-dark';
                                                    elseif ($loan['status'] === 'approved' || $loan['status'] === 'active') $status_badge = 'success';
                                                    elseif ($loan['status'] === 'rejected' || $loan['status'] === 'defaulted') $status_badge = 'danger';
                                                    elseif ($loan['status'] === 'completed') $status_badge = 'info text-dark';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_badge; ?>"><?php echo htmlspecialchars(ucfirst($loan['status'])); ?></span>
                                                </td>
                                                <td>
                                                    <a href="view_loan_application.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($filter_status); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($sa_error)): ?>
                Swal.fire({ icon: 'error', title: 'Oops...', text: '<?php echo addslashes(htmlspecialchars($sa_error)); ?>' });
            <?php endif; ?>
            <?php if (!empty($sa_success)): ?>
                Swal.fire({ icon: 'success', title: 'Success!', text: '<?php echo addslashes(htmlspecialchars($sa_success)); ?>' });
            <?php endif; ?>
        });
    </script>
</body>
</html>
