<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_admin(); // Core Admin or Administrator

$page_title = "User Management";

// Fetch all members
$members_stmt = $pdo->query("SELECT id, member_no, full_name, email, phone, is_system_user, user_id FROM memberz ORDER BY full_name ASC");
$members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users and key by user_id for easy lookup
$users_stmt = $pdo->query("SELECT id, username, email, phone, is_active, member_id FROM users ORDER BY username ASC");
$all_users_raw = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
$users_by_id = [];
$users_by_member_id = [];
foreach ($all_users_raw as $user) {
    $users_by_id[$user['id']] = $user;
    if ($user['member_id']) {
        $users_by_member_id[$user['member_id']] = $user;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars($settings['site_name'] ?? APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-sm th, .table-sm td {
            padding: 0.4rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <h2>Members List</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Member No</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status/Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($members)): ?>
                                <tr><td colspan="5">No members found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['member_no']); ?></td>
                                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $linked_user = null;
                                            if ($member['user_id'] && isset($users_by_id[$member['user_id']])) {
                                                $linked_user = $users_by_id[$member['user_id']];
                                            } elseif (isset($users_by_member_id[$member['id']])) {
                                                // Fallback if user_id on memberz is not set but user links to member
                                                $linked_user = $users_by_member_id[$member['id']];
                                            }

                                            if ($member['is_system_user'] || $linked_user): ?>
                                                <span class="badge bg-success">Is User</span>
                                                <?php if ($linked_user): ?>
                                                    <?php if ($linked_user['is_active']): ?>
                                                        <span class="badge bg-info text-dark">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Pending Activation</span>
                                                    <?php endif; ?>
                                                    <a href="manage_user_details.php?user_id=<?php echo $linked_user['id']; ?>" class="btn btn-sm btn-outline-secondary">Manage</a>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Data Mismatch</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="convert_member.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">Convert to User</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h2 class="mt-4">System Users List</h2>
                 <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Linked Member No</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_users_raw)): ?>
                                <tr><td colspan="6">No users found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($all_users_raw as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $linked_member_no = 'N/A';
                                            if ($user['member_id']) {
                                                foreach ($members as $member) {
                                                    if ($member['id'] == $user['member_id']) {
                                                        $linked_member_no = $member['member_no'];
                                                        break;
                                                    }
                                                }
                                            }
                                            echo htmlspecialchars($linked_member_no);
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="toggle_user_status.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                            <a href="../group_management/assign_user.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">Roles/Groups</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
