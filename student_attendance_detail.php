<?php
// Initialize the session
session_start();
 
// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Check if the user is a student
if($_SESSION["user_type"] !== "student") {
    header("location: index.php");
    exit;
}

// Include database connection
require_once "config.php";

// Get parameters
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$spreadsheet_id = isset($_GET['spreadsheet_id']) ? filter_var($_GET['spreadsheet_id'], FILTER_VALIDATE_INT) : 0;

if(!$student_id || !$spreadsheet_id) {
    $_SESSION['error_message'] = "Missing required parameters.";
    header("location: student_page.php");
    exit;
}

// Verify the student is enrolled in this spreadsheet
$sql = "SELECT s.id, s.name AS student_name, s.student_id AS student_number, 
        sp.name AS spreadsheet_name, f.folder_name, u.username AS teacher_name
        FROM students s
        JOIN spreadsheets sp ON s.spreadsheet_id = sp.id
        JOIN folders f ON sp.folder_id = f.id
        JOIN users u ON f.teacher_id = u.id
        WHERE s.student_id = :student_id AND sp.id = :spreadsheet_id";

$student_enrolled = false;
$student_name = '';
$student_number = '';
$spreadsheet_name = '';
$folder_name = '';
$teacher_name = '';

if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":student_id", $student_id, PDO::PARAM_STR);
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if($row = $stmt->fetch()) {
        $student_enrolled = true;
        $student_db_id = $row['id']; // This is the database ID, not the student_id/LRN
        $student_name = $row['student_name'];
        $student_number = $row['student_number'];
        $spreadsheet_name = $row['spreadsheet_name'];
        $folder_name = $row['folder_name'];
        $teacher_name = $row['teacher_name'];
    }
}

if(!$student_enrolled) {
    $_SESSION['error_message'] = "You are not enrolled in this course.";
    header("location: student_page.php");
    exit;
}

// Filter parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Custom date range parameters
$custom_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$custom_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Initialize date range based on report type
$start_date = null;
$end_date = null;
$formatted_label = '';

if ($report_type === 'custom') {
    $start_date = $custom_start_date;
    $end_date = $custom_end_date;
    $formatted_label = date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date));
} else {
    // Monthly report (default)
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    $formatted_label = date('F Y', strtotime($start_date));
}

// Build the query condition
$where_condition = "student_id = :student_id AND spreadsheet_id = :spreadsheet_id";
$params = [
    ':student_id' => $student_db_id,
    ':spreadsheet_id' => $spreadsheet_id
];

if ($report_type === 'monthly') {
    $where_condition .= " AND DATE_FORMAT(date, '%Y-%m') = :month";
    $params[':month'] = $month;
} else if ($report_type === 'custom') {
    $where_condition .= " AND date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

if (!empty($status_filter)) {
    $where_condition .= " AND status = :status";
    $params[':status'] = $status_filter;
}

// Get attendance records
$attendance_records = [];
$sql = "SELECT * FROM attendance WHERE $where_condition ORDER BY date DESC";

if($stmt = $conn->prepare($sql)) {
    foreach($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->execute();
    $attendance_records = $stmt->fetchAll();
}

// Calculate statistics
$stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0,
    'total' => 0
];

$sql = "SELECT status, COUNT(*) as count FROM attendance WHERE $where_condition GROUP BY status";
        
if($stmt = $conn->prepare($sql)) {
    foreach($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->execute();
    
    while($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }
}

// Calculate attendance rate
$attendance_rate = $stats['total'] > 0 ? 
    round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1) : 0;

// Get available months for filtering
$available_months = [];
$sql = "SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') as month 
        FROM attendance 
        WHERE student_id = :student_id AND spreadsheet_id = :spreadsheet_id
        ORDER BY month DESC";
        
if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":student_id", $student_db_id, PDO::PARAM_INT);
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->execute();
    
    while($row = $stmt->fetch()) {
        $available_months[] = $row['month'];
    }
}

// Add current month if not in results
if (!in_array(date('Y-m'), $available_months)) {
    array_unshift($available_months, date('Y-m'));
}

// Get attendance by weekday
$weekday_stats = [
    'Monday' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0],
    'Tuesday' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0],
    'Wednesday' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0],
    'Thursday' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0],
    'Friday' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0],
    'Saturday' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0],
    'Sunday' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0]
];

