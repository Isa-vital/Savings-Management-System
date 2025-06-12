<?php
// session_start(); // Session is now handled by config.php

require_once __DIR__ . '/../config.php';      // Provides $pdo, BASE_URL, APP_NAME, SMTP settings etc.
require_once __DIR__ . '/../helpers/auth.php'; // For require_login, has_role
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer
require_once __DIR__ . '/../emails/email_template.php'; // Email template function

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_login(); // Redirects to login if not authenticated

if (!has_role(['Core Admin', 'Administrator'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Check if member ID is provided
if (!isset($_GET['member_id'])) {
    $_SESSION['error_message'] = "Member ID not specified"; // Use error_message for consistency
    header('Location: ' . BASE_URL . 'members/memberslist.php'); // Use BASE_URL
    exit();
}

$member_id = intval($_GET['member_id']);
$member = null; // Initialize member
$error = null; // Initialize error for POST block

try {
    // Fetch member details - including email now
    $stmt = $pdo->prepare("SELECT id, member_no, full_name, email FROM memberz WHERE id = :member_id");
    $stmt->execute([':member_id' => $member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['error_message'] = "Member not found";
        header('Location: ' . BASE_URL . 'members/memberslist.php'); // Use BASE_URL
        exit();
    }

    // Process deposit form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deposit'])) {
        $deposit_recorded_successfully = false; // Flag for email sending
        try {
            $pdo->beginTransaction();

            $amount = floatval($_POST['amount']);
            $date = sanitize($_POST['date'] ?? '');
            $receipt_no = sanitize($_POST['receipt_no'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            $deposit_type = sanitize($_POST['deposit_type'] ?? 'regular');

            // Validate required fields
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than 0");
            }
            if (empty($date)) {
                throw new Exception("Date is required");
            }
            if (empty($receipt_no)) { // Assuming receipt_no is required
                throw new Exception("Receipt number is required.");
            }


            // Insert new deposit record
            $stmt_insert_savings = $pdo->prepare("INSERT INTO savings
                (member_id, amount, date, receipt_no, notes, deposit_type, recorded_by) 
                VALUES (:member_id, :amount, :date, :receipt_no, :notes, :deposit_type, :recorded_by)");
            
            $stmt_insert_savings->execute([
                ':member_id' => $member_id,
                ':amount' => $amount,
                ':date' => $date,
                ':receipt_no' => $receipt_no,
                ':notes' => $notes,
                ':deposit_type' => $deposit_type,
                ':recorded_by' => $_SESSION['user']['id']
            ]);
            
            $pdo->commit();
            $deposit_recorded_successfully = true;

            // ---- BEGIN EMAIL SENDING ----
            if ($deposit_recorded_successfully && !empty($member['email'])) {
                // Recalculate total savings for the receipt (or pass from earlier calculation if accurate)
                $stmt_total_savings = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM savings WHERE member_id = :member_id");
                $stmt_total_savings->execute([':member_id' => $member_id]);
                $new_total_savings = $stmt_total_savings->fetchColumn();

                $email_subject = "Savings Deposit Confirmation - " . (defined('APP_NAME') ? APP_NAME : 'Our SACCO');
                $receipt_body_html = "
                    <p>Dear " . htmlspecialchars($member['full_name']) . ",</p>
                    <p>This email confirms a recent savings deposit to your account:</p>
                    <ul>
                        <li><strong>Transaction Date:</strong> " . htmlspecialchars(date("d M Y", strtotime($date))) . "</li>
                        <li><strong>Receipt Number:</strong> " . htmlspecialchars($receipt_no) . "</li>
                        <li><strong>Amount Deposited:</strong> " . htmlspecialchars($settings['currency_symbol'] ?? 'UGX') . " " . htmlspecialchars(number_format($amount, 2)) . "</li>
                        <li><strong>Deposit Type:</strong> " . htmlspecialchars(ucfirst($deposit_type)) . "</li>
                        <li><strong>Notes:</strong> " . htmlspecialchars($notes ?: 'N/A') . "</li>
                    </ul>
                    <p><strong>Your new total savings balance is: " . htmlspecialchars($settings['currency_symbol'] ?? 'UGX') . " " . htmlspecialchars(number_format($new_total_savings, 2)) . "</strong></p>
                    <p>Thank you for saving with us!</p>
                ";

                $full_email_html = generateBasicEmailTemplate($receipt_body_html, (defined('APP_NAME') ? APP_NAME : 'Our SACCO'));

                $mail = new PHPMailer(true);
                try {
                    $mail->SMTPDebug = SMTP::DEBUG_OFF; // SMTP::DEBUG_SERVER for verbose output
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'tls') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    } elseif (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    } else { $mail->SMTPSecure = false; }
                    $mail->Port       = SMTP_PORT;

                    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                    $mail->addAddress($member['email'], $member['full_name']);
                    $mail->isHTML(true);
                    $mail->Subject = $email_subject;
                    $mail->Body    = $full_email_html;
                    $mail->AltBody = strip_tags(str_replace(["<br>", "<br/>", "<p>", "</p>", "<ul>", "</ul>", "<li>", "</li>", "<strong>", "</strong>"], ["\n", "\n", "\n", "\n", "\n", "\n", " - ", "\n", "*", "*"], $receipt_body_html));


                    $mail->send();
                    $_SESSION['success_message'] = "Deposit recorded successfully and email receipt sent to " . htmlspecialchars($member['email']) . ".";
                } catch (Exception $e) { // PHPMailer Exception
                    error_log("PHPMailer Error sending deposit receipt to " . $member['email'] . " for member_id " . $member_id . ": " . $mail->ErrorInfo . " (Exception: " . $e->getMessage() . ")");
                    $_SESSION['success_message'] = "Deposit recorded successfully, but the email receipt could not be sent. Please check system email settings.";
                }
            } elseif ($deposit_recorded_successfully && empty($member['email'])) {
                $_SESSION['success_message'] = "Deposit recorded successfully. No email address on file for this member to send a receipt.";
                 error_log("Deposit recorded for member_id " . $member_id . " but no email address found to send receipt.");
            }
            // ---- END EMAIL SENDING ----

            // Redirect after processing
            header("Location: " . BASE_URL . "savings/deposit.php?member_id=" . $member_id);
            exit();
            
        } catch (PDOException $e) { // This catch block was already there
            if($pdo->inTransaction()) $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    // Fetch savings history
    $stmt = $pdo->prepare("SELECT * FROM savings WHERE member_id = :member_id ORDER BY date DESC");
    $stmt->execute([':member_id' => $member_id]);
    $savings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total savings
    $total_savings = array_sum(array_column($savings, 'amount'));

} catch (PDOException $e) {
    error_log("Database error in deposit.php: " . $e->getMessage());
    $_SESSION['error'] = "A database error occurred. Please try again.";
    header('Location: ../members/memberslist.php');
    exit();
}
/**
 * Fetches the full name or username of a user by their ID.
 * @param PDO $pdo
 * @param int $user_id
 * @return string
 */
function getUserNameById($pdo, $user_id) {
    if (empty($user_id)) return 'Unknown';
    $stmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        return !empty($user['full_name']) ? $user['full_name'] : $user['username'];
    }
    return 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Deposit - <?= htmlspecialchars($member['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .member-info-card {
            border-left: 5px solid #28a745;
        }
        .savings-table th {
            background-color: #f8f9fa;
        }
        .total-savings {
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../partials/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-money-bill-wave me-2"></i>Record Deposit
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="<?php echo BASE_URL; ?>members/memberslist.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Members List
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): // Local error from POST processing ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card member-info-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1 text-muted">Member Number</p>
                                <p class="fw-bold"><?= htmlspecialchars($member['member_no']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1 text-muted">Full Name</p>
                                <p class="fw-bold"><?= htmlspecialchars($member['full_name']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1 text-muted">Total Savings</p>
                                <p class="fw-bold total-savings">UGX <?= htmlspecialchars(number_format($total_savings, 2)) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>New Deposit
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Amount (UGX)</label>
                                        <input type="number" class="form-control" name="amount" 
                                            min="1000" step="100" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Date</label>
                                        <input type="date" class="form-control" name="date" 
                                            max="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Receipt Number</label>
                                        <input type="text" class="form-control" name="receipt_no" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Deposit Type</label>
                                        <select class="form-select" name="deposit_type" required>
                                            <option value="regular">Regular Savings</option>
                                            <option value="special">Special Deposit</option>
                                            <option value="registration">Registration Fee</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Notes</label>
                                        <textarea class="form-control" name="notes" rows="2"></textarea>
                                    </div>
                                    
                                    <button type="submit" name="add_deposit" class="btn btn-success w-100 py-2 fw-bold">
                                        <i class="fas fa-save me-1"></i> Record Deposit
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Deposit History
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($savings)): ?>
                                    <div class="alert alert-info">No deposit records found</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table savings-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Receipt No</th>
                                                    <th>Type</th>
                                                    <th>Recorded By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($savings as $deposit): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars(date('d M Y', strtotime($deposit['date']))) ?></td>
                                                        <td class="fw-bold">UGX <?= htmlspecialchars(number_format($deposit['amount'], 2)) ?></td>
                                                        <td><?= htmlspecialchars($deposit['receipt_no']) ?></td>
                                                        <td><?= htmlspecialchars(ucfirst($deposit['deposit_type'])) ?></td>
                                                        <td><?= htmlspecialchars(getUserNameById($pdo, $deposit['recorded_by'])) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set default date to today
        document.querySelector('input[name="date"]').valueAsDate = new Date();
        
        // Format amount field on input
        document.querySelector('input[name="amount"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>