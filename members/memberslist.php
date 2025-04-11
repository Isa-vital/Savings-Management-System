<?php
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    redirect('../index.php');
}

// Handle member deletion
if (isset($_GET['delete'])) {
    $member_id = intval($_GET['delete']);
    
    $conn->begin_transaction();
    try {
        // First delete from savings table (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM savings WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        
        // Then delete from members table
        $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Member deleted successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting member: " . $e->getMessage();
    }
    redirect('memberslist.php');
}

// Search functionality
$search = '';
$where = '';
$params = [];
$types = '';

if (isset($_GET['search'])) {
    $search = sanitize($_GET['search']);
    if (!empty($search)) {
        $where = "WHERE m.member_no LIKE ? OR m.full_name LIKE ? OR m.phone LIKE ?";
        $search_term = "%$search%";
        $params = [$search_term, $search_term, $search_term];
        $types = 'sss';
    }
}

// Fetch members with their total savings
$query = "SELECT m.id, m.member_no, m.full_name, m.phone, m.gender, m.occupation, 
          COALESCE(SUM(s.amount), 0) as total_savings
          FROM memberz m
          LEFT JOIN savings s ON m.id = s.member_id
          $where
          GROUP BY m.id
          ORDER BY m.full_name ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$members = $result->fetch_all(MYSQLI_ASSOC);
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
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
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
                                                <td><?= htmlspecialchars(formatUgandanPhone($member['phone'])) ?></td>
                                                <td><?= htmlspecialchars($member['gender']) ?></td>
                                                <td><?= htmlspecialchars($member['occupation']) ?></td>
                                                <td class="text-end total-savings">
                                                    UGX <?= number_format($member['total_savings'], 2) ?>
                                                </td>
                                                <td class="action-btns">
                                                    <div class="d-flex gap-2">
                                                        <a href="view.php?id=<?= $member['id'] ?>" 
                                                           class="btn btn-sm btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?= $member['id'] ?>" 
                                                           class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="savings.php?member_id=<?= $member['id'] ?>" 
                                                           class="btn btn-sm btn-warning" title="Manage Savings">
                                                            <i class="fas fa-wallet"></i>
                                                        </a>
                                                        <button onclick="confirmDelete(<?= $member['id'] ?>)" 
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
        function confirmDelete(memberId) {
            const deleteBtn = document.getElementById('deleteBtn');
            deleteBtn.href = `memberslist.php?delete=${memberId}`;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        // Format phone numbers as you type in search
        document.querySelector('input[name="phone"]')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>