$sql = "SELECT DAYNAME(date) as weekday, status, COUNT(*) as count 
        FROM attendance 
        WHERE $where_condition
        GROUP BY DAYNAME(date), status
        ORDER BY FIELD(DAYNAME(date), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
        
if($stmt = $conn->prepare($sql)) {
    foreach($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->execute();
    
    while($row = $stmt->fetch()) {
        if (isset($weekday_stats[$row['weekday']])) {
            $weekday_stats[$row['weekday']][$row['status']] = $row['count'];
            $weekday_stats[$row['weekday']]['total'] += $row['count'];
        }
    }
}

// Get consecutive absence data
$consecutive_absences = [];
$current_streak = 0;
$max_streak = 0;
$last_date = null;

$sql = "SELECT date, status FROM attendance 
        WHERE student_id = :student_id AND spreadsheet_id = :spreadsheet_id
        AND status = 'absent'
        ORDER BY date";
        
if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":student_id", $student_db_id, PDO::PARAM_INT);
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->execute();
    
    while($row = $stmt->fetch()) {
        $current_date = new DateTime($row['date']);
        
        if ($last_date) {
            $diff = $current_date->diff($last_date)->days;
            
            if ($diff == 1) {
                $current_streak++;
            } else {
                if ($current_streak > 0) {
                    $consecutive_absences[] = $current_streak;
                }
                $current_streak = 1;
            }
        } else {
            $current_streak = 1;
        }
        
        $max_streak = max($max_streak, $current_streak);
        $last_date = $current_date;
    }
}

