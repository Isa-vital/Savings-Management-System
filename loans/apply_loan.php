<?php
require_once __DIR__ . '/../config.php'; // Defines $pdo, BASE_URL, APP_NAME, starts session
require_once __DIR__ . '/../helpers/auth.php'; // For require_login, has_role

require_login(); // Ensure user is logged in

// Access Control: Only 'Member' role with a valid member_id can apply
if (!has_role('Member') || !isset($_SESSION['user']['member_id']) || empty($_SESSION['user']['member_id'])) {
    $_SESSION['error_message'] = "You must be a registered member to apply for a loan.";
    header("Location: " . BASE_URL . "index.php"); // Redirect to their dashboard or landing
    exit;
}

$current_user_id = $_SESSION['user']['id']; // User ID from users table
$current_member_id = $_SESSION['user']['member_id']; // Member ID from memberz table (applicant)
$page_title = "Apply for Loan";
$page_errors = []; // For errors specific to this page load (e.g. DB error fetching referees)

// Fetch other members for referee selection (excluding the applicant)
$other_members = [];
try {
    $stmt_members = $pdo->prepare("SELECT id, full_name, member_no FROM memberz WHERE id != :current_member_id ORDER BY full_name ASC");
    $stmt_members->execute(['current_member_id' => $current_member_id]);
    $other_members = $stmt_members->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching members for referee list in apply_loan.php: " . $e->getMessage());
    $page_errors[] = "Could not load list of potential referees. Please try again later.";
}

