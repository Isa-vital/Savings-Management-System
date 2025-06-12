<?php
// Session is expected to be started by config.php
// session_start(); // Ensure this is there first

require_once __DIR__ . '/../config.php';      // For $pdo, BASE_URL, APP_NAME, sanitize()
require_once __DIR__ . '/../helpers/auth.php'; // For require_login(), has_role()

// Add PHPMailer and email template includes
require_once __DIR__ . '/../vendor/autoload.php'; // For PHPMailer
require_once __DIR__ . '/../emails/email_template.php'; // For generateBasicEmailTemplate()

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_login(); // Redirects to login if not authenticated

if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    // Redirect to a safe page, like user's dashboard or landing page
     if (has_role('Member') && isset($_SESSION['user']['member_id'])) {
        header("Location: " . BASE_URL . "members/my_savings.php");
    } else {
        header("Location: " . BASE_URL . "landing.php"); // Or index.php if landing is not for logged-in users
    }
    exit;
}

// $pdo is available from config.php
// sanitize() is assumed to be available from config.php or helpers/auth.php (config.php is more likely)

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
    if ($district) { // Only generate if district is selected
        $member_no = generateMemberNo($district);
    }
}

$page_error_for_sweetalert = ''; // Initialize for SweetAlert
$sa_success_admin_reg = $_SESSION['success_message_admin_reg'] ?? ''; // For new success message
if (isset($_SESSION['success_message_admin_reg'])) unset($_SESSION['success_message_admin_reg']);

$error = null; // Ensure $error is initialized for the page

