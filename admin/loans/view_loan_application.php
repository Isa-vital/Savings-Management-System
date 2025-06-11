<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/auth.php';
// Assuming PHPMailer might be used for notifications from this page later
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
require_once __DIR__ . '/../../emails/email_template.php'; // Added for email template

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_login();
if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$page_title = "View Loan Application";
$loan_id = filter_input(INPUT_GET, 'loan_id', FILTER_VALIDATE_INT);

// Fallback for APP_NAME and BASE_URL if not defined in config.php (should be defined)
if (!defined('APP_NAME')) { define('APP_NAME', 'Savings App'); }
if (!defined('BASE_URL')) { define('BASE_URL', '/'); } // Adjust if needed
if (!defined('APP_CURRENCY_SYMBOL')) { define('APP_CURRENCY_SYMBOL', 'UGX');}


if (!$loan_id || $loan_id <= 0) {
    $_SESSION['error_message'] = "Invalid loan ID specified.";
    header("Location: " . BASE_URL . "admin/loans/loan_applications_list.php");
    exit;
}

$loan_details = null;
$applicant_details = null;
$applicant_savings = 0;
$referee1_details = null;
$referee1_savings = 0;
$referee2_details = null;
$referee2_savings = 0;
$max_eligible_loan = 0;
$processing_admin_details = null;
$page_specific_errors = []; // Use this for errors on this page load, not session for this part

