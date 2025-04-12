<?php
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    redirect('../index.php');
}

// Ugandan districts array
$uganda_districts = [
    "Kampala", "Wakiso", "Mukono", "Jinja", "Mbale", "Gulu", "Lira", "Mbarara", 
    "Kabale", "Fort Portal", "Arua", "Soroti", "Masaka", "Entebbe", "Hoima"
];

// Function to generate member number
function generateMemberNo($district) {
    $district_code = strtoupper(substr($district, 0, 3));
    $year = date('Y');
    $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return 'UG-' . $district_code . '-' . $year . '-' . $random;
}

// Initialize member number
$member_no = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['district'])) {
    $district = sanitize($_GET['district']);
    $member_no = generateMemberNo($district);
}

// Process Ugandan member registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction for data integrity
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
        $member_no = sanitize($_POST['member_no'] ?? '');

        // Validate required fields
        $required = [
            'full_name' => $full_name,
            'ninnumber' => $nin_number,
            'phone' => $phone,
            'district' => $district,
            'gender' => $gender,
            'dob' => $dob,
            'member_no' => $member_no
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

        // Validate NIN format (14 alphanumeric characters)
        if (!preg_match('/^[A-Z0-9]{14}$/', $nin_number)) {
            throw new Exception("NIN must be 14 alphanumeric characters");
        }

        // Validate date of birth (at least 18 years old)
        $minAgeDate = date('Y-m-d', strtotime('-18 years'));
        if ($dob > $minAgeDate) {
            throw new Exception("Member must be at least 18 years old");
        }

        // Prepare and execute the insert statement
        $stmt = $pdo->prepare("INSERT INTO memberz (
            member_no, full_name, nin_number, phone, email, 
            district, subcounty, village, gender, dob, occupation, 
            next_of_kin_name, next_of_kin_contact
        ) VALUES (
            :member_no, :full_name, :nin_number, :phone, :email, 
            :district, :subcounty, :village, :gender, :dob, :occupation, 
            :next_of_kin_name, :next_of_kin_contact
        )");
        
        $params = [
            ':member_no' => $member_no,
            ':full_name' => $full_name,
            ':nin_number' => $nin_number,
            ':phone' => $phone,
            ':email' => $email,
            ':district' => $district,
            ':subcounty' => $subcounty,
            ':village' => $village,
            ':gender' => $gender,
            ':dob' => $dob,
            ':occupation' => $occupation,
            ':next_of_kin_name' => $next_of_kin_name,
            ':next_of_kin_contact' => $next_of_kin_contact
        ];
        
        $stmt->execute($params);
        
        // Check for duplicate entries
        if ($stmt->rowCount() === 0) {
            $errorInfo = $stmt->errorInfo();
            if (isset($errorInfo[1])) {
                // Check for duplicate NIN or phone
                if ($errorInfo[1] == 1062) {
                    if (strpos($errorInfo[2], 'nin_number') !== false) {
                        throw new Exception("This NIN number is already registered");
                    }
                    if (strpos($errorInfo[2], 'phone') !== false) {
                        throw new Exception("This phone number is already registered");
                    }
                }
            }
            throw new Exception("Registration failed. Please try again.");
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Set success session variables
        $_SESSION['success'] = true;
        $_SESSION['member_number'] = $member_no;
        $_SESSION['member_name'] = $full_name;
        
        // Redirect back to show success message
        redirect('register.php');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error: " . $e->getMessage());
        $error = "A database error occurred. Please try again.";
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Member - Ugandan SACCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
        .form-control:invalid, .form-select:invalid {
            border-color: #dc3545;
        }
        .member-no-display {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 20px;
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
                        <i class="fas fa-user-plus me-2"></i>Register New Member
                    </h1>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST" novalidate>
                            <?php if ($member_no): ?>
                                <div class="member-no-display alert alert-info">
                                    <i class="fas fa-id-card me-2"></i>
                                    Member Number: <?= htmlspecialchars($member_no) ?>
                                    <input type="hidden" name="member_no" value="<?= htmlspecialchars($member_no) ?>">
                                </div>
                            <?php endif; ?>

                            <h5 class="mb-4 text-primary">
                                <i class="fas fa-id-card me-2"></i>Personal Information
                            </h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" 
                                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">NIN Number</label>
                                    <input type="text" class="form-control" name="ninnumber" 
                                        pattern="[A-Z0-9]{14}" title="14 character National ID Number"
                                        value="<?= htmlspecialchars($_POST['ninnumber'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Select</option>
                                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Date of Birth</label>
                                    <input type="date" class="form-control" name="dob" 
                                        max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                                        value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Occupation</label>
                                    <input type="text" class="form-control" name="occupation"
                                        value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>">
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
                                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                                    </div>
                                    <small class="text-muted">e.g. 771234567 (without +256 or 0)</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">District</label>
                                    <select class="form-select" name="district" id="district" required
                                        onchange="generateMemberNumber()">
                                        <option value="">Select District</option>
                                        <?php foreach ($uganda_districts as $district): ?>
                                            <option value="<?= htmlspecialchars($district) ?>" 
                                                <?= ($_POST['district'] ?? '') === $district ? 'selected' : '' ?>>
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
                                        value="<?= htmlspecialchars($_POST['subcounty'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Village</label>
                                    <input type="text" class="form-control" name="village"
                                        value="<?= htmlspecialchars($_POST['village'] ?? '') ?>">
                                </div>
                            </div>

                            <h5 class="mb-4 mt-5 text-primary">
                                <i class="fas fa-users me-2"></i>Next of Kin
                            </h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="next_of_kin_name"
                                        value="<?= htmlspecialchars($_POST['next_of_kin_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+256</span>
                                        <input type="tel" class="form-control" name="next_of_kin_contact"
                                            value="<?= htmlspecialchars($_POST['next_of_kin_contact'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="../index.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Register Member
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Show success popup if registration was successful
        <?php if (isset($_SESSION['success']) && $_SESSION['success'] === true): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Registration Successful!',
                html: `
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success mb-3" style="font-size: 4rem;"></i>
                        <h4>Member Registered Successfully</h4>
                        <p>Name: <strong><?= htmlspecialchars($_SESSION['member_name']) ?></strong></p>
                        <p>Member Number: <strong><?= htmlspecialchars($_SESSION['member_number']) ?></strong></p>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'Continue',
                showCancelButton: true,
                cancelButtonText: 'View Members List',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isDismissed) {
                    window.location.href = 'memberslist.php';
                }
                
                // Clear the session
                fetch('clearsession.php?clear=success')
                    .then(response => response.text())
                    .then(data => console.log('Session cleared'));
            });
        });
        <?php 
            unset($_SESSION['success']);
            unset($_SESSION['member_number']);
            unset($_SESSION['member_name']);
        endif; 
        ?>

        // Function to generate member number when district is selected
        function generateMemberNumber() {
            const district = document.getElementById('district').value;
            if (district) {
                // AJAX request to generate member number
                fetch(window.location.pathname + '?district=' + encodeURIComponent(district))
                    .then(response => response.text())
                    .then(html => {
                        // Create a temporary element to parse the HTML
                        const temp = document.createElement('div');
                        temp.innerHTML = html;
                        
                        // Find the member number display in the response
                        const memberNoDisplay = temp.querySelector('.member-no-display');
                        if (memberNoDisplay) {
                            // Replace the form with the updated version
                            const form = document.querySelector('form');
                            const oldMemberNo = document.querySelector('.member-no-display');
                            if (oldMemberNo) {
                                oldMemberNo.replaceWith(memberNoDisplay);
                            } else {
                                form.insertBefore(memberNoDisplay, form.firstChild);
                            }
                        }
                    });
            }
        }

        // Ugandan phone number formatting
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
</body>
</html>