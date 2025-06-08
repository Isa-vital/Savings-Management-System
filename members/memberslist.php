<?php
session_start(); // Ensure this is there first

require_once __DIR__ . '/../config.php';      // For $pdo, BASE_URL, APP_NAME
require_once __DIR__ . '/../helpers/auth.php'; // For require_login(), has_role()

require_login(); // Redirects to login if not authenticated

if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    // Redirect to a safe page, like user's dashboard or landing page
    if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header("Location: " . BASE_URL . "members/my_savings.php");
    } else {
        header("Location: " . BASE_URL . "landing.php");
    }
    exit;
}

// $pdo is available from config.php
// sanitize() is assumed to be available from config.php or helpers

// Handle member deletion
if (isset($_GET['delete'])) {
    $member_no = sanitize($_GET['delete']);
    
    try {
        $pdo->beginTransaction();
        
        // First delete from savings table
        $stmt = $pdo->prepare("DELETE FROM savings WHERE member_id = ?");
        $stmt->execute([$member_no]);
        
        // Then delete from members table
        $stmt = $pdo->prepare("DELETE FROM memberz WHERE member_no = ?");
        $stmt->execute([$member_no]);
        
        $pdo->commit();
        $_SESSION['success'] = "Member deleted successfully";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting member: " . $e->getMessage();
    }
    header('Location: memberslist.php');
    exit;
}

// Search functionality
$search = '';
$where = '';
$params = [];

if (isset($_GET['search'])) {
    $search = sanitize($_GET['search']);
    if (!empty($search)) {
        $where = "WHERE m.member_no LIKE ? OR m.full_name LIKE ? OR m.phone LIKE ?";
        $search_term = "%$search%";
        $params = array_fill(0, 3, $search_term);
    }
}

// Fetch members with their total savings
try {
    $query = "SELECT m.id, m.member_no, m.full_name, m.phone, m.gender, m.occupation, 
              COALESCE(SUM(s.amount), 0) as total_savings
              FROM memberz m
              LEFT JOIN savings s ON m.id = s.id
              
              $where
              GROUP BY m.member_no
              ORDER BY m.full_name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Failed to fetch members: " . $e->getMessage();
    header('Location: memberslist.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - Ugandan SACCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .uganda-flag {
            background: linear-gradient(to right, 
                #000 0%, #000 33%, 
                #FFC90D 33%, #FFC90D 66%, 
                #DE2010 66%, #DE2010 100%);
            height: 5px;
            margin-bottom: 20px;
        }
        .total-savings {
            font-weight: bold;
            color: #28a745;
        }
        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .search-box {
            max-width: 400px;
        }
    </style>
</head>
<body>
    <?php include '../partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="uganda-flag"></div>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users me-2"></i>Manage Members
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="register.php" class="btn btn-success">
                            <i class="fas fa-user-plus me-1"></i> Add New Member
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <div class="row">
                            <div class="col-md-6">
                                <form method="GET" class="search-box">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" 
                                            placeholder="Search by name, member no or phone" 
                                            value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-outline-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search)): ?>
                                            <a href="memberslist.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Member No</th>
                                        <th>Full Name</th>
                                        <th>Phone</th>
                                        <th>Gender</th>
                                        <th>Occupation</th>
                                        <th class="text-end">Total Savings</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($members)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No members found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($member['member_no']) ?></td>
                                                <td><?= htmlspecialchars($member['full_name']) ?></td>
                                                <td><?= htmlspecialchars($member['phone']) ?></td>
                                                <td><?= htmlspecialchars($member['gender']) ?></td>
                                                <td><?= htmlspecialchars($member['occupation']) ?></td>
                                                <td class="text-end total-savings">
                                                    UGX <?= number_format($member['total_savings'], 2) ?>
                                                </td>
                                                <td class="action-btns">
                                                    <div class="d-flex gap-2">
                                                        <a href="view.php?member_no=<?= urlencode($member['member_no']) ?>" 
                                                           class="btn btn-sm btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?member_no=<?= urlencode($member['member_no']) ?>" 
                                                           class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <div 
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#addSavingsModal<?= $member['member_no'] ?>" >
                                                        <a href="../savings/savings.php?member_no=<?= urlencode($member['member_no']) ?>" 
                                                           class="btn btn-sm btn-warning" title="Manage Savings">
                                                            <i class="fas fa-wallet"></i>
                                                        </a>
                                        </div>
                                                    
                                   
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
                                
                                                        <button onclick="confirmDelete('<?= htmlspecialchars($member['member_no'], ENT_QUOTES) ?>')" 
                                                                class="btn btn-sm btn-danger" title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($members)): ?>
                                    <tfoot>
                                        <tr class="table-active">
                                            <td colspan="5" class="text-end fw-bold">Total Savings:</td>
                                            <td class="text-end fw-bold">
                                                UGX <?= number_format(array_sum(array_column($members, 'total_savings')), 2) ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this member? All their savings records will also be deleted.</p>
                    <p class="fw-bold">This action cannot be undone!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteBtn" class="btn btn-danger">Delete Member</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete confirmation
        function confirmDelete(member_no) {
            const deleteBtn = document.getElementById('deleteBtn');
            deleteBtn.href = `memberslist.php?delete=${encodeURIComponent(member_no)}`;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>