// For SweetAlerts from session (e.g., after an action on this page if it reloads itself)
$sa_error = $_SESSION['error_message'] ?? '';
if(isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
$sa_success = $_SESSION['success_message'] ?? '';
if(isset($_SESSION['success_message'])) unset($_SESSION['success_message']);


try {
    // Fetch main loan details - adjust columns as per your 'loans' table structure
    $stmt_loan = $pdo->prepare("SELECT l.*,
                                la.amount_approved, la.interest_rate, la.term_months, la.repayment_start_date, la.monthly_payment, la.total_repayment,
                                lr.rejection_reason, lr.processed_at as rejection_processed_at, lr.processed_by as rejected_by_user_id
                                FROM loans l
                                LEFT JOIN loan_approvals la ON l.id = la.loan_id
                                LEFT JOIN loan_rejections lr ON l.id = lr.loan_id
                                WHERE l.id = :loan_id");
    $stmt_loan->execute(['loan_id' => $loan_id]);
    $loan_details = $stmt_loan->fetch(PDO::FETCH_ASSOC);

    if (!$loan_details) {
        $_SESSION['error_message'] = "Loan application not found.";
        header("Location: " . BASE_URL . "admin/loans/loan_applications_list.php");
        exit;
    }

    // Fetch applicant details and their savings
    $stmt_applicant = $pdo->prepare("SELECT m.id, m.full_name, m.member_no, m.email, m.phone, COALESCE(SUM(s.amount), 0) AS total_savings
                                     FROM memberz m
                                     LEFT JOIN savings s ON m.id = s.member_id
                                     WHERE m.id = :member_id GROUP BY m.id");
    $stmt_applicant->execute(['member_id' => $loan_details['member_id']]);
    $applicant_details = $stmt_applicant->fetch(PDO::FETCH_ASSOC);
    $applicant_savings = $applicant_details['total_savings'] ?? 0;

    // Fetch Referee 1 details and savings
    if (!empty($loan_details['referee1_member_id'])) {
        $stmt_ref1 = $pdo->prepare("SELECT m.id, m.full_name, m.member_no, m.email, m.phone, COALESCE(SUM(s.amount), 0) AS total_savings
                                   FROM memberz m
                                   LEFT JOIN savings s ON m.id = s.member_id
                                   WHERE m.id = :member_id GROUP BY m.id");
        $stmt_ref1->execute(['member_id' => $loan_details['referee1_member_id']]);
        $referee1_details = $stmt_ref1->fetch(PDO::FETCH_ASSOC);
        $referee1_savings = $referee1_details['total_savings'] ?? 0;
    }

    // Fetch Referee 2 details and savings
    if (!empty($loan_details['referee2_member_id'])) {
        $stmt_ref2 = $pdo->prepare("SELECT m.id, m.full_name, m.member_no, m.email, m.phone, COALESCE(SUM(s.amount), 0) AS total_savings
                                   FROM memberz m
                                   LEFT JOIN savings s ON m.id = s.member_id
                                   WHERE m.id = :member_id GROUP BY m.id");
        $stmt_ref2->execute(['member_id' => $loan_details['referee2_member_id']]);
        $referee2_details = $stmt_ref2->fetch(PDO::FETCH_ASSOC);
        $referee2_savings = $referee2_details['total_savings'] ?? 0;
    }

    $max_eligible_loan = $applicant_savings + $referee1_savings + $referee2_savings;

    // Fetch details of admin who processed (approved/rejected) the loan
    $processor_user_id_val = null;
    if ($loan_details['status'] === 'approved' && !empty($loan_details['processed_by'])) { // Assuming 'approved_by' is 'processed_by' on approval
        $processor_user_id_val = $loan_details['processed_by'];
    } elseif ($loan_details['status'] === 'rejected' && !empty($loan_details['rejected_by_user_id'])) {
        $processor_user_id_val = $loan_details['rejected_by_user_id'];
    }

    if ($processor_user_id_val) {
        $stmt_admin = $pdo->prepare("SELECT username, email FROM users WHERE id = :user_id");
        $stmt_admin->execute(['user_id' => $processor_user_id_val]);
        $processing_admin_details = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error fetching loan application details for loan_id {$loan_id}: " . $e->getMessage());
    $page_specific_errors[] = "Database error fetching loan details: " . $e->getMessage();
}

// POST Request Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // $user_id is admin performing action, $loan_details['member_id'] is applicant memberz.id
    // $loan_details['id'] is the loan_id

    // Ensure $loan_id from GET is still the context for POST operations
    if (!$loan_id || !$loan_details) { // $loan_details must be fetched from GET part
        $_SESSION['error_message'] = "Cannot process action: Loan context is missing or invalid.";
        header("Location: " . BASE_URL . "admin/loans/loan_applications_list.php");
        exit;
    }

    if (isset($_POST['action'])) {
        if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
            $_SESSION['error_message'] = 'CSRF token validation failed. Please try again.';
            header("Location: " . BASE_URL . "admin/loans/view_loan_application.php?loan_id=" . $loan_id);
            exit;
        }

        if ($_POST['action'] === 'approve_loan') {
            $submitted_loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
            $approved_amount = filter_input(INPUT_POST, 'amount_approved', FILTER_VALIDATE_FLOAT);
            $interest_rate_annual = filter_input(INPUT_POST, 'interest_rate', FILTER_VALIDATE_FLOAT);
            $term_months = filter_input(INPUT_POST, 'term_months', FILTER_VALIDATE_INT);
            $repayment_start_date_str = sanitize($_POST['repayment_start_date'] ?? '');

            $current_action_errors = [];

            if (!$submitted_loan_id || $submitted_loan_id != $loan_id) {
                $current_action_errors[] = "Loan ID mismatch. Approval failed.";
            }
            if ($approved_amount === false || $approved_amount <= 0) {
                $current_action_errors[] = "Approved amount must be a positive number.";
            }
            if ($interest_rate_annual === false || $interest_rate_annual < 0) {
                $current_action_errors[] = "Interest rate must be a non-negative number.";
            }
            if ($term_months === false || $term_months <= 0) {
                $current_action_errors[] = "Loan term must be a positive number of months.";
            }
            if (empty($repayment_start_date_str)) {
                $current_action_errors[] = "Repayment start date is required.";
            } else {
                $start_date_timestamp = strtotime($repayment_start_date_str);
                if ($start_date_timestamp === false) {
                    $current_action_errors[] = "Invalid repayment start date format.";
                }
            }
            if ($loan_details['status'] !== 'pending') {
                 $current_action_errors[] = "This loan is no longer pending and cannot be approved again.";
            }

            if (empty($current_action_errors)) {
                if (!function_exists('calculateLoanRepayments')) {
                    function calculateLoanRepayments($principal, $annual_interest_rate, $loan_term_months) {
                        if ($loan_term_months <= 0) return ['monthly_payment' => 0, 'total_repayment' => $principal, 'total_interest' => 0];
                        if ($annual_interest_rate == 0) {
                            return [
                                'monthly_payment' => round($principal / $loan_term_months, 2),
                                'total_repayment' => round($principal, 2),
                                'total_interest' => 0
                            ];
                        }
                        $monthly_interest_rate = ($annual_interest_rate / 100) / 12;
                        if ($monthly_interest_rate == 0) {
                             return [
                                'monthly_payment' => round($principal / $loan_term_months, 2),
                                'total_repayment' => round($principal, 2),
                                'total_interest' => 0
                            ];
                        }
                        $pow_term = pow(1 + $monthly_interest_rate, $loan_term_months);
                        if ($pow_term == 1) {
                            $monthly_payment = $principal / $loan_term_months;
                        } else {
                            $monthly_payment = $principal * ($monthly_interest_rate * $pow_term) / ($pow_term - 1);
                        }
                        $total_repayment = $monthly_payment * $loan_term_months;
                        $total_interest = $total_repayment - $principal;
                        return [
                            'monthly_payment' => round($monthly_payment, 2),
                            'total_repayment' => round($total_repayment, 2),
                            'total_interest' => round($total_interest, 2)
                        ];
                    }
                }

                $repayments = calculateLoanRepayments($approved_amount, $interest_rate_annual, $term_months);
                $monthly_repayment = $repayments['monthly_payment'];
                $total_repayment = $repayments['total_repayment'];

                try {
                    $pdo->beginTransaction();
                    // Insert into loan_approvals table
                    $stmt_approval = $pdo->prepare(
                        "INSERT INTO loan_approvals (loan_id, amount_approved, interest_rate, term_months, repayment_start_date, monthly_payment, total_repayment, approved_by_user_id, approved_at)
                         VALUES (:loan_id, :amount_approved, :interest_rate, :term_months, :repayment_start_date, :monthly_payment, :total_repayment, :approved_by_user_id, NOW())"
                    );
                    $stmt_approval->execute([
                        ':loan_id' => $loan_id,
                        ':amount_approved' => $approved_amount,
                        ':interest_rate' => $interest_rate_annual,
                        ':term_months' => $term_months,
                        ':repayment_start_date' => $repayment_start_date_str,
                        ':monthly_payment' => $monthly_repayment,
                        ':total_repayment' => $total_repayment,
                        ':approved_by_user_id' => $_SESSION['user']['id']
                    ]);

                    // Update loans table status
                    $update_loan_stmt = $pdo->prepare(
                        "UPDATE loans SET status = 'approved', processed_by = :processed_by, processed_at = NOW()
                         WHERE id = :loan_id AND status = 'pending'"
                    );
                    $update_loan_stmt->execute([
                        ':processed_by' => $_SESSION['user']['id'],
                        ':loan_id' => $loan_id
                    ]);

                    if ($update_loan_stmt->rowCount() > 0 && $stmt_approval->rowCount() > 0) {
                        $pdo->commit();
                        $_SESSION['success_message'] = "Loan (#" . htmlspecialchars($loan_details['loan_number']) . ") approved successfully.";

                        if (!empty($applicant_details['email'])) {
                            $mail = new PHPMailer(true);
                            try {
                                $mail->SMTPDebug = SMTP::DEBUG_OFF;
                                $mail->isSMTP();
                                $mail->Host       = SMTP_HOST;
                                $mail->SMTPAuth   = true;
                                $mail->Username   = SMTP_USERNAME;
                                $mail->Password   = SMTP_PASSWORD;
                                if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                elseif (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                else $mail->SMTPSecure = false;
                                $mail->Port = SMTP_PORT;
                                $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                                $mail->addAddress($applicant_details['email'], $applicant_details['full_name']);
                                $mail->isHTML(true);
                                $mail->Subject = "Loan Application Approved - " . APP_NAME;

                                $email_body_content = "<p>Dear " . htmlspecialchars($applicant_details['full_name']) . ",</p>" .
                                              "<p>Congratulations! Your loan application (<strong>#" . htmlspecialchars($loan_details['loan_number']) . "</strong>) has been approved.</p>" .
                                              "<h4>Loan Details:</h4>" .
                                              "<ul>" .
                                              "<li>Approved Amount: " . htmlspecialchars(APP_CURRENCY_SYMBOL . ' ' . number_format($approved_amount, 2)) . "</li>" .
                                              "<li>Interest Rate: " . htmlspecialchars($interest_rate_annual) . "% per annum</li>" .
                                              "<li>Loan Term: " . htmlspecialchars($term_months) . " months</li>" .
                                              "<li>Monthly Repayment: " . htmlspecialchars(APP_CURRENCY_SYMBOL . ' ' . number_format($monthly_repayment, 2)) . "</li>" .
                                              "<li>Total Repayment: " . htmlspecialchars(APP_CURRENCY_SYMBOL . ' ' . number_format($total_repayment, 2)) . "</li>" .
                                              "<li>Repayment Start Date: " . htmlspecialchars(date('d M, Y', strtotime($repayment_start_date_str))) . "</li>" .
                                              "</ul>" .
                                              "<p>Please contact us if you have any questions.</p>";
                                $full_email_html = generateBasicEmailTemplate($email_body_content, APP_NAME);

                                $mail->Body    = $full_email_html;
                                $mail->AltBody = strip_tags(str_replace("<br>", "\n", $email_body_content));

                                $mail->send();
                                $_SESSION['success_message'] .= " Approval email sent to member.";
                            } catch (Exception $e_mail) { // PHPMailer Exception
                                error_log("PHPMailer Error (Loan Approval for loan_id " . $loan_id . "): " . $mail->ErrorInfo . " (Details: " . $e_mail->getMessage() . ")");
                                $_SESSION['error_message'] = "Loan approved, but failed to send approval email. Error: " . $mail->ErrorInfo;
                            }
                        } else {
                            $_SESSION['success_message'] .= " Member has no email address on file for notification.";
                        }
                    } else {
                        $pdo->rollBack();
                        $_SESSION['error_message'] = "Could not approve loan. It might have been processed by another admin or status changed. Please refresh.";
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("PDOException during loan approval: " . $e->getMessage());
                    $_SESSION['error_message'] = "Database error during loan approval. Please try again.";
                } catch (Exception $e) {
                     if ($pdo->inTransaction()) $pdo->rollBack();
                     error_log("Exception during loan approval: " . $e->getMessage());
                    $_SESSION['error_message'] = "An unexpected error occurred: " . $e->getMessage();
                }
            } else {
                $_SESSION['error_message'] = "Please correct the errors in the approval form: <br>" . implode("<br>", array_map('htmlspecialchars', $current_action_errors));
            }
            header("Location: " . BASE_URL . "admin/loans/view_loan_application.php?loan_id=" . $loan_id);
            exit;
        } elseif (isset($_POST['action']) && $_POST['action'] === 'reject_loan') {
            // CSRF is already validated for any POST action if structure is common
            $submitted_loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
            $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');

            $current_action_errors = [];

            if (!$submitted_loan_id || $submitted_loan_id != $loan_id) {
                $current_action_errors[] = "Loan ID mismatch. Rejection failed.";
            }
            if ($loan_details['status'] !== 'pending') {
                 $current_action_errors[] = "This loan is no longer pending and cannot be processed again.";
            }
            if (strlen($rejection_reason) > 2000) {
                $current_action_errors[] = "Rejection reason is too long (max 2000 characters).";
            }

            if (empty($current_action_errors)) {
                try {
                    $pdo->beginTransaction();
                    // Update loans table
                    // Also NULLIFY approval fields in case some data was errantly entered or to clean state
                    $stmt_reject_loan = $pdo->prepare(
                        "UPDATE loans SET
                            status = 'rejected',
                            rejection_reason = :rejection_reason,
                            processed_by = :processed_by,
                            processed_at = NOW(),
                            approved_by_user_id = NULL,
                            approved_at = NULL,
                            amount_approved = NULL,
                            interest_rate = NULL,
                            term_months = NULL,
                            repayment_start_date = NULL,
                            monthly_repayment = NULL,
                            total_repayment = NULL
                         WHERE id = :loan_id AND status = 'pending'"
                    );
                    $stmt_reject_loan->execute([
                        ':rejection_reason' => !empty($rejection_reason) ? $rejection_reason : null,
                        ':processed_by' => $_SESSION['user']['id'],
                        ':loan_id' => $loan_id
                    ]);

                    // Insert into loan_rejections table (if it exists and is used for history)
                    // For this example, rejection_reason is directly on loans table.
                    // If a separate loan_rejections table exists like loan_approvals:
                    /*
                    $stmt_rejection_log = $pdo->prepare(
                        "INSERT INTO loan_rejections (loan_id, rejection_reason, processed_by_user_id, processed_at)
                         VALUES (:loan_id, :rejection_reason, :processed_by_user_id, NOW())"
                    );
                    $stmt_rejection_log->execute([
                        ':loan_id' => $loan_id,
                        ':rejection_reason' => !empty($rejection_reason) ? $rejection_reason : null,
                        ':processed_by_user_id' => $_SESSION['user']['id']
                    ]);
                    */

                    if ($stmt_reject_loan->rowCount() > 0) {
                        $pdo->commit();
                        $_SESSION['success_message'] = "Loan (#" . htmlspecialchars($loan_details['loan_number']) . ") rejected successfully.";
                        // Optional: Send Loan Rejection Email (skipped for this subtask)
                    } else {
                        $pdo->rollBack();
                        $_SESSION['error_message'] = "Could not reject loan. It might have been processed by another admin or status changed. Please refresh.";
                    }

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("PDOException during loan rejection: " . $e->getMessage());
                    $_SESSION['error_message'] = "Database error during loan rejection: " . $e->getMessage();
                } catch (Exception $e) {
                     if ($pdo->inTransaction()) $pdo->rollBack();
                     error_log("Exception during loan rejection: " . $e->getMessage());
                    $_SESSION['error_message'] = "An unexpected error occurred: " . $e->getMessage();
                }
            } else {
                $_SESSION['error_message'] = "Validation errors for rejection: <br>" . implode("<br>", array_map('htmlspecialchars', $current_action_errors));
            }
            header("Location: " . BASE_URL . "admin/loans/view_loan_application.php?loan_id=" . $loan_id);
            exit;
        }
        // Ensure this elseif is correctly placed within the main if (isset($_POST['action'])) block
    } else { // No action specified in POST
        $_SESSION['error_message'] = "Invalid action specified.";
        header("Location: " . BASE_URL . "admin/loans/view_loan_application.php?loan_id=" . $loan_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { margin-bottom: 0.5rem; }
        .card-header h5 { display: flex; align-items: center; }
        .card-header h5 .fas { margin-right: 0.5rem; }
        .badge-status { font-size: 0.9em; padding: 0.5em 0.75em;}
        .amount-highlight { font-weight: bold; color: #198754; }
        .amount-warning { font-weight: bold; color: #dc3545; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-invoice-dollar me-2"></i>
                        <?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($loan_details['loan_number'] ?? 'N/A'); ?>
                    </h1>
                    <a href="<?php echo BASE_URL; ?>admin/loans/loan_applications_list.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>

                <?php if (!empty($page_specific_errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($page_specific_errors as $err): echo '<p class="mb-0">' . htmlspecialchars($err) . '</p>'; endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($loan_details && empty($page_specific_errors)): ?>
                <div class="row">
                    <!-- Column 1: Loan & Applicant Details -->
                    <div class="col-lg-7 col-xl-8 mb-4">
                        <!-- Loan Application Summary Card -->
                        <div class="card mb-4">
                            <div class="card-header"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Loan Summary</h5></div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4 detail-label">Loan Number:</dt>
                                    <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($loan_details['loan_number']); ?></dd>

                                    <dt class="col-sm-4 detail-label">Amount Applied:</dt>
                                    <dd class="col-sm-8 detail-value amount-highlight"><?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($loan_details['amount'], 2)); ?></dd>

                                    <dt class="col-sm-4 detail-label">Max Eligible Amount:</dt>
                                    <dd class="col-sm-8 detail-value <?php echo ($loan_details['amount'] > $max_eligible_loan) ? 'amount-warning' : ''; ?>">
                                        <?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($max_eligible_loan, 2)); ?>
                                        <?php if ($loan_details['amount'] > $max_eligible_loan): ?>
                                            <i class="fas fa-exclamation-triangle text-danger ms-1" title="Applied amount exceeds maximum eligible based on savings."></i>
                                        <?php endif; ?>
                                    </dd>

                                    <dt class="col-sm-4 detail-label">Purpose:</dt>
                                    <dd class="col-sm-8 detail-value"><?php echo nl2br(htmlspecialchars($loan_details['purpose'])); ?></dd>

                                    <dt class="col-sm-4 detail-label">Application Date:</dt>
                                    <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($loan_details['application_date']))); ?></dd>

                                    <dt class="col-sm-4 detail-label">Current Status:</dt>
                                    <dd class="col-sm-8 detail-value">
                                        <?php
                                            $status_badge = 'secondary'; // Default
                                            if ($loan_details['status'] === 'pending') $status_badge = 'warning text-dark';
                                            elseif ($loan_details['status'] === 'approved' || $loan_details['status'] === 'active') $status_badge = 'success';
                                            elseif ($loan_details['status'] === 'rejected' || $loan_details['status'] === 'defaulted') $status_badge = 'danger';
                                            elseif ($loan_details['status'] === 'completed') $status_badge = 'info text-dark';
                                        ?>
                                        <span class="badge bg-<?php echo $status_badge; ?> badge-status"><?php echo htmlspecialchars(ucfirst($loan_details['status'])); ?></span>
                                    </dd>

                                    <?php if ($loan_details['status'] === 'approved' || $loan_details['status'] === 'active' || $loan_details['status'] === 'completed'): ?>
                                        <dt class="col-sm-4 detail-label">Approved Amount:</dt>
                                        <dd class="col-sm-8 detail-value amount-highlight"><?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($loan_details['amount_approved'] ?? $loan_details['amount'], 2)); ?></dd>

                                        <dt class="col-sm-4 detail-label">Interest Rate:</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($loan_details['interest_rate'] ?? 'N/A'); ?>%</dd>

                                        <dt class="col-sm-4 detail-label">Term (Months):</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($loan_details['term_months'] ?? 'N/A'); ?></dd>

                                        <dt class="col-sm-4 detail-label">Monthly Payment:</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($loan_details['monthly_payment'] ?? 0, 2)); ?></dd>

                                        <dt class="col-sm-4 detail-label">Total Repayment:</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($loan_details['total_repayment'] ?? 0, 2)); ?></dd>

                                        <dt class="col-sm-4 detail-label">Repayment Start Date:</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo !empty($loan_details['repayment_start_date']) ? htmlspecialchars(date('d M Y', strtotime($loan_details['repayment_start_date']))) : 'N/A'; ?></dd>

                                        <dt class="col-sm-4 detail-label">Processed By:</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($processing_admin_details['username'] ?? 'N/A'); ?></dd>

                                        <dt class="col-sm-4 detail-label">Processed At:</dt> <!-- Assuming processed_by and processed_at are on loans table -->
                                        <dd class="col-sm-8 detail-value"><?php echo !empty($loan_details['processed_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($loan_details['processed_at']))) : 'N/A'; ?></dd>
                                    <?php elseif ($loan_details['status'] === 'rejected'): ?>
                                        <dt class="col-sm-4 detail-label">Rejection Reason:</dt>
                                        <dd class="col-sm-8 detail-value text-danger"><?php echo nl2br(htmlspecialchars($loan_details['rejection_reason'] ?? 'No reason provided.')); ?></dd>

                                        <dt class="col-sm-4 detail-label">Processed By:</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($processing_admin_details['username'] ?? 'N/A'); ?></dd>

                                        <dt class="col-sm-4 detail-label">Processed At:</dt>
                                        <dd class="col-sm-8 detail-value"><?php echo !empty($loan_details['rejection_processed_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($loan_details['rejection_processed_at']))) : 'N/A'; ?></dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>

                        <!-- Applicant Details Card -->
                        <?php if ($applicant_details): ?>
                        <div class="card mb-4">
                            <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Applicant Details</h5></div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4 detail-label">Full Name:</dt>
                                    <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($applicant_details['full_name']); ?></dd>
                                    <dt class="col-sm-4 detail-label">Member No:</dt>
                                    <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($applicant_details['member_no']); ?></dd>
                                    <dt class="col-sm-4 detail-label">Email:</dt>
                                    <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($applicant_details['email'] ?? 'N/A'); ?></dd>
                                    <dt class="col-sm-4 detail-label">Phone:</dt>
                                    <dd class="col-sm-8 detail-value"><?php echo htmlspecialchars($applicant_details['phone'] ?? 'N/A'); ?></dd>
                                    <dt class="col-sm-4 detail-label">Total Savings:</dt>
                                    <dd class="col-sm-8 detail-value amount-highlight"><?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($applicant_savings, 2)); ?></dd>
                                </dl>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div> <!-- End Column 1 -->

                    <!-- Column 2: Referees & Actions -->
                    <div class="col-lg-5 col-xl-4">
                        <!-- Referee 1 Details Card -->
                        <?php if ($referee1_details): ?>
                        <div class="card mb-4">
                            <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Referee 1 Details</h5></div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-5 detail-label">Full Name:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee1_details['full_name']); ?></dd>
                                    <dt class="col-sm-5 detail-label">Member No:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee1_details['member_no']); ?></dd>
                                    <dt class="col-sm-5 detail-label">Email:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee1_details['email'] ?? 'N/A'); ?></dd>
                                    <dt class="col-sm-5 detail-label">Phone:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee1_details['phone'] ?? 'N/A'); ?></dd>
                                    <dt class="col-sm-5 detail-label">Total Savings:</dt>
                                    <dd class="col-sm-7 detail-value amount-highlight"><?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($referee1_savings, 2)); ?></dd>
                                </dl>
                            </div>
                        </div>
                        <?php elseif(!empty($loan_details['referee1_member_id'])): ?>
                             <div class="alert alert-warning">Details for Referee 1 (ID: <?php echo htmlspecialchars($loan_details['referee1_member_id']); ?>) not found.</div>
                        <?php endif; ?>

                        <!-- Referee 2 Details Card -->
                        <?php if ($referee2_details): ?>
                        <div class="card mb-4">
                            <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Referee 2 Details</h5></div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-5 detail-label">Full Name:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee2_details['full_name']); ?></dd>
                                    <dt class="col-sm-5 detail-label">Member No:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee2_details['member_no']); ?></dd>
                                    <dt class="col-sm-5 detail-label">Email:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee2_details['email'] ?? 'N/A'); ?></dd>
                                    <dt class="col-sm-5 detail-label">Phone:</dt>
                                    <dd class="col-sm-7 detail-value"><?php echo htmlspecialchars($referee2_details['phone'] ?? 'N/A'); ?></dd>
                                    <dt class="col-sm-5 detail-label">Total Savings:</dt>
                                    <dd class="col-sm-7 detail-value amount-highlight"><?php echo APP_CURRENCY_SYMBOL . " " . htmlspecialchars(number_format($referee2_savings, 2)); ?></dd>
                                </dl>
                            </div>
                        </div>
                        <?php elseif(!empty($loan_details['referee2_member_id'])): ?>
                             <div class="alert alert-warning">Details for Referee 2 (ID: <?php echo htmlspecialchars($loan_details['referee2_member_id']); ?>) not found.</div>
                        <?php endif; ?>

                        <!-- Actions Card -->
                        <?php if ($loan_details['status'] === 'pending'): ?>
                        <div class="card mb-4">
                            <div class="card-header"><h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Process Application</h5></div>
                            <div class="card-body text-center">
                                <p class="mb-3">Review the application details and take action:</p>
                                <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#approveLoanModal">
                                    <i class="fas fa-check-circle me-2"></i>Approve Loan
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectLoanModal">
                                    <i class="fas fa-times-circle me-2"></i>Reject Loan
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div> <!-- End Column 2 -->
                </div> <!-- End Row -->
                <?php else: ?>
                    <?php if (empty($page_specific_errors)): ?>
                    <div class="alert alert-warning">Loan application data could not be fully loaded.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($sa_error)): ?>
                Swal.fire({ icon: 'error', title: 'Oops...', text: '<?php echo addslashes(htmlspecialchars($sa_error)); ?>' });
            <?php endif; ?>
            <?php if (!empty($sa_success)): ?>
                Swal.fire({ icon: 'success', title: 'Success!', text: '<?php echo addslashes(htmlspecialchars($sa_success)); ?>' });
            <?php endif; ?>
        });
    </script>

    <!-- Approve Loan Modal -->
    <div class="modal fade" id="approveLoanModal" tabindex="-1" aria-labelledby="approveLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> <!-- modal-lg for a slightly wider modal -->
            <div class="modal-content">
                <form method="POST" action="view_loan_application.php?loan_id=<?php echo htmlspecialchars($loan_details['id'] ?? ''); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                    <input type="hidden" name="action" value="approve_loan">
                    <input type="hidden" name="loan_id" value="<?php echo htmlspecialchars($loan_details['id'] ?? ''); ?>">

                    <div class="modal-header">
                        <h5 class="modal-title" id="approveLoanModalLabel">Approve Loan Application - <?php echo htmlspecialchars($loan_details['loan_number'] ?? ''); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Applicant:</strong> <?php echo htmlspecialchars($applicant_details['full_name'] ?? 'N/A'); ?></p>
                        <p><strong>Amount Applied:</strong> <?php echo htmlspecialchars(APP_CURRENCY_SYMBOL . ' ' . number_format($loan_details['amount'] ?? 0, 2)); // amount is amount_applied from loans table ?></p>
                        <hr>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="amount_approved" class="form-label">Approved Amount <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="amount_approved" name="amount_approved"
                                       value="<?php echo htmlspecialchars($loan_details['amount'] ?? ''); // Default to applied amount ?>"
                                       step="100" min="0" required>
                                <small class="form-text text-muted">Adjust if different from amount applied.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="interest_rate" class="form-label">Interest Rate (% per annum) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="interest_rate" name="interest_rate"
                                       value="<?php echo htmlspecialchars($system_settings['default_loan_interest_rate'] ?? '10'); ?>"
                                       step="0.1" min="0" max="100" required>
                                <small class="form-text text-muted">E.g., 10 for 10% p.a.</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="term_months" class="form-label">Loan Term (Months) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="term_months" name="term_months"
                                       value="<?php echo htmlspecialchars($loan_details['term_months_applied'] ?? '12'); // Assuming a term_months_applied field or default to 12 ?>"
                                       step="1" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="repayment_start_date" class="form-label">Repayment Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="repayment_start_date" name="repayment_start_date"
                                       value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>" required>
                            </div>
                        </div>
                        <div class="alert alert-info small">
                            Note: Monthly repayment and total repayment will be calculated automatically based on these terms.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i>Confirm & Approve Loan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Loan Modal -->
    <div class="modal fade" id="rejectLoanModal" tabindex="-1" aria-labelledby="rejectLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="view_loan_application.php?loan_id=<?php echo htmlspecialchars($loan_details['id'] ?? ''); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">
                    <input type="hidden" name="action" value="reject_loan">
                    <input type="hidden" name="loan_id" value="<?php echo htmlspecialchars($loan_details['id'] ?? ''); ?>">

                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectLoanModalLabel">Reject Loan Application - <?php echo htmlspecialchars($loan_details['loan_number'] ?? ''); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Applicant:</strong> <?php echo htmlspecialchars($applicant_details['full_name'] ?? 'N/A'); ?></p>
                        <p><strong>Amount Applied:</strong> <?php echo htmlspecialchars( (defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX') . ' ' . number_format($loan_details['amount'] ?? 0, 2)); // amount is amount_applied from loans table ?></p>
                        <hr>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection (Optional)</label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3"></textarea>
                            <small class="form-text text-muted">This reason may be shared with the applicant.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this loan application?');">
                            <i class="fas fa-times-circle me-2"></i>Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