// For SweetAlerts (populated by POST handler in next step, or by session messages from other pages)
$sa_error = $_SESSION['error_message'] ?? '';
if(isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
$sa_success = $_SESSION['success_message'] ?? '';
if(isset($_SESSION['success_message'])) unset($_SESSION['success_message']);

// Placeholder for POST handling errors to display on form
$form_errors = $_SESSION['form_errors'] ?? []; // From POST validation failures
$form_values = $_SESSION['form_values'] ?? []; // To repopulate form
unset($_SESSION['form_errors'], $_SESSION['form_values']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Repopulate $form_values for sticky form, even if some are overwritten by sanitization later
    // This ensures select dropdowns also repopulate correctly with what was submitted
    $form_values = $_POST;
    $form_errors = []; // Reset errors for current submission

    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        $form_errors['csrf'] = 'CSRF token validation failed. Please try again.';
        // Optionally, set a general session error for SweetAlert if preferred for CSRF
        $_SESSION['error_message'] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $loan_amount = filter_input(INPUT_POST, 'loan_amount', FILTER_VALIDATE_FLOAT);
        $loan_purpose = sanitize($_POST['loan_purpose'] ?? ''); // sanitize from config.php
        $referee1_member_id = filter_input(INPUT_POST, 'referee1_member_id', FILTER_VALIDATE_INT);
        $referee2_member_id = filter_input(INPUT_POST, 'referee2_member_id', FILTER_VALIDATE_INT);

        // --- Validations ---
        if ($loan_amount === false || $loan_amount <= 0) {
            $form_errors['loan_amount'] = "Loan amount must be a positive number.";
        }
        if (empty($loan_purpose)) {
            $form_errors['loan_purpose'] = "Purpose of loan is required.";
        }
        if (strlen($loan_purpose) > 1000) { // Example max length
            $form_errors['loan_purpose'] = "Purpose is too long (max 1000 characters).";
        }
        if (empty($referee1_member_id)) {
            $form_errors['referee1'] = "Referee 1 is required.";
        }
        if (empty($referee2_member_id)) {
            $form_errors['referee2'] = "Referee 2 is required.";
        }
        if ($referee1_member_id && $referee1_member_id == $current_member_id) {
            $form_errors['referee1'] = "You cannot select yourself as Referee 1.";
        }
        if ($referee2_member_id && $referee2_member_id == $current_member_id) {
            $form_errors['referee2'] = "You cannot select yourself as Referee 2.";
        }
        if ($referee1_member_id && $referee2_member_id && $referee1_member_id == $referee2_member_id) {
            $form_errors['referee2'] = "Referee 2 must be different from Referee 1.";
        }

        // Verify referees are valid members (exist in $other_members list or query DB)
        $valid_referee1 = false; $valid_referee2 = false;
        foreach ($other_members as $om) { // $other_members is fetched earlier
            if ($om['id'] == $referee1_member_id) $valid_referee1 = true;
            if ($om['id'] == $referee2_member_id) $valid_referee2 = true;
        }
        if ($referee1_member_id && !$valid_referee1) {
            $form_errors['referee1'] = "Selected Referee 1 is not a valid member.";
        }
        if ($referee2_member_id && !$valid_referee2) {
            $form_errors['referee2'] = "Selected Referee 2 is not a valid member.";
        }


        if (empty($form_errors)) {
            try {
                // --- Eligibility Check ---
                $stmt_applicant_savings = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings WHERE member_id = :member_id");
                $stmt_applicant_savings->execute(['member_id' => $current_member_id]);
                $applicant_savings = $stmt_applicant_savings->fetchColumn();

                $stmt_ref1_savings = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings WHERE member_id = :member_id");
                $stmt_ref1_savings->execute(['member_id' => $referee1_member_id]);
                $referee1_savings = $stmt_ref1_savings->fetchColumn();

                $stmt_ref2_savings = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings WHERE member_id = :member_id");
                $stmt_ref2_savings->execute(['member_id' => $referee2_member_id]);
                $referee2_savings = $stmt_ref2_savings->fetchColumn();

                $max_eligible_loan = $applicant_savings + $referee1_savings + $referee2_savings;

                if ($loan_amount > $max_eligible_loan) {
                    $_SESSION['error_message'] = "Requested loan amount (" . htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX') . " " . number_format($loan_amount, 2) . ") exceeds the maximum eligible amount of " . htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX') . " " . number_format($max_eligible_loan, 2) . " (based on your savings and referees' savings).";
                    // No redirect here, let the page reload and display SweetAlert from session.
                    // To show inline error as well for loan_amount field:
                    $form_errors['loan_amount'] = "Amount exceeds maximum eligible (max: " . htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX') . " " . number_format($max_eligible_loan, 2) . ").";
                } else {
                    // --- Save Application ---
                    $pdo->beginTransaction();

                    $loan_number = 'LN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

                    $stmt_insert_loan = $pdo->prepare(
                        "INSERT INTO loans (member_id, loan_number, amount, purpose, application_date, status, referee1_member_id, referee2_member_id, created_by, created_at)
                         VALUES (:member_id, :loan_number, :amount, :purpose, NOW(), 'pending', :referee1_id, :referee2_id, :created_by, NOW())"
                    );
                    $stmt_insert_loan->execute([
                        ':member_id' => $current_member_id,
                        ':loan_number' => $loan_number,
                        ':amount' => $loan_amount,
                        ':purpose' => $loan_purpose,
                        ':referee1_id' => $referee1_member_id,
                        ':referee2_id' => $referee2_member_id,
                        ':created_by' => $current_user_id
                    ]);

                    $pdo->commit();
                    $_SESSION['success_message'] = "Loan application submitted successfully! Your loan number is " . $loan_number . ". You will be notified once it's reviewed.";
                    // Clear form values from session on success
                    unset($_SESSION['form_values']);
                    header("Location: " . BASE_URL . "loans/apply_loan.php");
                    exit;
                }

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("PDOException during loan application: " . $e->getMessage());
                $_SESSION['error_message'] = "A database error occurred while submitting your application. Please try again.";
            } catch (Exception $e) {
                 if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Exception during loan application: " . $e->getMessage());
                // Use the specific exception message if it's a validation one, otherwise generic
                $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
            }
        }
    }
    // If form_errors is populated, or _SESSION['error_message'] is set
    if (!empty($form_errors) && !isset($_SESSION['error_message'])) {
         // If only form field errors, set a general session message for SweetAlert
         $_SESSION['error_message'] = "Please correct the errors in the form.";
    }
    // Pass back $form_errors and $form_values to session for repopulation if redirecting due to error or for general display
    $_SESSION['form_errors'] = $form_errors;
    // No explicit redirect here for errors; the page will re-render and show messages.
    // If $_SESSION['error_message'] was set, SweetAlert will pick it up on reload (if we redirect).
    // If we don't redirect on validation error, then $sa_error needs to be set from $form_errors for current page load.
    if(!empty($form_errors) && empty($_SESSION['error_message'])) {
        $sa_error = "Please correct the validation errors highlighted below.";
    } else if (!empty($_SESSION['error_message'])) {
        $sa_error = $_SESSION['error_message']; // Ensure $sa_error is populated for current render
        unset($_SESSION['error_message']); // Unset it as it's now in $sa_error for current display
    }
    // $form_values is already set to $_POST at the start of POST block.
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Savings App'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Optional: Add any page-specific styles here */
    </style>