// Process Ugandan member registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction for data integrity
        $pdo->beginTransaction();
        
        // Sanitize all inputs
        $full_name = sanitize($_POST['full_name'] ?? '');

        // New validation for full_name length
        if (strlen($full_name) < 2) {
            throw new Exception("Full name must be at least 2 characters long.");
        }

        // New validation for full_name characters (only letters and spaces)
        if (!preg_match('/^[a-zA-Z\s]+$/', $full_name)) {
            throw new Exception("Full name must only contain letters and spaces. Special characters are not allowed.");
        }

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
        
        // Check for duplicate entries or failure
        if ($stmt->rowCount() === 0) {
            // This part might be tricky if actual error is a duplicate key violation caught by DB
            // For now, assume if rowCount is 0, it's a generic failure or caught duplicate.
            // More specific duplicate checks (NIN, phone) are already handled by DB constraints if set.
            throw new Exception("Member registration failed. Please check details or contact support if the issue persists.");
        }

        $new_member_id_from_memberz = $pdo->lastInsertId(); // Get ID of the newly inserted member
        $user_creation_message_suffix = ""; // To append to final success message

        if (!empty($email)) {
            // Check if email already exists in users table
            $stmt_check_email = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt_check_email->execute([':email' => $email]);
            if ($stmt_check_email->fetch()) {
                $user_creation_message_suffix = " However, a user account could not be automatically created as the email '" . htmlspecialchars($email) . "' is already in use by another system user.";
                error_log("Admin Member Registration: Member '$full_name' ($member_no) registered, but user account not created. Email '$email' already exists for another user.");
            } else {
                // Proceed to create user
                $generated_username = strtok($email, '@'); 
                $temp_username = $generated_username;
                $counter = 1;
                $stmt_check_username = $pdo->prepare("SELECT id FROM users WHERE username = :username");
                while (true) {
                    $stmt_check_username->execute([':username' => $temp_username]);
                    if (!$stmt_check_username->fetch()) {
                        $generated_username = $temp_username;
                        break;
                    }
                    $temp_username = $generated_username . $counter++;
                }

                $activation_token = bin2hex(random_bytes(32));
                $token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $password_placeholder = 'PENDING_ACTIVATION_NO_LOGIN'; // Not directly hashable, just a placeholder

                $stmt_insert_user = $pdo->prepare(
                    "INSERT INTO users (member_id, username, password_hash, email, phone, is_active, activation_token, token_expires_at, created_at) 
                     VALUES (:member_id, :username, :password_hash, :email, :phone, 0, :token, :expires_at, NOW())"
                );
                $stmt_insert_user->execute([
                    ':member_id' => $new_member_id_from_memberz,
                    ':username' => $generated_username,
                    ':password_hash' => hashPassword($password_placeholder), // Hash the placeholder
                    ':email' => $email,
                    ':phone' => $phone, 
                    ':token' => $activation_token,
                    ':expires_at' => $token_expires_at
                ]);
                $new_user_id = $pdo->lastInsertId();

                $stmt_update_memberz = $pdo->prepare("UPDATE memberz SET user_id = :user_id, is_system_user = 1 WHERE id = :member_id");
                $stmt_update_memberz->execute([':user_id' => $new_user_id, ':member_id' => $new_member_id_from_memberz]);

                $default_member_role_id = 3; 
                $default_members_group_id = 2; 
                $stmt_assign_role = $pdo->prepare("INSERT INTO user_group_roles (user_id, group_id, role_id) VALUES (:user_id, :group_id, :role_id)");
                $stmt_assign_role->execute([
                    ':user_id' => $new_user_id,
                    ':group_id' => $default_members_group_id, // Assuming group ID 2 is 'Members' group
                    ':role_id' => $default_member_role_id   // Assuming role ID 3 is 'Member'
                ]);
                if ($stmt_assign_role->rowCount() == 0) {
                    throw new Exception("Critical: Failed to assign default role during member registration user creation.");
                }
                
                $activation_link = rtrim(BASE_URL, '/') . '/auth/activate_account.php?token=' . $activation_token;
                $email_subject = 'Activate Your Account - ' . APP_NAME;
                $email_body_html = "<p>Hello " . htmlspecialchars($full_name) . ",</p>" .
                                   "<p>A member account and user profile have been created for you on " . APP_NAME . ".</p>" .
                                   "<p>Please click the link below to activate your user account and set your password:</p>" .
                                   "<p><a href='" . $activation_link . "'>" . $activation_link . "</a></p>" .
                                   "<p>This link is valid for 24 hours.</p>" .
                                   "<p>Regards,<br>The " . APP_NAME . " Team</p>";
                $full_email_html = generateBasicEmailTemplate($email_body_html, APP_NAME);
                
                $mail = new PHPMailer(true);
                try {
                    $mail->SMTPDebug = SMTP::DEBUG_OFF; // Set to DEBUG_SERVER for detailed logs if issues
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    elseif (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    else $mail->SMTPSecure = false; // Or PHPMailer::ENCRYPTION_SMTPS if port is 465
                    $mail->Port       = SMTP_PORT;
                    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                    $mail->addAddress($email, $full_name);
                    $mail->isHTML(true);
                    $mail->Subject = $email_subject;
                    $mail->Body    = $full_email_html;
                    $mail->AltBody = strip_tags(str_replace(["<p>", "</p>", "<br>"], ["", "\n", "\n"], $email_body_html)); // Basic conversion
                    $mail->send();
                    $user_creation_message_suffix = " A user account was created, and an activation email has been sent to " . htmlspecialchars($email) . ".";
                } catch (Exception $e_mail) { // PHPMailer Exception
                    error_log("PHPMailer Error (Admin Member Registration for " . $email . "): " . $mail->ErrorInfo . " (Details: " . $e_mail->getMessage() . ")");
                    $user_creation_message_suffix = " A user account was created, but the activation email could not be sent. Please contact support or convert user manually. Activation link for testing (normally emailed): " . $activation_link;
                }
            }
        } else { // No email provided
            $user_creation_message_suffix = " No user account was created as no email address was provided for the member.";
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message_admin_reg'] = "Member '" . htmlspecialchars($full_name) . "' (No: " . htmlspecialchars($member_no) . ") registered successfully." . $user_creation_message_suffix;
        header("Location: " . BASE_URL . "members/register.php"); // Redirect to clear POST and show message
        exit;
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Database error: " . $e->getMessage());
        $error = "A database error occurred. Please try again.";
        $page_error_for_sweetalert = $error; // Capture for SweetAlert
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        $page_error_for_sweetalert = $error; // Capture for SweetAlert
    }
}

// Prepare success message data for SweetAlert (already handled by $sa_success_admin_reg initialization)
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
            border-color: #dc3545; /* Bootstrap's danger color */
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
    <?php include __DIR__ . '/../partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="uganda-flag"></div>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-plus me-2"></i>Register New Member
                    </h1>
                </div>

                <?php /* Old inline error display - to be removed
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                */ ?>

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
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($sa_success_admin_reg)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Operation Successful',
                html: '<?php echo addslashes(str_replace("\n", "<br>", htmlspecialchars($sa_success_admin_reg))); ?>',
                confirmButtonText: 'OK',
                // Removed cancel button and redirect to memberslist for simplicity with new combined message
                // User will stay on the registration page, which is fine.
            });
            // No need for fetch('clearsession.php?clear=success') as session var is unset in PHP.
            <?php endif; ?>

            <?php if (!empty($page_error_for_sweetalert)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Registration Error',
                    text: '<?php echo addslashes(htmlspecialchars($page_error_for_sweetalert)); ?>',
                });
            <?php endif; ?>
        });

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
    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>