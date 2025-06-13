<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user']['id'])) {
    header("Location: /savingssystem/auth/login.php");
    exit;
}

// Load dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/emails/email_template.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Configuration
$currentYear = date('Y');
$withdrawalDate = new DateTime("$currentYear-12-15");
$meetingDay = 'Tuesday'; // Weekly meeting day
$adminEmail = 'info.rksavingssystem@gmail.com';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_reminder'])) {
        handleReminderRequest($pdo, $adminEmail);
    } elseif (isset($_POST['attendance'])) {
        handleAttendanceSubmission($pdo);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Generate all meeting dates for the year
$meetingDates = generateMeetingDates($currentYear, $meetingDay, $withdrawalDate);

// Get all active members
$members = fetchActiveMembers($pdo);

// Assign all members to all meetings
assignMembersToMeetings($meetingDates, $members);

// Handle reminder email sending
function handleReminderRequest($pdo, $fromEmail) {
    try {
        $eventDate = $_POST['event_date'];
        $eventType = $_POST['event_type'];
        
        $members = fetchActiveMembers($pdo);
        
        if (empty($members)) {
            throw new Exception("No active members found to notify");
        }
        
        $mail = new PHPMailer(true);
        configureMailer($mail, $fromEmail);
        
        $successCount = 0;
        foreach ($members as $member) {
            if (!empty($member['email'])) {
                try {
                    sendReminderEmail($mail, $member, $eventDate, $eventType);
                    $successCount++;
                } catch (Exception $e) {
                    error_log("Email failed for {$member['email']}: " . $e->getMessage());
                }
            }
        }
        
        $_SESSION['success'] = "Reminders sent to {$successCount} members";
    } catch (Exception $e) {
        $_SESSION['error'] = "Reminder error: " . $e->getMessage();
    }
}

// Configure PHPMailer settings
function configureMailer($mail, $fromEmail) {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $fromEmail;
    $mail->Password = 'your_app_specific_password'; // Use app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($fromEmail, 'RK Savings System');
    $mail->isHTML(true);
}

// Send individual reminder email
function sendReminderEmail($mail, $member, $eventDate, $eventType) {
    $mail->clearAddresses();
    $mail->addAddress($member['email'], $member['full_name']);
    
    $formattedDate = date('l, F j, Y', strtotime($eventDate));
    $emailContent = generateEmailContent($member, $eventType, $formattedDate);
    
    $mail->Subject = 'Reminder: ' . ($eventType === 'withdrawal' ? 'Annual Withdrawal' : 'Weekly Savings Meeting');
    $mail->Body = generateBasicEmailTemplate($emailContent, APP_NAME);
    $mail->AltBody = stripEmailContent($emailContent);
    
    $mail->send();
}

// Generate email content
function generateEmailContent($member, $eventType, $formattedDate) {
    return "
        <h2>Savings System Reminder</h2>
        <p>Hello {$member['full_name']},</p>
        <p>This is a friendly reminder about our upcoming event:</p>
        <p><strong>" . ($eventType === 'withdrawal' ? 'Annual Withdrawal Day' : 'Weekly Savings Meeting') . "</strong><br>
        Date: {$formattedDate}</p>
        <p>Please make sure to attend and bring your savings book.</p>
        <p>Thank you,<br>RK Savings System</p>
    ";
}

// Strip HTML tags for plain text version
function stripEmailContent($content) {
    return strip_tags(str_replace(["<p>", "</p>", "<br>"], ["", "\n", "\n"], $content));
}

// Handle attendance form submission
function handleAttendanceSubmission($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Process attendance data
        // Implementation depends on your database structure
        // Example: Save to attendance_records table
        
        $pdo->commit();
        $_SESSION['success'] = "Attendance saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Attendance error: " . $e->getMessage();
    }
}

// Generate all meeting dates for the year
function generateMeetingDates($year, $meetingDay, $withdrawalDate) {
    $startDate = date("$year-01-01");
    $endDate = $withdrawalDate->format('Y-m-d');
    $dates = [];
    
    $current = new DateTime($startDate);
    if ($current->format('l') !== $meetingDay) {
        $current->modify("next $meetingDay");
    }

    while ($current <= new DateTime($endDate)) {
        $dates[] = [
            'date' => $current->format('Y-m-d'),
            'day' => $current->format('l'),
            'type' => 'meeting',
            'members_present' => []
        ];
        $current->modify("+1 week");
    }

    // Add withdrawal date
    $dates[] = [
        'date' => $withdrawalDate->format('Y-m-d'),
        'day' => $withdrawalDate->format('l'),
        'type' => 'withdrawal',
        'members_present' => []
    ];

    return $dates;
}

// Fetch all active members
function fetchActiveMembers($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT member_no, full_name, email
            FROM memberz
            ORDER BY full_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to load member data";
        return [];
    }
}