</head>
<body>
    <?php include __DIR__ . '/../partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-hand-holding-usd me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <?php if (!empty($page_errors)): // Display page-level errors like DB failure for referees ?>
                    <div class="alert alert-danger">
                        <?php foreach ($page_errors as $err): echo '<p class="mb-0">' . htmlspecialchars($err) . '</p>'; endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Loan Application Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'loans/apply_loan.php'); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateToken()); ?>">

                            <div class="mb-3">
                                <label for="loan_amount" class="form-label">Loan Amount Requested (<?php echo htmlspecialchars(defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX'); ?>)</label>
                                <input type="number" name="loan_amount" id="loan_amount" class="form-control <?php echo isset($form_errors['loan_amount']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($form_values['loan_amount'] ?? ''); ?>" required min="1000" step="100">
                                <?php if (isset($form_errors['loan_amount'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form_errors['loan_amount']); ?></div><?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="loan_purpose" class="form-label">Purpose of Loan</label>
                                <textarea name="loan_purpose" id="loan_purpose" class="form-control <?php echo isset($form_errors['loan_purpose']) ? 'is-invalid' : ''; ?>"
                                          rows="3" required><?php echo htmlspecialchars($form_values['loan_purpose'] ?? ''); ?></textarea>
                                <?php if (isset($form_errors['loan_purpose'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form_errors['loan_purpose']); ?></div><?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="referee1_member_id" class="form-label">Select Referee 1</label>
                                    <select name="referee1_member_id" id="referee1_member_id" class="form-select <?php echo isset($form_errors['referee1']) ? 'is-invalid' : ''; ?>" required>
                                        <option value="">-- Select Referee 1 --</option>
                                        <?php foreach ($other_members as $m): ?>
                                            <option value="<?php echo $m['id']; ?>" <?php echo (($form_values['referee1_member_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($m['full_name'] . ' (' . $m['member_no'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($form_errors['referee1'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form_errors['referee1']); ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="referee2_member_id" class="form-label">Select Referee 2</label>
                                    <select name="referee2_member_id" id="referee2_member_id" class="form-select <?php echo isset($form_errors['referee2']) ? 'is-invalid' : ''; ?>" required>
                                        <option value="">-- Select Referee 2 --</option>
                                        <?php foreach ($other_members as $m): ?>
                                            <option value="<?php echo $m['id']; ?>" <?php echo (($form_values['referee2_member_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($m['full_name'] . ' (' . $m['member_no'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($form_errors['referee2'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form_errors['referee2']); ?></div><?php endif; ?>
                                </div>
                            </div>
                            <p class="form-text text-muted">Referees must be active members of the SACCO and cannot be the same person.</p>

                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit Loan Application</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($sa_error)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: '<?php echo addslashes(htmlspecialchars($sa_error)); ?>',
                });
            <?php endif; ?>
            <?php if (!empty($sa_success)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo addslashes(htmlspecialchars($sa_success)); ?>',
                });
            <?php endif; ?>

            // Basic client-side check to prevent selecting same referee twice
            // More robust validation will be on server-side
            const referee1Select = document.getElementById('referee1_member_id');
            const referee2Select = document.getElementById('referee2_member_id');

            function validateReferees() {
                if (referee1Select.value !== '' && referee1Select.value === referee2Select.value) {
                    // referee2Select.setCustomValidity('Referee 2 must be different from Referee 1.');
                    // For now, let server handle this, or add more advanced JS validation.
                    // console.warn("Referees are the same. Server-side validation will catch this.");
                } else {
                    // referee2Select.setCustomValidity('');
                }
            }
            if(referee1Select && referee2Select) {
                referee1Select.addEventListener('change', validateReferees);
                referee2Select.addEventListener('change', validateReferees);
            }
        });
    </script>
</body>
</html>
