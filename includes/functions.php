<?php
/**
 * Formats Ugandan phone numbers to a standard format
 * @param string $phone The raw phone number
 * @return string Formatted phone number (256XXXXXXXXX)
 */

function formatUgandanPhone($phone) {
    // Remove all non-digit characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to standard Ugandan format (256...)
    if (strlen($cleaned) == 9) {
        // If starts with 0 (e.g., 0755123456)
        if (substr($cleaned, 0, 1) == '0') {
            return '256' . substr($cleaned, 1);
        }
        // If starts with 7 (e.g., 755123456)
        return '256' . $cleaned;
    }
    elseif (strlen($cleaned) == 10 && substr($cleaned, 0, 3) == '256') {
        // Already in correct format (256755123456)
        return $cleaned;
    }
    
    // Return original if doesn't match expected patterns
    return $phone;
}

//viewloan
function getLoanStatusBadge($status) {
    $statusClasses = [
        'Pending' => 'warning',
        'Approved' => 'info',
        'Active' => 'primary',
        'Completed' => 'success',
        'Defaulted' => 'danger'
    ];
    return $statusClasses[$status] ?? 'secondary';
}

function formatDate($date) {
    if (empty($date) || $date === '0000-00-00') return 'N/A';
    try {
        return (new DateTime($date))->format('d M Y');
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}
?>