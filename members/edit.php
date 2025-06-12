<?php
require_once __DIR__ . '/../config.php';      // For $pdo, BASE_URL, APP_NAME, sanitize()
require_once __DIR__ . '/../helpers/auth.php';

require_login(); // Redirects if not logged in

// Only allow access for Core Admins and Administrators
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";

    // Redirect based on role
    if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header("Location: " . BASE_URL . "members/my_savings.php");
    } else {
        header("Location: " . BASE_URL . "landing.php");
    }
    exit;
}
// Set page title
$page_title = "Edit Member - " . APP_NAME;

// Page content for Core Admins and Administrators continues below...

// Page content for Core Admins and Administrators continues below...

// Check if member ID is provided
if (!isset($_GET['member_no'])) {
    $_SESSION['error'] = "Member ID not specified";
    header('Location: memberslist.php');
    exit;
}

$member_no = sanitize($_GET['member_no']);

// Fetch member details
try {
    $stmt = $pdo->prepare("SELECT * FROM memberz WHERE member_no = ?");
    $stmt->execute([$member_no]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['error'] = "Member not found";
        header('Location: memberslist.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: memberslist.php');
    exit;
}

// Ugandan districts array
$uganda_districts = [
    "Kampala", "Wakiso", "Mukono", "Jinja", "Mbale", "Gulu", "Lira", "Mbarara", 
    "Kabale", "Fort Portal", "Arua", "Soroti", "Masaka", "Entebbe", "Hoima"
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Sanitize all inputs
        $full_name = sanitize($_POST['full_name'] ?? '');
        $nin_number = sanitize($_POST['ninnumber'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        $subcounty = sanitize($_POST['subcounty'] ?? '');
        $village = sanitize($_POST['village'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $dob = sanitize($_POST['dob'] ?? '');
        $occupation = sanitize($_POST['occupation'] ?? '');
        $next_of_kin_name = sanitize($_POST['next_of_kin_name'] ?? '');
        $next_of_kin_contact = sanitize($_POST['next_of_kin_contact'] ?? '');

        // Validate required fields
        $required = [
            'full_name' => $full_name,
            'ninnumber' => $nin_number,
            'phone' => $phone,
            'district' => $district,
            'gender' => $gender,
            'dob' => $dob
        ];
        
        foreach ($required as $field => $value) {
            if (empty($value)) {
                throw new Exception(ucfirst($field) . " is required!");
            }
        }

        // Validate Ugandan phone number format
        $phone = preg_replace('/^\+256/', '', $phone);
        $phone = preg_replace('/^0/', '', $phone);
        if (!preg_match('/^[0-9]{9}$/', $phone)) {
            throw new Exception("Phone number must be 9 digits (without +256 or leading 0)");
        }

        // Validate NIN format
        if (!preg_match('/^[A-Z0-9]{14}$/', $nin_number)) {
            throw new Exception("NIN must be 14 alphanumeric characters");
        }

        // Validate date of birth
        $minAgeDate = date('Y-m-d', strtotime('-18 years'));
        if ($dob > $minAgeDate) {
            throw new Exception("Member must be at least 18 years old");
        }

        // Update member record
        $stmt = $pdo->prepare("UPDATE memberz SET 
            full_name = ?, nin_number = ?, phone = ?, email = ?,
            district = ?, subcounty = ?, village = ?, gender = ?,
            dob = ?, occupation = ?, next_of_kin_name = ?, next_of_kin_contact = ?
            WHERE member_no = ?");
        
        $stmt->execute([
            $full_name,
            $nin_number,
            $phone,
            $email,
            $district,
            $subcounty,
            $village,
            $gender,
            $dob,
            $occupation,
            $next_of_kin_name,
            $next_of_kin_contact,
            $member_no
        ]);
        
        $pdo->commit();
        
        // Log the update action
        error_log("Member {$member_no} updated by admin {$_SESSION['user']['id']}");
        
        $_SESSION['success'] = "Member updated successfully";
        header('Location: view.php?member_no=' . $member_no);
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->errorInfo[1] == 1062) {
            if (strpos($e->getMessage(), 'nin_number') !== false) {
                $error = "This NIN number is already registered";
            } elseif (strpos($e->getMessage(), 'phone') !== false) {
                $error = "This phone number is already registered";
            } else {
                $error = "Update failed. Please try again.";
            }
        } else {
            $error = "Database error: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - <?= htmlspecialchars(APP_NAME) ?></title>
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
        .required-field::after {
            content: " *";
            color: #DE2010;
        }
        .member-info-card {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
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
                        <i class="fas fa-user-edit me-2"></i>Edit Member
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="view.php?member_no=<?= htmlspecialchars($member['member_no']) ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="member-info-card p-3 mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">
                                                <i class="fas fa-id-card me-2"></i>
                                                Member: <?= htmlspecialchars($member['member_no']) ?>
                                            </h5>
                                            <p class="mb-0 text-muted">
                                                Last updated: <?= date('M j, Y', strtotime($member['updated_at'] ?? $member['created_at'])) ?>
                                            </p>
                                        </div>
                                        <div>
                                            <a href="../savings/savings.php?member_no=<?= urlencode($member['member_no']) ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-wallet me-1"></i> Manage Savings
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" novalidate>
                            <h5 class="mb-4 text-primary">
                                <i class="fas fa-id-card me-2"></i>Personal Information
                            </h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" 
                                        value="<?= htmlspecialchars($member['full_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">NIN Number</label>
                                    <input type="text" class="form-control" name="ninnumber" 
                                        pattern="[A-Z0-9]{14}" title="14 character National ID Number"
                                        value="<?= htmlspecialchars($member['nin_number']) ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Select</option>
                                        <option value="Male" <?= $member['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $member['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= $member['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Date of Birth</label>
                                    <input type="date" class="form-control" name="dob" 
                                        max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                                        value="<?= htmlspecialchars($member['dob']) ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Occupation</label>
                                    <input type="text" class="form-control" name="occupation"
                                        value="<?= htmlspecialchars($member['occupation']) ?>">
                                </div>
                            </div>

                            <h5 class="mb-4 mt-5 text-primary">
                                <i class="fas fa-map-marker-alt me-2"></i>Contact Information
                            </h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+256</span>
                                        <input type="tel" class="form-control" name="phone" 
                                            pattern="[0-9]{9}" title="9 digits without 0 prefix"
                                            value="<?= htmlspecialchars(preg_replace('/^\+256/', '', preg_replace('/^0/', '', $member['phone']))) ?>" required>
                                    </div>
                                    <small class="text-muted">e.g. 771234567 (without +256 or 0)</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                        value="<?= htmlspecialchars($member['email']) ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">District</label>
                                    <select class="form-select" name="district" required>
                                        <option value="">Select District</option>
                                        <?php foreach ($uganda_districts as $district): ?>
                                            <option value="<?= htmlspecialchars($district) ?>" 
                                                <?= $member['district'] === $district ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($district) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subcounty</label>
                                    <input type="text" class="form-control" name="subcounty"
                                        value="<?= htmlspecialchars($member['subcounty']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Village</label>
                                    <input type="text" class="form-control" name="village"
                                        value="<?= htmlspecialchars($member['village']) ?>">
                                </div>
                            </div>

                            <h5 class="mb-4 mt-5 text-primary">
                                <i class="fas fa-users me-2"></i>Next of Kin
                            </h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="next_of_kin_name"
                                        value="<?= htmlspecialchars($member['next_of_kin_name']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+256</span>
                                        <input type="tel" class="form-control" name="next_of_kin_contact"
                                            value="<?= htmlspecialchars(preg_replace('/^\+256/', '', preg_replace('/^0/', '', $member['next_of_kin_contact'] ?? ''))) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="view.php?member_no=<?= htmlspecialchars($member['member_no']) ?>" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number formatting
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // NIN validation (14 alphanumeric characters)
        document.querySelector('input[name="ninnumber"]').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
        
        // Next of kin phone formatting
        document.querySelector('input[name="next_of_kin_contact"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Form validation feedback
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                this.classList.add('was-validated');
            }
        });
    </script>
    <?php require_once '../partials/footer.php'; ?>
</body>
</html>