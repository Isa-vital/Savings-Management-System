<?php
require_once __DIR__ . '/../config.php'; // Defines $pdo, BASE_URL, APP_NAME, starts session
require_once __DIR__ . '/../helpers/auth.php'; // For require_login, has_role
require_once __DIR__ . '/../vendor/autoload.php'; // For PHPMailer
require_once __DIR__ . '/../emails/email_template.php'; // For generateBasicEmailTemplate()

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_login(); // Ensure user is logged in

// Access Control: Only 'Member' role with a valid member_id can access
if (!has_role('Member') || !isset($_SESSION['user']['member_id']) || empty($_SESSION['user']['member_id'])) {
    $_SESSION['error_message'] = "You must be a registered member to make a savings deposit request.";
    header("Location: " . BASE_URL . "index.php"); // Redirect to their dashboard or landing
    exit;
}

$current_user_id = $_SESSION['user']['id']; // User ID from users table
$current_member_id = $_SESSION['user']['member_id']; // Member ID from memberz table
$page_title = "Request Savings Deposit";

// Fetch current member's details for display (name, member_no, email for fallback)
$member_info = null;
try {
    $stmt_member = $pdo->prepare("SELECT member_no, full_name, email FROM memberz WHERE id = :member_id");
    $stmt_member->execute(['member_id' => $current_member_id]);
    $member_info = $stmt_member->fetch(PDO::FETCH_ASSOC);
    if (!$member_info) {
        // This should ideally not happen if session member_id is valid
        throw new Exception("Logged in member's details not found in memberz table.");
    }
} catch (Exception $e) {
    error_log("Error fetching member details for request_deposit page: " . $e->getMessage());
    $_SESSION['error_message'] = "Could not load your member details. Please try again later.";
    // For now, allow page to load and show error via SweetAlert, handled below
}

