<?php
session_start();
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/helpers/auth.php';

if (!isset($_SESSION['admin'])) {
    header("Location: auth/login.php");
    exit;
}

$adminId = $_SESSION['admin']['id'];

// Fetch admin profile
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $photo = $admin['photo'];
    $signature = $admin['signature'];

    // Handle profile photo upload
    if (!empty($_FILES['photo']['name'])) {
        $photoName = uniqid() . "_" . $_FILES['photo']['name'];
        $photoTmp = $_FILES['photo']['tmp_name'];
        move_uploaded_file($photoTmp, "assets/uploads/" . $photoName);
        $photo = $photoName;
    }

    // Handle signature upload
    if (!empty($_FILES['signature']['name'])) {
        $sigName = uniqid() . "_" . $_FILES['signature']['name'];
        $sigTmp = $_FILES['signature']['tmp_name'];
        move_uploaded_file($sigTmp, "assets/uploads/" . $sigName);
        $signature = $sigName;
    }

    // Update admin
    $updateStmt = $pdo->prepare("UPDATE admins SET email = ?, photo = ?, signature = ? WHERE id = ?");
    $updateStmt->execute([$email, $photo, $signature, $adminId]);

    header("Location: profile.php?updated=1");
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
<?php include 'partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include 'partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
            <h4 class="mb-4">My Profile</h4>

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">Profile updated successfully.</div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($admin['username']) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Profile Photo</label><br>
                                <?php if (!empty($admin['photo'])): ?>
                                    <img src="assets/uploads/<?= $admin['photo'] ?>" alt="Photo" width="100" class="img-thumbnail mb-2 d-block">
                                <?php endif; ?>
                                <input type="file" name="photo" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Signature</label><br>
                                <?php if (!empty($admin['signature'])): ?>
                                    <img src="assets/uploads/<?= $admin['signature'] ?>" alt="Signature" width="100" class="img-thumbnail mb-2 d-block">
                                <?php endif; ?>
                                <input type="file" name="signature" class="form-control">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
