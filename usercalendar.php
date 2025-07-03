
<?php
session_start();

// Session validation
if (!isset($_SESSION['user']['id'])) {
    header("Location: /savingssystem/auth/login.php");
    exit;
}

// Database connection
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';

// Initialize variables
$year = date('Y');
$withdrawalDate = new DateTime("$year-12-15");
$meetingDay = 'Tuesday';

// Fetch all active members
try {
    $stmt = $pdo->query("
        SELECT 
            m.member_no,
            m.full_name,
            MIN(s.transaction_date) as start_date
        FROM memberz m
        LEFT JOIN savings s ON m.member_no = s.member_id
        WHERE m.is_active = 1
        GROUP BY m.member_no, m.full_name
    ");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load member data";
    $members = [];
}

// Generate all meeting dates for the year
$startDate = date('Y-01-01');
$endDate = $withdrawalDate->format('Y-m-d');
$meetingDates = [];

$current = new DateTime($startDate);
if ($current->format('l') !== $meetingDay) {
    $current->modify("next $meetingDay");
}

while ($current <= new DateTime($endDate)) {
    $meetingDates[] = [
        'date' => $current->format('Y-m-d'),
        'day' => $current->format('l'),
        'type' => 'meeting',
        'members_present' => []
    ];
    $current->modify("+1 week");
}

// Add withdrawal date
$meetingDates[] = [
    'date' => $withdrawalDate->format('Y-m-d'),
    'day' => $withdrawalDate->format('l'),
    'type' => 'withdrawal',
    'members_present' => []
];

// Assign members to dates
foreach ($members as $member) {
    $memberStartDate = $member['start_date'] ?: date('Y-m-d');
    foreach ($meetingDates as &$meeting) {
        if ($meeting['date'] >= $memberStartDate) {
            $meeting['members_present'][] = [
                'id' => $member['member_no'],
                'name' => $member['full_name'],
                'attended' => false // Default attendance status
            ];
        }
    }
}
unset($meeting);

// Process attendance form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['attendance'] as $date => $memberIds) {
            // Here you would save to database
            // Example: UPDATE attendance_records SET attended=1 WHERE date=? AND member_id IN(...)
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Attendance saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to save attendance: " . $e->getMessage();
    }
}
?>
<?php
// [Keep all your existing PHP logic for sessions/database]
// [Keep the $meetingDates generation code from previous example]
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Savings Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                        <small class="text-muted"><?= $year ?></small>
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
                                Weekly deposits every <strong><?= $meetingDay ?></strong>. 
                                All savings will be withdrawn on 
                                <strong><?= $withdrawalDate->format('F j, Y') ?></strong>.
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
                                                        <?= /* Add checked status if previously saved */ false ? 'checked' : '' ?>
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
                                        <?php if ($event['type'] === 'meeting'): ?>
                                            <i class="fas fa-hand-holding-usd text-primary me-1"></i> Deposit meeting
                                        <?php else: ?>
                                            <i class="fas fa-money-bill-wave text-warning me-1"></i> Withdrawal day
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($event['type'] === 'meeting'): ?>
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-envelope me-1"></i>
                                        </button>
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
    <script>
        // Toggle all attendance for a specific date
        function toggleAll(date, element) {
            const checkboxes = document.querySelectorAll(`input[name="attendance[${date}][]"]`);
            checkboxes.forEach(checkbox => {
                checkbox.checked = element.checked;
            });
        }
    </script>
</body>
</html>