// For SweetAlerts (populated by POST handler in next step, or by session messages from this page's logic)
$sa_error = $_SESSION['error_message'] ?? '';
if(isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
$sa_success = $_SESSION['success_message'] ?? '';
if(isset($_SESSION['success_message'])) unset($_SESSION['success_message']);

// Placeholder for POST handling errors to display on form & form value repopulation
// $form_errors will be used for inline errors and also passed to JavaScript for SweetAlert
$form_errors_for_sa = $_SESSION['form_errors'] ?? []; // Explicitly for SA, though content is same as $form_errors
$form_values = $_SESSION['form_values'] ?? []; 
unset($_SESSION['form_errors'], $_SESSION['form_values']); // Clears for next request cycle

$current_date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit_request'])) {
    $form_values = $_POST; // For repopulation

    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'CSRF token validation failed. Please try again.';
        header("Location: " . BASE_URL . "members/request_deposit.php");
        exit;
    }

    $deposit_date = sanitize($_POST['deposit_date'] ?? $current_date); 
    $savings_amount = filter_input(INPUT_POST, 'savings_amount', FILTER_VALIDATE_FLOAT);
    $supporting_document = $_FILES['supporting_document'] ?? null;
    $document_path_to_save = null;

    // --- Validations ---
    if (empty($deposit_date)) { 
        $form_errors['deposit_date'] = "Deposit date is required.";
    } // Future date check can be added if policy changes for deposit_date field (currently readonly)

    if ($savings_amount === false || $savings_amount <= 0) {
        $form_errors['savings_amount'] = "Savings amount must be a positive number.";
    }

    // File Upload Handling
    if (isset($supporting_document) && $supporting_document['error'] == UPLOAD_ERR_OK) {
        $allowed_mime_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        $file_name = $supporting_document['name'];
        $file_tmp_name = $supporting_document['tmp_name'];
        $file_size = $supporting_document['size'];
        $file_mime_type = mime_content_type($file_tmp_name); 
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_mime_type, $allowed_mime_types) || !in_array($file_extension, $allowed_extensions)) {
            $form_errors['supporting_document'] = "Invalid file type. Only PDF, JPG, PNG, GIF are allowed.";
        } elseif ($file_size > $max_file_size) {
            $form_errors['supporting_document'] = "File is too large. Maximum size is 5MB.";
        } else {
            $upload_dir = __DIR__ . '/../assets/uploads/savings_proofs/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) { // Check mkdir return and re-check is_dir
                     error_log("Failed to create upload directory: " . $upload_dir); // Log actual error
                     $form_errors['supporting_document'] = "Server error: Cannot create directory for uploads.";
                }
            }
            
            if (empty($form_errors['supporting_document'])) { // Proceed only if directory exists or was created
                $unique_file_name = uniqid('proof_', true) . '.' . $file_extension;
                $destination_path = $upload_dir . $unique_file_name;

                if (move_uploaded_file($file_tmp_name, $destination_path)) {
                    $document_path_to_save = 'assets/uploads/savings_proofs/' . $unique_file_name; 
                } else {
                    $form_errors['supporting_document'] = "Failed to upload supporting document. Please try again.";
                    error_log("Failed to move uploaded file: " . $file_name . " to " . $destination_path . " (check permissions for " . $upload_dir . ")");
                }
            }
        }
    } elseif (isset($supporting_document) && $supporting_document['error'] != UPLOAD_ERR_NO_FILE && $supporting_document['error'] != UPLOAD_ERR_OK) {
        $form_errors['supporting_document'] = "File upload error: code " . $supporting_document['error'] . ". Please try again or select a smaller file.";
    }

    if (empty($form_errors)) {
        try {
            $pdo->beginTransaction();

            $temp_receipt_no = 'REQ-' . time() . '-' . $current_member_id; 
            $notes_for_request = "Member deposit request. Document: " . ($document_path_to_save ? basename($document_path_to_save) : 'None');

            $stmt_insert_savings = $pdo->prepare(
                "INSERT INTO savings (member_id, amount, date, status, supporting_document_path, recorded_by, receipt_no, notes, created_at) 
                 VALUES (:member_id, :amount, :date, 'Pending Approval', :doc_path, :recorded_by, :receipt_no, :notes, NOW())"
            );
            $stmt_insert_savings->execute([
                ':member_id' => $current_member_id,
                ':amount' => $savings_amount,
                ':date' => $deposit_date,
                ':doc_path' => $document_path_to_save,
                ':recorded_by' => $current_user_id, 
                ':receipt_no' => $temp_receipt_no, 
                ':notes' => $notes_for_request
            ]);
            $new_savings_request_id = $pdo->lastInsertId();
            
            $pdo->commit();

            // --- Email Notifications ---
            $email_sending_errors = [];

            // --- Email to Approvers ---
            $approver_email = 'info.rksavingssystem@gmail.com'; 
            $pdf_link_for_email = rtrim(BASE_URL, '/') . '/savings/generate_request_receipt.php?request_id=' . $new_savings_request_id;

            $subject_to_approvers = "New Savings Deposit Request Pending Approval - " . htmlspecialchars($member_info['full_name'] ?? 'N/A');
            $body_to_approvers_html = "
                <p>A new savings deposit request has been submitted and requires your approval.</p>
                <p><strong>Member:</strong> " . htmlspecialchars($member_info['full_name'] ?? 'N/A') . " (" . htmlspecialchars($member_info['member_no'] ?? 'N/A') . ")</p>
                <p><strong>Amount Requested:</strong> " . htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX') . " " . htmlspecialchars(number_format($savings_amount, 2)) . "</p>
                <p><strong>Date of Intended Deposit:</strong> " . htmlspecialchars(date('d M, Y', strtotime($deposit_date))) . "</p>
                <p><strong>Request ID:</strong> " . htmlspecialchars($temp_receipt_no) . "</p>
                <p>You can view the details and the uploaded document (if any) and process this request in the admin panel.</p>
                <p>(A direct link to the admin review page for this request can be added here once that page exists.)</p>
            ";
            $full_email_to_approvers = generateBasicEmailTemplate($body_to_approvers_html, APP_NAME);

            $mail_to_approvers = new PHPMailer(true);
            try {
                $mail_to_approvers->SMTPDebug = SMTP::DEBUG_OFF;
                $mail_to_approvers->isSMTP();
                $mail_to_approvers->Host       = SMTP_HOST;
                $mail_to_approvers->SMTPAuth   = true;
                $mail_to_approvers->Username   = SMTP_USERNAME;
                $mail_to_approvers->Password   = SMTP_PASSWORD;
                if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'tls') $mail_to_approvers->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                elseif (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') $mail_to_approvers->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                else $mail_to_approvers->SMTPSecure = false;
                $mail_to_approvers->Port = SMTP_PORT;
                $mail_to_approvers->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                $mail_to_approvers->addAddress($approver_email);
                $mail_to_approvers->isHTML(true);
                $mail_to_approvers->Subject = $subject_to_approvers;
                $mail_to_approvers->Body    = $full_email_to_approvers;
                $mail_to_approvers->AltBody = strip_tags(str_replace("<br>", "\n", $body_to_approvers_html));
                $mail_to_approvers->send();
            } catch (Exception $e) {
                $email_sending_errors[] = "Failed to send notification to approvers: " . $mail_to_approvers->ErrorInfo;
                error_log("PHPMailer Error (Savings Request to Approvers for request_id " . $new_savings_request_id . "): " . $mail_to_approvers->ErrorInfo . " (Details: " . $e->getMessage() . ")");
            }

            // --- Email to Submitting User ---
            $user_email = $_SESSION['user']['email'] ?? ($member_info['email'] ?? null); 
            if (!empty($user_email)) {
                $subject_to_user = "Your Savings Deposit Request Received (ID: " . htmlspecialchars($temp_receipt_no) . ") - " . APP_NAME;
                $body_to_user_html = "
                    <p>Dear " . htmlspecialchars($member_info['full_name'] ?? 'User') . ",</p>
                    <p>Thank you for submitting your savings deposit request. Your request is now pending approval.</p>
                    <p><strong>Request Details:</strong></p>
                    <ul>
                        <li><strong>Request ID (Temp Receipt #):</strong> " . htmlspecialchars($temp_receipt_no) . "</li>
                        <li><strong>Amount Requested:</strong> " . htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX') . " " . htmlspecialchars(number_format($savings_amount, 2)) . "</li>
                        <li><strong>Intended Deposit Date:</strong> " . htmlspecialchars(date('d M, Y', strtotime($deposit_date))) . "</li>
                        <li><strong>Status:</strong> Pending Approval</li>
                    </ul>
                    <p>You can download an acknowledgment receipt for your request here: <a href='" . $pdf_link_for_email . "'>Download Request Acknowledgment</a></p>
                    <p>We will notify you once your request has been processed.</p>
                    <p>Regards,<br>The " . APP_NAME . " Team</p>
                ";
                $full_email_to_user = generateBasicEmailTemplate($body_to_user_html, APP_NAME);

                $mail_to_user = new PHPMailer(true);
                try {
                    $mail_to_user->SMTPDebug = SMTP::DEBUG_OFF;
                    $mail_to_user->isSMTP();
                    $mail_to_user->Host       = SMTP_HOST;
                    $mail_to_user->SMTPAuth   = true;
                    $mail_to_user->Username   = SMTP_USERNAME;
                    $mail_to_user->Password   = SMTP_PASSWORD;
                    if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'tls') $mail_to_user->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    elseif (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') $mail_to_user->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    else $mail_to_user->SMTPSecure = false;
                    $mail_to_user->Port       = SMTP_PORT;
                    $mail_to_user->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                    $mail_to_user->addAddress($user_email, ($member_info['full_name'] ?? 'Valued Member'));
                    $mail_to_user->isHTML(true);
                    $mail_to_user->Subject = $subject_to_user;
                    $mail_to_user->Body    = $full_email_to_user;
                    $mail_to_user->AltBody = strip_tags(str_replace("<br>", "\n", $body_to_user_html));
                    $mail_to_user->send();
                } catch (Exception $e) {
                    $email_sending_errors[] = "Failed to send confirmation email to you: " . $mail_to_user->ErrorInfo;
                    error_log("PHPMailer Error (Savings Request to User " . $user_email . " for request_id " . $new_savings_request_id . "): " . $mail_to_user->ErrorInfo . " (Details: " . $e->getMessage() . ")");
                }
            } else {
                $email_sending_errors[] = "Your email address is not available to send a confirmation.";
                error_log("Savings Request User Email: User ID " . ($_SESSION['user']['id'] ?? 'Unknown') . " has no email in session or member_info for request_id " . $new_savings_request_id);
            }

            if (empty($email_sending_errors)) {
                $_SESSION['success_message'] = "Your savings deposit request (ID: {$new_savings_request_id}) submitted successfully! Notifications have been sent.";
            } else {
                $_SESSION['success_message'] = "Your savings deposit request (ID: {$new_savings_request_id}) submitted. Some email notifications may have failed: " . implode("; ", $email_sending_errors);
            }
            
            unset($_SESSION['form_values']); 
            header("Location: " . BASE_URL . "members/request_deposit.php");
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("PDOException during savings deposit request: " . $e->getMessage());
            $_SESSION['error_message'] = "A database error occurred. Please try again.";
            header("Location: " . BASE_URL . "members/request_deposit.php");
            exit;
        } catch (Exception $e) { 
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Exception during savings deposit request: " . $e->getMessage());
            $_SESSION['error_message'] = "An unexpected error occurred: " . $e->getMessage();
            header("Location: " . BASE_URL . "members/request_deposit.php");
            exit;
        }
    } else {
        $_SESSION['form_errors'] = $form_errors;
        $_SESSION['form_values'] = $form_values; 
        if(empty($_SESSION['error_message'])) { 
             $_SESSION['error_message'] = "Please check the form for errors and fill all required fields.";
        }
        header("Location: " . BASE_URL . "members/request_deposit.php"); 
        exit;
    }
}