if ($current_streak > 0) {
    $consecutive_absences[] = $current_streak;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .container {
            padding: 0 20px;
        }
        .status-present {
            background-color: rgba(25, 135, 84, 0.15);
        }
        .status-absent {
            background-color: rgba(220, 53, 69, 0.15);
        }
        .status-late {
            background-color: rgba(255, 193, 7, 0.15);
        }
        .status-excused {
            background-color: rgba(13, 110, 253, 0.15);
        }
        .stats-card {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .attendance-table {
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .attendance-date {
            white-space: nowrap;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .present-indicator { background-color: #198754; }
        .absent-indicator { background-color: #dc3545; }
        .late-indicator { background-color: #ffc107; }
        .excused-indicator { background-color: #0d6efd; }
        .breakdown-card {
            margin-bottom: 20px;
        }
        .attendance-progress {
            height: 20px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .breakdown-label {
            font-weight: bold;
            font-size: 0.9rem;
        }
        .breakdown-section {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .chart-container {
            height: 250px;
            margin-bottom: 20px;
        }
        .course-info {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .date-filter-form {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filter-item {
            flex: 1;
            min-width: 200px;
        }
        .filter-btn {
            align-self: flex-end;
            height: 38px;
        }
        .date-range-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="student_page.php" class="btn btn-secondary mb-3">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <h2>Attendance Records</h2>
        <div></div>
    </div>
    
    <!-- Course Info -->
    <div class="course-info mb-4">
        <div class="row">
            <div class="col-md-6">
                <h5><?= htmlspecialchars($spreadsheet_name) ?></h5>
                <p class="mb-1"><strong>Teacher:</strong> <?= htmlspecialchars($teacher_name) ?></p>
                <p class="mb-0"><strong>Folder:</strong> <?= htmlspecialchars($folder_name) ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Student:</strong> <?= htmlspecialchars($student_name) ?></p>
                <p class="mb-0"><strong>ID:</strong> <?= htmlspecialchars($student_number) ?></p>
            </div>
        </div>
    </div>

    <!-- Filter options -->
    <div class="date-filter-form">
        <form method="get" id="reportForm">
            <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
            <input type="hidden" name="spreadsheet_id" value="<?= $spreadsheet_id ?>">
            
            <div class="filter-row mb-3">
                <div class="filter-item">
                    <label for="report_type" class="form-label">Report Type:</label>
                    <select name="report_type" id="report_type" class="form-select" onchange="onReportTypeChange(this.value)">
                        <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="custom" <?= $report_type === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    </select>
                </div>
                
                <div class="filter-item" id="monthly_input" style="display: none;">
                    <label for="month" class="form-label">Select Month:</label>
                    <select name="month" id="month" class="form-select">
                        <?php foreach ($available_months as $m): ?>
                            <?php 
                                $m_formatted = date('F Y', strtotime($m . '-01'));
                                $selected = $m == $month ? 'selected' : '';
                            ?>
                            <option value="<?= $m ?>" <?= $selected ?>><?= $m_formatted ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item date-range-inputs" id="custom_input" style="display: none;">
                    <div class="flex-grow-1">
                        <label for="start_date" class="form-label">From:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($custom_start_date) ?>">
                    </div>
                    <div class="flex-grow-1">
                        <label for="end_date" class="form-label">To:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($custom_end_date) ?>">
                    </div>
                </div>
                
                <div class="filter-item">
                    <label for="status" class="form-label">Status:</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="present" <?= $status_filter == 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $status_filter == 'absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="late" <?= $status_filter == 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="excused" <?= $status_filter == 'excused' ? 'selected' : '' ?>>Excused</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary filter-btn">
                    <i class="bi bi-filter"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Present</h5>
                    <h2 class="card-text"><?= $stats['present'] ?></h2>
                    <p>Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Absent</h5>
                    <h2 class="card-text"><?= $stats['absent'] ?></h2>
                    <p>Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Late</h5>
                    <h2 class="card-text"><?= $stats['late'] ?></h2>
                    <p>Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Excused</h5>
                    <h2 class="card-text"><?= $stats['excused'] ?></h2>
                    <p>Days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Attendance Breakdown -->
    <div class="row">
        <div class="col-md-8">
            <h4 class="mb-3">Attendance Breakdown - <?= $formatted_label ?></h4>
            <div class="breakdown-section">
                <!-- Overall attendance rate visualization -->
                <h5>Overall Attendance Rate: <?= $attendance_rate ?>%</h5>
                <div class="progress attendance-progress mb-4">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $attendance_rate ?>%"
                         aria-valuenow="<?= $attendance_rate ?>" aria-valuemin="0" aria-valuemax="100">
                         <?= $attendance_rate ?>%
                    </div>
                </div>

                <!-- Weekday analysis -->
                <h5 class="mt-4 mb-3">Attendance by Day of Week</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Excused</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weekday_stats as $day => $day_stats): ?>
                                <?php 
                                    $day_attendance_rate = $day_stats['total'] > 0 ? 
                                        round((($day_stats['present'] + $day_stats['late']) / $day_stats['total']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><strong><?= $day ?></strong></td>
                                    <td><?= $day_stats['present'] ?></td>
                                    <td><?= $day_stats['absent'] ?></td>
                                    <td><?= $day_stats['late'] ?></td>
                                    <td><?= $day_stats['excused'] ?></td>
                                    <td>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?= $day_attendance_rate ?>%"
                                                 aria-valuenow="<?= $day_attendance_rate ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?= $day_attendance_rate ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Attendance patterns -->
                <h5 class="mt-4 mb-3">Attendance Patterns</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Consecutive Absences</h6>
                                <h3 class="card-text"><?= $max_streak ?></h3>
                                <p class="text-muted">Longest streak of absences</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Attendance Rate</h6>
                                <h3 class="card-text <?= $attendance_rate >= 85 ? 'text-success' : ($attendance_rate >= 70 ? 'text-warning' : 'text-danger') ?>">
                                    <?= $attendance_rate ?>%
                                </h3>
                                <p class="text-muted">For <?= $formatted_label ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Chart -->
                <div class="chart-container mt-4">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Attendance Records -->
            <h4 class="mb-3">Attendance Records</h4>
            <div class="table-responsive">
                <table class="table table-hover attendance-table">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendance_records) == 0): ?>
                            <tr>
                                <td colspan="3" class="text-center">No records found</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($attendance_records as $record): ?>
                            <tr class="status-<?= $record['status'] ?>">
                                <td class="attendance-date">
                                    <?= date('M d, Y (D)', strtotime($record['date'])) ?>
                                </td>
                                <td>
                                    <span class="status-indicator <?= $record['status'] ?>-indicator"></span>
                                    <?= ucfirst($record['status']) ?>
                                </td>
                                <td><?= htmlspecialchars($record['notes'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Function to toggle visibility of date filter inputs based on report type
function onReportTypeChange(value) {
    document.getElementById('monthly_input').style.display = 'none';
    document.getElementById('custom_input').style.display = 'none';

    if (value === 'monthly') {
        document.getElementById('monthly_input').style.display = 'block';
    } else if (value === 'custom') {
        document.getElementById('custom_input').style.display = 'flex';
    }
}

// Initialize the form inputs visibility on page load
window.onload = function() {
    onReportTypeChange('<?= $report_type ?>');

    // Validate date range for custom date filter
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    // Ensure end date is not before start date
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value && this.value && this.value < startDateInput.value) {
            alert('End date cannot be before start date');
            this.value = startDateInput.value;
        }
    });
    
    // Ensure start date is not after end date
    startDateInput.addEventListener('change', function() {
        if (endDateInput.value && this.value && this.value > endDateInput.value) {
            endDateInput.value = this.value;
        }
    });
};

// Prepare data for the attendance chart
const ctx = document.getElementById('attendanceChart').getContext('2d');
const attendanceChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Present', 'Absent', 'Late', 'Excused'],
        datasets: [{
            label: 'Attendance Status',
            data: [
                <?= $stats['present'] ?>,
                <?= $stats['absent'] ?>,
                <?= $stats['late'] ?>,
                <?= $stats['excused'] ?>
            ],
            backgroundColor: [
                'rgba(25, 135, 84, 0.7)',  // Present - green
                'rgba(220, 53, 69, 0.7)',  // Absent - red
                'rgba(255, 193, 7, 0.7)',  // Late - yellow
                'rgba(13, 110, 253, 0.7)'  // Excused - blue
            ],
            borderColor: [
                'rgba(25, 135, 84, 1)',
                'rgba(220, 53, 69, 1)',
                'rgba(255, 193, 7, 1)',
                'rgba(13, 110, 253, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>
</body>
</html>