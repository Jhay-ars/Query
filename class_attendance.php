<?php
// Initialize the session
session_start();
 
// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Check if the user is a teacher
if($_SESSION["user_type"] !== "teacher") {
    header("location: index.php");
    exit;
}

// Include database connection
require_once "config.php";

$spreadsheet_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;

if(!$spreadsheet_id) {
    header("location: teacher_page.php");
    exit;
}

// Verify that the spreadsheet belongs to this teacher
$teacher_id = $_SESSION["id"];

// Get report type and date parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_week = isset($_GET['week']) ? $_GET['week'] : date('o-\WW'); // ISO-8601 week format
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// New custom date range parameters
$custom_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$custom_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    // Get folder and spreadsheet info
    $sql = "SELECT s.folder_id, s.name AS spreadsheet_name, f.folder_name 
            FROM spreadsheets s 
            JOIN folders f ON s.folder_id = f.id 
            WHERE s.id = :spreadsheet_id AND f.teacher_id = :teacher_id";
            
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if($stmt->rowCount() == 0) {
            // Spreadsheet doesn't belong to this teacher
            header("location: teacher_page.php");
            exit;
        }
        
        $spreadsheet_data = $stmt->fetch();
        $folder_id = $spreadsheet_data["folder_id"];
        $spreadsheet_name = $spreadsheet_data["spreadsheet_name"];
        $folder_name = $spreadsheet_data["folder_name"];
    }

    // Initialize variables for date range and formatted label
    $start_date = null;
    $end_date = null;
    $formatted_label = '';

    // Determine date range based on report type
    if ($report_type === 'daily') {
        $start_date = $selected_date;
        $end_date = $selected_date;
        $formatted_label = date('F j, Y', strtotime($selected_date));
        // For daily, weekday dates is just the selected date for that day
        $weekday_dates = [];
        $weekday_dates[date('l', strtotime($selected_date))] = date('M d', strtotime($selected_date));
    } elseif ($report_type === 'weekly') {
        // Calculate start and end dates of the selected ISO week
        $dto = new DateTime();
        $dto->setISODate(substr($selected_week, 0, 4), substr($selected_week, 6, 2));
        $start_date = $dto->format('Y-m-d');
        $dto->modify('+6 days');
        $end_date = $dto->format('Y-m-d');
        $formatted_label = 'Week ' . substr($selected_week, 6, 2) . ', ' . substr($selected_week, 0, 4);

        // Calculate dates for Monday to Sunday of the selected week
        $weekday_dates = [];
        $week_start = new DateTime($start_date);
        for ($i = 0; $i < 7; $i++) {
            $day_name = $week_start->format('l');
            $day_date = $week_start->format('M d');
            $weekday_dates[$day_name] = $day_date;
            $week_start->modify('+1 day');
        }
    } elseif ($report_type === 'custom') {
        // Custom date range report
        $start_date = $custom_start_date;
        $end_date = $custom_end_date;
        $formatted_label = date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date));
        
        // Calculate dates for each weekday in the range (first occurrence)
        $weekday_dates = [];
        $range_start = new DateTime($start_date);
        $range_end = new DateTime($end_date);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($range_start, $interval, $range_end->modify('+1 day'));
        $found_days = [];
        foreach ($period as $date) {
            $day_name = $date->format('l');
            if (!isset($found_days[$day_name])) {
                $weekday_dates[$day_name] = $date->format('M d');
                $found_days[$day_name] = true;
            }
            if (count($found_days) == 7) {
                break;
            }
        }
    } else {
        // Monthly report (default)
        $start_date = $selected_month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        $formatted_label = date('F Y', strtotime($start_date));

        // Calculate dates for each weekday in the month (first occurrence)
        $weekday_dates = [];
        $month_start = new DateTime($start_date);
        $month_end = new DateTime($end_date);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($month_start, $interval, $month_end->modify('+1 day'));
        $found_days = [];
        foreach ($period as $date) {
            $day_name = $date->format('l');
            if (!isset($found_days[$day_name])) {
                $weekday_dates[$day_name] = $date->format('M d');
                $found_days[$day_name] = true;
            }
            if (count($found_days) == 7) {
                break;
            }
        }
    }

    // Get students
    $students = [];
    $sql = "SELECT * FROM students WHERE spreadsheet_id = :spreadsheet_id ORDER BY name";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Initialize tracking arrays
    $attendance_by_date = [];
    $student_attendance = [];
    $dates_in_range = [];

    // Initialize student attendance stats
    foreach ($students as $student) {
        $student_id = $student['id'];
        $student_attendance[$student_id] = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'total' => 0,
            'rate' => 0
        ];
    }

    // Get all attendance records for the date range
    $sql = "SELECT a.*, s.name as student_name, s.student_id as student_number 
            FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.spreadsheet_id = :spreadsheet_id 
            AND a.date BETWEEN :start_date AND :end_date
            ORDER BY a.date, s.name";
            
    $attendance_records = [];
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->bindParam(":start_date", $start_date, PDO::PARAM_STR);
        $stmt->bindParam(":end_date", $end_date, PDO::PARAM_STR);
        $stmt->execute();
        $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Collect unique dates in the range
    $sql = "SELECT DISTINCT date FROM attendance 
            WHERE student_id IN (SELECT id FROM students WHERE spreadsheet_id = :spreadsheet_id)
            AND date BETWEEN :start_date AND :end_date
            ORDER BY date";
            
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->bindParam(":start_date", $start_date, PDO::PARAM_STR);
        $stmt->bindParam(":end_date", $end_date, PDO::PARAM_STR);
        $stmt->execute();
        
        while($row = $stmt->fetch()) {
            $dates_in_range[] = $row['date'];
            $attendance_by_date[$row['date']] = [];
        }
    }

    // Organize attendance records by student and date
    foreach($attendance_records as $row) {
        $student_id = $row['student_id'];
        $date = $row['date'];
        $status = $row['status'];
        
        // Add to student's statistics
        $student_attendance[$student_id][$status]++;
        $student_attendance[$student_id]['total']++;
        
        // Store attendance record by date
        $attendance_by_date[$date][$student_id] = [
            'status' => $status,
            'notes' => $row['notes'] ?? ''
        ];
    }

    // Calculate attendance rates for each student
    foreach ($student_attendance as $student_id => &$stats) {
        if ($stats['total'] > 0) {
            $stats['rate'] = round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1);
        }
    }

    // Calculate class statistics
    $class_stats = [
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'excused' => 0,
        'total' => 0,
        'average_rate' => 0
    ];

    foreach ($student_attendance as $stats) {
        $class_stats['present'] += $stats['present'];
        $class_stats['absent'] += $stats['absent'];
        $class_stats['late'] += $stats['late'];
        $class_stats['excused'] += $stats['excused'];
        $class_stats['total'] += $stats['total'];
    }

    // Calculate class average attendance rate
    if ($class_stats['total'] > 0) {
        $class_stats['average_rate'] = round((($class_stats['present'] + $class_stats['late']) / $class_stats['total']) * 100, 1);
    }

    // Check for problematic patterns
    $class_insights = [];

    // Add insights based on class statistics
    if ($class_stats['average_rate'] < 80) {
        $class_insights[] = "Class average attendance is below 80%. Consider investigating factors affecting attendance.";
    }

    if ($class_stats['late'] > count($students) * count($dates_in_range) * 0.1) {
        $class_insights[] = "There's a high rate of tardiness in the class. Consider addressing punctuality issues.";
    }

    // Find days with unusually high absences (>25% of class)
    $problem_dates = [];
    foreach ($attendance_by_date as $date => $records) {
        $absent_count = 0;
        foreach ($students as $student) {
            $student_id = $student['id'];
            if (isset($records[$student_id]) && $records[$student_id]['status'] == 'absent') {
                $absent_count++;
            }
        }
        
        if ($absent_count > 0 && count($students) > 0 && $absent_count > count($students) * 0.25) {
            $problem_dates[$date] = round(($absent_count / count($students)) * 100);
        }
    }

    if (!empty($problem_dates)) {
        $dates_list = [];
        foreach ($problem_dates as $date => $percentage) {
            $dates_list[] = date('M d, Y', strtotime($date)) . " ({$percentage}%)";
        }
        $class_insights[] = "Days with unusually high absences: " . implode(", ", $dates_list);
    }

    // Get available months for filtering
    $available_months = [];
    $sql = "SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') as month 
            FROM attendance 
            WHERE student_id IN (SELECT id FROM students WHERE spreadsheet_id = :spreadsheet_id)
            ORDER BY month DESC";
            
    if($stmt = $conn->prepare($sql)) {
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

    // Get attendance by weekday for breakdown analysis
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
            WHERE student_id IN (SELECT id FROM students WHERE spreadsheet_id = :spreadsheet_id)
            AND date BETWEEN :start_date AND :end_date
            GROUP BY DAYNAME(date), status
            ORDER BY FIELD(DAYNAME(date), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
            
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->bindParam(":start_date", $start_date, PDO::PARAM_STR);
        $stmt->bindParam(":end_date", $end_date, PDO::PARAM_STR);
        $stmt->execute();
        
        while($row = $stmt->fetch()) {
            if (isset($weekday_stats[$row['weekday']])) {
                $weekday_stats[$row['weekday']][$row['status']] = $row['count'];
                $weekday_stats[$row['weekday']]['total'] += $row['count'];
            }
        }
    }
    // Define $dates_in_month as dates in $dates_in_range filtered by selected month
    $dates_in_month = $dates_in_range;
    if ($report_type === 'monthly') {
        $dates_in_month = array_filter($dates_in_range, function($date) use ($selected_month) {
            return strpos($date, $selected_month) === 0;
        });
    }
} catch (PDOException $e) {
    // Error handling
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Attendance - <?= htmlspecialchars($spreadsheet_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-fluid {
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
        .insights-box {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .attendance-date {
            white-space: nowrap;
        }
        .calendar-day {
            text-align: center;
            border: 1px solid #dee2e6;
            padding: 10px;
            position: relative;
            min-height: 110px;
        }
        .calendar-day.has-data {
            background-color: #f8f9fa;
        }
        .calendar-date {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .calendar-stats {
            font-size: 0.8rem;
        }
        .table-fixed {
            table-layout: fixed;
        }
        .header-row {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 1;
        }
        .student-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .attendance-rate {
            font-weight: bold;
        }
        .rate-high {
            color: #198754;
        }
        .rate-medium {
            color: #ffc107;
        }
        .rate-low {
            color: #dc3545;
        }
        .date-filter-form {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 0;
        }
        .date-range-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        /* Updated styles for form elements alignment */
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
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="spreadsheet.php?id=<?= $spreadsheet_id ?>" class="btn btn-secondary mb-3">
            <i class="bi bi-arrow-left"></i> Back to Spreadsheet
        </a>
        <h2>Class Attendance Overview - <?= htmlspecialchars($spreadsheet_name) ?></h2>
        <a href="manage_attendance.php?spreadsheet_id=<?= $spreadsheet_id ?>" class="btn btn-primary">
            <i class="bi bi-calendar-check"></i> Manage Attendance
        </a>

    </div>

    <!-- Report Type and Date Filter -->
    <div class="date-filter-form">
        <form method="get" id="reportForm">
            <input type="hidden" name="id" value="<?= $spreadsheet_id ?>">
            
            <div class="filter-row mb-3">
                <div class="filter-item">
                    <label for="report_type" class="form-label">Report Type:</label>
                    <select name="report_type" id="report_type" class="form-select" onchange="onReportTypeChange(this.value)">
                        <option value="daily" <?= $report_type === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= $report_type === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="custom" <?= $report_type === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    </select>
                </div>
                
                <div class="filter-item" id="daily_input" style="display: none;">
                    <label for="date" class="form-label">Select Date:</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?= htmlspecialchars($selected_date) ?>">
                </div>

                <div class="filter-item" id="weekly_input" style="display: none;">
                    <label for="week" class="form-label">Select Week:</label>
                    <select name="week" id="week" class="form-select">
                        <?php 
                        // Generate available weeks for the dropdown
                        $currentDate = new DateTime();
                        for ($i = 0; $i < 12; $i++) {
                            $weekYear = $currentDate->format('o'); // ISO year
                            $weekNum = $currentDate->format('W'); // ISO week number
                            $weekValue = $weekYear . '-W' . $weekNum;
                            $weekLabel = 'Week ' . $weekNum . ', ' . $weekYear;
                            $selected = $weekValue == $selected_week ? 'selected' : '';
                            echo "<option value=\"$weekValue\" $selected>$weekLabel</option>";
                            $currentDate->modify('-1 week');
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-item" id="monthly_input" style="display: none;">
                    <label for="month" class="form-label">Select Month:</label>
                    <select name="month" id="month" class="form-select">
                        <?php foreach ($available_months as $m): ?>
                            <?php 
                                $m_formatted = date('F Y', strtotime($m . '-01'));
                                $selected = $m == $selected_month ? 'selected' : '';
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
                <a href="export_attendance_pdf.php?id=<?= $spreadsheet_id ?>&report_type=<?= $report_type ?>&date=<?= $selected_date ?>&week=<?= $selected_week ?>&month=<?= $selected_month ?>" class="btn btn-primary">
    <i class="bi bi-file-earmark-pdf"></i> Export to PDF
</a>
                <button type="submit" class="btn btn-primary filter-btn">
                    <i class="bi bi-filter"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Class Summary Stats -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Class Summary - <?= $formatted_label ?></h4>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Present</h5>
                    <h2 class="card-text"><?= $class_stats['present'] ?></h2>
                    <p>Total present days across all students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Absent</h5>
                    <h2 class="card-text"><?= $class_stats['absent'] ?></h2>
                    <p>Total absent days across all students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Late</h5>
                    <h2 class="card-text"><?= $class_stats['late'] ?></h2>
                    <p>Total late days across all students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100 bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Class Attendance Rate</h5>
                    <h2 class="card-text"><?= $class_stats['average_rate'] ?>%</h2>
                    <p>Average attendance rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights and Charts -->
    <div class="row mb-4">
        <div class="col-md-7">
            <h4 class="mb-3">Class Attendance Analysis</h4>
            <div class="breakdown-section">
                <!-- Overall attendance rate visualization -->
                <h5>Overall Class Attendance Rate: <?= $class_stats['average_rate'] ?>%</h5>
                <div class="progress mb-4" style="height: 25px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $class_stats['average_rate'] ?>%"
                         aria-valuenow="<?= $class_stats['average_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                        <?= $class_stats['average_rate'] ?>%    
                    </div>
                </div>

                <?php if (!empty($class_insights)): ?>
                    <div class="insights-box mb-4">
                        <h5><i class="bi bi-lightbulb"></i> Insights</h5>
                        <ul class="mb-0">
                            <?php foreach ($class_insights as $insight): ?>
                                <li><?= $insight ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

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
                                    $day_date = $weekday_dates[$day] ?? '';
                                ?>
                                <tr>
                                    <td><strong><?= $day ?> <?= $day_date ? '(' . $day_date . ')' : '' ?></strong></td>
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

                <!-- Charts -->
                <div class="chart-container mt-4">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <!-- Student attendance rates -->
            <h4 class="mb-3">Student Attendance Rates</h4>
            <div class="table-responsive">
                <table class="table table-hover attendance-table">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Rate</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center">No students found</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($students as $student): ?>
                            <?php 
                                $student_id = $student['id'];
                                $stats = $student_attendance[$student_id] ?? [
                                    'present' => 0,
                                    'absent' => 0,
                                    'late' => 0,
                                    'excused' => 0,
                                    'total' => 0,
                                    'rate' => 0
                                ];
                                
                                $rate_class = 'rate-high';
                                if ($stats['rate'] < 90 && $stats['rate'] >= 80) {
                                    $rate_class = 'rate-medium';
                                } else if ($stats['rate'] < 80) {
                                    $rate_class = 'rate-low';
                                }
                            ?>
                            <tr>
                                <td class="student-name"><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= $stats['present'] ?></td>
                                <td><?= $stats['absent'] ?></td>
                                <td><?= $stats['late'] ?></td>
                                <td class="attendance-rate <?= $rate_class ?>"><?= $stats['rate'] ?>%</td>
                                <td>
                                    <a href="student_attendance_teacher.php?student_id=<?= $student['id'] ?>&spreadsheet_id=<?= $spreadsheet_id ?>"
                                    class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Calendar View -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Attendance Calendar - <?= $formatted_label ?></h4>
            <?php if (empty($dates_in_month)): ?>
                <div class="alert alert-info">No attendance records found for this period.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Excused</th>
                                <th>Attendance Rate</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dates_in_month as $date): ?>
                                <?php
                                    $day_stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];
                                    $date_records = $attendance_by_date[$date] ?? [];
                                    
                                    foreach ($students as $student) {
                                        $student_id = $student['id'];
                                        if (isset($date_records[$student_id])) {
                                            $status = $date_records[$student_id]['status'];
                                            $day_stats[$status]++;
                                            $day_stats['total']++;
                                        }
                                    }
                                    
                                    $day_rate = $day_stats['total'] > 0 ? 
                                        round((($day_stats['present'] + $day_stats['late']) / $day_stats['total']) * 100, 1) : 0;
                                    
                                    $formatted_date = date('D, M d', strtotime($date));
                                ?>
                                <tr>
                                    <td><strong><?= $formatted_date ?></strong></td>
                                    <td><?= $day_stats['present'] ?></td>
                                    <td><?= $day_stats['absent'] ?></td>
                                    <td><?= $day_stats['late'] ?></td>
                                    <td><?= $day_stats['excused'] ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?= $day_rate ?>%"
                                                 aria-valuenow="<?= $day_rate ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?= $day_rate ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="manage_attendance.php?spreadsheet_id=<?= $spreadsheet_id ?>&date=<?= $date ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Function to toggle visibility of date filter inputs based on report type
function onReportTypeChange(value) {
    document.getElementById('daily_input').style.display = 'none';
    document.getElementById('weekly_input').style.display = 'none';
    document.getElementById('monthly_input').style.display = 'none';
    document.getElementById('custom_input').style.display = 'none';

    if (value === 'daily') {
        document.getElementById('daily_input').style.display = 'block';
    } else if (value === 'weekly') {
        document.getElementById('weekly_input').style.display = 'block';
    } else if (value === 'custom') {
        document.getElementById('custom_input').style.display = 'flex';
    } else {
        document.getElementById('monthly_input').style.display = 'block';
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
            label: 'Class Attendance Summary',
            data: [
                <?= $class_stats['present'] ?>,
                <?= $class_stats['absent'] ?>,
                <?= $class_stats['late'] ?>,
                <?= $class_stats['excused'] ?>
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