// Re-initialize $sa_error, $sa_success, $form_errors, $form_values for the GET request display after redirect
// This is important because the script exits on POST, and these are re-read from session at the top for new request.
// The $sa_error and $sa_success are already handled at the top. $form_errors and $form_values are also handled.
// No specific re-initialization needed here before HTML starts.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .form-control:disabled, .form-control[readonly] {
            background-color: #e9ecef;
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-piggy-bank me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        Your Deposit Information
                    </div>
                    <div class="card-body">
                        <?php if ($member_info): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Member Name:</strong></label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($member_info['full_name']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Member Number:</strong></label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($member_info['member_no']); ?></p>
                                </div>
                            </div>
                            <hr>

                            <form method="POST" action="request_deposit.php" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="deposit_date" class="form-label">Date of Deposit</label>
                                        <input type="date" name="deposit_date" id="deposit_date" class="form-control" 
                                               value="<?= htmlspecialchars($form_values['deposit_date'] ?? $current_date); ?>" readonly>
                                        <?php if (isset($form_errors['deposit_date'])): ?>
                                            <div class="text-danger mt-1"><small><?= htmlspecialchars($form_errors['deposit_date']); ?></small></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="savings_amount" class="form-label">Amount to Deposit (<?php echo htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX'); ?>)</label>
                                        <input type="number" name="savings_amount" id="savings_amount" class="form-control" 
                                               value="<?= htmlspecialchars($form_values['savings_amount'] ?? ''); ?>" 
                                               required min="1" step="any">
                                        <?php if (isset($form_errors['savings_amount'])): ?>
                                            <div class="text-danger mt-1"><small><?= htmlspecialchars($form_errors['savings_amount']); ?></small></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="supporting_document" class="form-label">Supporting Document (e.g., Deposit Slip)</label>
                                    <input type="file" name="supporting_document" id="supporting_document" class="form-control" accept="application/pdf,image/jpeg,image/png">
                                    <small class="form-text text-muted">Optional. Max file size: 5MB. Allowed types: PDF, JPG, PNG.</small>
                                    <?php if (isset($form_errors['supporting_document'])): ?>
                                        <div class="text-danger mt-1"><small><?= htmlspecialchars($form_errors['supporting_document']); ?></small></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" name="submit_deposit_request" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Deposit Request
                                    </button>
                                    <a href="<?php echo BASE_URL . 'index.php'; ?>" class="btn btn-secondary ms-2">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                Could not load your member details. Please ensure your member account is correctly linked to your user profile or contact support.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const saFormErrors = <?php echo json_encode($form_errors_for_sa); ?>;
            const saError = '<?php echo addslashes(htmlspecialchars($sa_error ?? '')); ?>'; // Ensure htmlspecialchars
            const saSuccess = '<?php echo addslashes(htmlspecialchars($sa_success ?? '')); ?>'; // Ensure htmlspecialchars

            if (Object.keys(saFormErrors).length > 0) {
                let errorHtml = '<ul style="text-align: left; list-style-position: inside; padding-left: 0;">';
                for (const key in saFormErrors) {
                    // Ensure error messages are also escaped for HTML context if they can contain HTML
                    errorHtml += '<li>' + saFormErrors[key].replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</li>';
                }
                errorHtml += '</ul>';
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Errors',
                    html: errorHtml,
                    footer: 'Please correct the indicated errors and try again.'
                });
            } else if (saError) {
                Swal.fire({
                    icon: 'error',
                    title: 'An Error Occurred',
                    text: saError, // saError is already htmlspecialchars'd and addslashed in PHP
                });
            } else if (saSuccess) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: saSuccess, // saSuccess is already htmlspecialchars'd and addslashed in PHP
                });
            }
        });
    </script>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
