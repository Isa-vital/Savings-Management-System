<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/GroupAccess.php';

$groupAccess = new GroupAccess($pdo);

// Get all pages for dropdown
$pages = $pdo->query("SELECT * FROM system_pages ORDER BY title")->fetchAll();

// Get all groups
$groups = $pdo->query("SELECT * FROM user_groups ORDER BY name")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_page'])) {
        $stmt = $pdo->prepare(
            "INSERT INTO group_page_access (group_id, page_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE group_id = group_id"
        );
        $stmt->execute([$_POST['group_id'], $_POST['page_id']]);
    }
}

// Get current assignments
$assignments = $pdo->query(
    "SELECT g.name as group_name, p.title as page_title 
     FROM group_page_access gpa
     JOIN user_groups g ON gpa.group_id = g.id
     JOIN system_pages p ON gpa.page_id = p.id"
)->fetchAll();
?>

<!-- HTML Interface -->
<div class="container">
    <h2>Page Access Management</h2>
    
    <!-- Assignment Form -->
    <form method="POST" class="mb-4">
        <div class="row g-3">
            <div class="col-md-5">
                <select name="group_id" class="form-select" required>
                    <option value="">Select Group</option>
                    <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <select name="page_id" class="form-select" required>
                    <option value="">Select Page</option>
                    <?php foreach ($pages as $page): ?>
                    <option value="<?= $page['id'] ?>"><?= htmlspecialchars($page['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" name="assign_page" class="btn btn-primary w-100">Assign</button>
            </div>
        </div>
    </form>
    
    <!-- Current Assignments -->
    <table class="table">
        <thead>
            <tr>
                <th>Group</th>
                <th>Page</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($assignments as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['group_name']) ?></td>
                <td><?= htmlspecialchars($row['page_title']) ?></td>
                <td>
                    <a href="?remove=<?= $row['id'] ?>" class="btn btn-sm btn-danger">Remove</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>