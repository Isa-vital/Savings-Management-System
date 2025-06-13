<?php
require_once __DIR__ . '/../config.php'; // Defines $pdo, BASE_URL, APP_NAME, starts session
require_once __DIR__ . '/../helpers/auth.php'; // For require_login, has_role
require_once __DIR__ . '/../vendor/autoload.php'; // For Dompdf (and PHPMailer if ever needed here)

use Dompdf\Dompdf;
use Dompdf\Options;

require_login(); // Ensure user is logged in

$request_id = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
$savings_record = null;
$member_details = null;

if (!$request_id || $request_id <= 0) {
    // Instead of dying, redirect or show user-friendly error. For a PDF script, dying is simpler.
    http_response_code(400);
    die("Invalid savings request ID provided.");
}

try {
    $stmt_savings = $pdo->prepare("SELECT s.* FROM savings s WHERE s.id = :request_id");
    $stmt_savings->execute(['request_id' => $request_id]);
    $savings_record = $stmt_savings->fetch(PDO::FETCH_ASSOC);

    if (!$savings_record) {
        http_response_code(404);
        die("Savings request not found for the given ID.");
    }

    // Authorization Check: Owner or Admin
    $is_owner = (isset($_SESSION['user']['member_id']) && $_SESSION['user']['member_id'] == $savings_record['member_id']);
    $is_admin = has_role(['Core Admin', 'Administrator']);

    if (!$is_owner && !$is_admin) {
        http_response_code(403);
        die("Access Denied. You do not have permission to view this receipt.");
    }

    // Fetch associated member details
    $stmt_member = $pdo->prepare("SELECT m.full_name, m.member_no FROM memberz m WHERE m.id = :member_id");
    $stmt_member->execute(['member_id' => $savings_record['member_id']]);
    $member_details = $stmt_member->fetch(PDO::FETCH_ASSOC);

    if (!$member_details) { // Should not happen if savings_record.member_id is valid
        http_response_code(500);
        die("Could not retrieve member details for this savings request.");
    }

} catch (PDOException $e) {
    error_log("PDOException in generate_request_receipt.php for request_id " . $request_id . ": " . $e->getMessage());
    http_response_code(500);
    die("Database error occurred while fetching receipt details.");
}

// --- HTML Content Generation for PDF ---
$app_name_pdf = defined('APP_NAME') ? APP_NAME : 'Savings System';
$currency_symbol_pdf = defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : 'UGX';

// Determine status class for styling
$status_class = 'status-pending'; // Default
if ($savings_record['status'] === 'Approved') {
    $status_class = 'status-approved';
} elseif ($savings_record['status'] === 'Rejected') {
    $status_class = 'status-rejected';
}


// Precompute values for use in heredoc
$date_submitted = date('d M, Y H:i', strtotime($savings_record['created_at']));
$member_name = htmlspecialchars($member_details['full_name']);
$member_no = htmlspecialchars($member_details['member_no']);
$amount_requested = htmlspecialchars($currency_symbol_pdf . ' ' . number_format($savings_record['amount'], 2));
$date_of_deposit = date('d M, Y', strtotime($savings_record['date']));
$status = htmlspecialchars($savings_record['status']);
$generated_on = date('d M, Y H:i:s');
$current_year = date('Y');

$html_content = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Savings Request Receipt - {$savings_record['receipt_no']}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; color: #333; font-size: 12px; }
        .container { width: 90%; margin: 20px auto; padding: 15px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #004085; padding-bottom: 10px;}
        .header h1 { margin: 0; font-size: 22px; color: #004085;}
        .header h2 { margin: 5px 0 0; font-size: 18px; color: #555;}
        .details-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .details-table th, .details-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .details-table th { background-color: #f2f2f2; font-weight: bold; width: 35%;}
        .footer { text-align: center; font-size: 0.8em; color: #777; margin-top: 25px; padding-top: 10px; border-top: 1px solid #eee; }
        .status { font-weight: bold; padding: 4px 8px; border-radius: 4px; color: white; display: inline-block; }
        .status-pending { background-color: #ffc107; color: #333; } /* Yellowish */
        .status-approved { background-color: #28a745; } /* Green */
        .status-rejected { background-color: #dc3545; } /* Red */
        .text-muted { color: #6c757d; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$app_name_pdf}</h1>
            <h2>Savings Deposit Request Acknowledgment</h2>
        </div>

        <h3>Request Details</h3>
        <table class="details-table">
            <tr><th>Request ID (Temp Receipt #):</th><td>{$savings_record['receipt_no']}</td></tr>
            <tr><th>Date Submitted:</th><td>{$date_submitted}</td></tr>
            <tr><th>Member Name:</th><td>{$member_name}</td></tr>
            <tr><th>Member Number:</th><td>{$member_no}</td></tr>
            <tr><th>Amount Requested:</th><td>{$amount_requested}</td></tr>
            <tr><th>Date of Intended Deposit:</th><td>{$date_of_deposit}</td></tr>
            <tr><th>Status:</th><td><span class="status {$status_class}">{$status}</span></td></tr>
        </table>

        <p>This document acknowledges that your request to deposit the amount specified above has been received. Its current status is <strong>{$status}</strong>.</p>
        <p class="text-muted">If your request is 'Pending Approval', you will be notified once it has been processed. If 'Approved', this serves as a temporary acknowledgment until the official receipt is generated post-confirmation. If 'Rejected', please check with administration for reasons.</p>
        
        <div class="footer">
            <p>Generated on: {$generated_on}</p>
            <p>&copy; {$current_year} {$app_name_pdf}. All Rights Reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

// --- PDF Generation with Dompdf ---
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // For images from remote URLs, if any in future
    // $options->set('defaultFont', 'Arial'); // Optional: set default font if system fonts are an issue

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html_content);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = "Savings_Request_Ack_" . str_replace([' ', '/', '\\', ':', '*','?','"','<','>','|'], '_', $savings_record['receipt_no'] ?: $request_id) . ".pdf";
    $dompdf->stream($filename, ["Attachment" => 0]); // 1 = force download, 0 = view in browser (more user friendly for acknowledgment)
    exit; // Ensure no further output after PDF stream

} catch (Exception $e) {
    error_log("Dompdf Exception in generate_request_receipt.php for request_id " . $request_id . ": " . $e->getMessage());
    http_response_code(500);
    // Provide a more user-friendly error message if possible, rather than just dying.
    echo "Error generating PDF document. An error occurred: " . htmlspecialchars($e->getMessage()) . ". Please try again later or contact support.";
    // die("Error generating PDF document. Please try again later or contact support.");
}
?>
