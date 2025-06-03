<?php
// Authentication check
if (!isset($_SESSION['admin']['id']) || $_SESSION['admin']['role'] !== 'admin') {
    header("Location: /savingssystem/auth/login.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_group'])) {
        $stmt = $pdo->prepare("INSERT INTO groups (name, description) VALUES (?, ?)");
        $stmt->execute([$_POST['group_name'], $_POST['group_description']]);
    }
    
    if (isset($_POST['assign_features'])) {
        // First clear existing features
        $pdo->prepare("DELETE FROM group_features WHERE group_id = ?")
           ->execute([$_POST['group_id']]);
        
        // Add new features
        if (!empty($_POST['features'])) {
            $stmt = $pdo->prepare("INSERT INTO group_features (group_id, feature_id) VALUES (?, ?)");
            foreach ($_POST['features'] as $featureId) {
                $stmt->execute([$_POST['group_id'], $featureId]);
            }
        }
    }
    
    if (isset($_POST['assign_users'])) {
        // Similar logic for user assignments
    }
}

// Fetch all groups
$groups = $pdo->query("SELECT * FROM groups ORDER BY name")->fetchAll();

// Fetch all features
$features = $pdo->query("SELECT * FROM features ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <!-- Group Management Tab -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-layer-group me-2"></i>Manage Groups</h5>
        </div>
        <div class="card-body">
            <!-- Create New Group -->
            <form method="POST" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-5">
                        <input type="text" name="group_name" class="form-control" placeholder="Group Name" required>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="group_description" class="form-control" placeholder="Description">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="create_group" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i> Create
                        </button>
                    </div>
                </div>
            </form>

            <!-- Feature Assignment -->
            <h6 class="mb-3">Assign Features to Groups</h6>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <select name="group_id" class="form-select" required>
                            <option value="">Select Group</option>
                            <?php foreach ($groups as $group): ?>
                            <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="features[]" class="form-select" multiple size="5">
                            <?php foreach ($features as $feature): ?>
                            <option value="<?= $feature['id'] ?>">
                                <?= htmlspecialchars($feature['name']) ?> (<?= $feature['key'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="assign_features" class="btn btn-success w-100">
                            <i class="fas fa-save me-1"></i> Assign
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- User Assignment Tab -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-users me-2"></i>Assign Users to Groups</h5>
        </div>
        <div class="card-body">
            <!-- Similar form for user assignment -->
        </div>
    </div>
</div>