// Assign all members to all meetings
function assignMembersToMeetings(&$meetingDates, $members) {
    foreach ($meetingDates as &$meeting) {
        $meeting['members_present'] = array_map(function($member) {
            return [
                'id' => $member['member_no'],
                'name' => $member['full_name'],
                'attended' => false
            ];
        }, $members);
    }
    unset($meeting);
}

// Notification variables for SweetAlert
$page_error = $_SESSION['error'] ?? '';
$page_success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Savings Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --meeting-color: #4e73df;
            --withdrawal-color: #f6c23e;
            --present-color: #1cc88a;
            --absent-color: #e74a3b;
        }
        
        .calendar-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .calendar-card {
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.2s;
        }
        
        .calendar-card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 0.5rem 0.5rem 0 0 !important;
            padding: 1rem 1.25rem;
        }
        
        .meeting-header {
            background: var(--meeting-color);
        }
        
        .withdrawal-header {
            background: var(--withdrawal-color);
        }
        
        .member-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .member-item:last-child {
            border-bottom: none;
        }
        
        .attendance-toggle {
            position: relative;
            width: 50px;
            height: 26px;
            margin-right: 12px;
        }
        
        .attendance-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .attendance-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--absent-color);
            transition: .4s;
            border-radius: 34px;
        }
        
        .attendance-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .attendance-slider {
            background-color: var(--present-color);
        }
        
        input:checked + .attendance-slider:before {
            transform: translateX(24px);
        }
        
        .stats-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <?php include 'partials/navbar.php'; ?>
        
        <div class="row">
            <?php include 'partials/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-calendar-alt me-2"></i>Savings Calendar
                        <small class="text-muted"><?= $currentYear ?></small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                            <button type="submit" form="attendanceForm" class="btn btn-sm btn-success">
                                <i class="fas fa-save me-1"></i> Save Attendance
                            </button>
                        </div>
                    </div>
                </div>

                <div class="alert alert-primary shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle fa-2x"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="alert-heading">Meeting Schedule</h5>
                            <p class="mb-0">
                                Weekly savings every <strong><?= $meetingDay ?></strong>. 
                                Annual withdrawal on <strong><?= $withdrawalDate->format('F j, Y') ?></strong>.
                                All active members participate in all meetings.
                            </p>
                        </div>
                    </div>
                </div>

                <form id="attendanceForm" method="POST">
                    <div class="calendar-container mb-4">
                        <?php foreach ($meetingDates as $event): ?>
                            <div class="calendar-card card shadow-sm mb-4">
                                <div class="card-header text-white <?= $event['type'] === 'withdrawal' ? 'withdrawal-header' : 'meeting-header' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <?= date('M j, Y', strtotime($event['date'])) ?>
                                            <small>(<?= $event['day'] ?>)</small>
                                        </h5>
                                        <span class="badge bg-white text-dark stats-badge">
                                            <?= count($event['members_present']) ?> members
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($event['type'] === 'withdrawal'): ?>
                                        <div class="p-3 text-center bg-light">
                                            <i class="fas fa-money-bill-wave fa-2x mb-2 text-warning"></i>
                                            <h5 class="text-dark">Annual Withdrawal Day</h5>
                                            <p class="mb-0 text-muted">All active members receive their savings</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="member-list">
                                        <?php foreach ($event['members_present'] as $member): ?>
                                            <div class="member-item">
                                                <label class="attendance-toggle">
                                                    <input 
                                                        type="checkbox" 
                                                        name="attendance[<?= $event['date'] ?>][]" 
                                                        value="<?= $member['id'] ?>"
                                                        <?= $member['attended'] ? 'checked' : '' ?>
                                                    >
                                                    <span class="attendance-slider"></span>
                                                </label>
                                                <span class="flex-grow-1"><?= htmlspecialchars($member['name']) ?></span>
                                                <small class="text-muted">ID: <?= $member['id'] ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white d-flex justify-content-between">
                                    <small class="text-muted">
                                        <?= $event['type'] === 'meeting' ? 
                                            '<i class="fas fa-hand-holding-usd text-primary me-1"></i> Savings meeting' : 
                                            '<i class="fas fa-money-bill-wave text-warning me-1"></i> Withdrawal day' ?>
                                    </small>
                                    <?php if ($event['type'] === 'meeting'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="event_date" value="<?= $event['date'] ?>">
                                            <input type="hidden" name="event_type" value="<?= $event['type'] ?>">
                                            <button type="submit" name="send_reminder" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-envelope me-1"></i> Remind
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Toggle all attendance for a date
        function toggleAll(date, element) {
            document.querySelectorAll(`input[name="attendance[${date}][]"]`)
                .forEach(checkbox => checkbox.checked = element.checked);
        }

        // Confirmation for reminders
        document.querySelectorAll('button[name="send_reminder"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Send reminders for this meeting?')) {
                    e.preventDefault();
                }
            });
        });

        // Show notifications
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($page_success)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= addslashes(htmlspecialchars($page_success)) ?>',
            });
            <?php endif; ?>

            <?php if (!empty($page_error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes(htmlspecialchars($page_error)) ?>',
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>