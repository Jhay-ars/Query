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

// Add custom date range parameters
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
    } elseif ($report_type === 'weekly') {
        // Calculate start and end dates of the selected ISO week
        $dto = new DateTime();
        $dto->setISODate(substr($selected_week, 0, 4), substr($selected_week, 6, 2));
        $start_date = $dto->format('Y-m-d');
        $dto->modify('+6 days');
        $end_date = $dto->format('Y-m-d');
        $formatted_label = 'Week ' . substr($selected_week, 6, 2) . ', ' . substr($selected_week, 0, 4);
    } elseif ($report_type === 'custom') {
        // Custom date range
        $start_date = $custom_start_date;
        $end_date = $custom_end_date;
        $formatted_label = date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date));
    } else {
        // Monthly report (default)
        $start_date = $selected_month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        $formatted_label = date('F Y', strtotime($start_date));
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

    // Get available months for filtering (to be used in the form)
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

    // First display the report on the screen
    $show_screen_view = true;

    // Check if PDF download was requested
    if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
        $show_screen_view = false;
        
        // Include the TCPDF library
        require_once('TCPDF-6.9.3/tcpdf.php');

        // Create new PDF document
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('School Portal');
        $pdf->SetAuthor($_SESSION["username"]);
        $pdf->SetTitle($spreadsheet_name . ' - Attendance Report');
        $pdf->SetSubject('Class Attendance');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont('courier');

        // Set margins
        $pdf->SetMargins(15, 15, 15);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Set image scale factor
        $pdf->setImageScale(1.25);

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Add a page
        $pdf->AddPage();

        // Set title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $folder_name . ' - ' . $spreadsheet_name, 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Attendance Report - ' . $formatted_label, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(5);

        // Class Summary Section
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Class Summary', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // Class Stats Table
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(45, 7, 'Present', 1, 0, 'L', true);
        $pdf->Cell(30, 7, $class_stats['present'], 1, 1, 'C');
        
        $pdf->Cell(45, 7, 'Absent', 1, 0, 'L', true);
        $pdf->Cell(30, 7, $class_stats['absent'], 1, 1, 'C');
        
        $pdf->Cell(45, 7, 'Late', 1, 0, 'L', true);
        $pdf->Cell(30, 7, $class_stats['late'], 1, 1, 'C');
        
        $pdf->Cell(45, 7, 'Excused', 1, 0, 'L', true);
        $pdf->Cell(30, 7, $class_stats['excused'], 1, 1, 'C');
        
        $pdf->Cell(45, 7, 'Class Attendance Rate', 1, 0, 'L', true);
        $pdf->Cell(30, 7, $class_stats['average_rate'] . '%', 1, 1, 'C');
        
        $pdf->Ln(5);

        // Day of Week Analysis
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Attendance by Day of Week', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // Weekday Table Header
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(30, 7, 'Day', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Present', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Absent', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Late', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Excused', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Attendance Rate', 1, 1, 'C', true);

        // Weekday Table Data
        foreach ($weekday_stats as $day => $day_stats) {
            $day_attendance_rate = $day_stats['total'] > 0 ? 
                round((($day_stats['present'] + $day_stats['late']) / $day_stats['total']) * 100, 1) : 0;
                
            $pdf->Cell(30, 7, $day, 1, 0, 'L');
            $pdf->Cell(25, 7, $day_stats['present'], 1, 0, 'C');
            $pdf->Cell(25, 7, $day_stats['absent'], 1, 0, 'C');
            $pdf->Cell(25, 7, $day_stats['late'], 1, 0, 'C');
            $pdf->Cell(25, 7, $day_stats['excused'], 1, 0, 'C');
            $pdf->Cell(30, 7, $day_attendance_rate . '%', 1, 1, 'C');
        }
        
        $pdf->Ln(5);

        // Students Attendance Section
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Student Attendance Summary', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // Student Table Header
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(60, 7, 'Student', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Present', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Absent', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Late', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Excused', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Attendance Rate', 1, 1, 'C', true);

        // Student Table Data
        foreach ($students as $student) {
            $student_id = $student['id'];
            $stats = $student_attendance[$student_id] ?? [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
                'total' => 0,
                'rate' => 0
            ];
            
            $pdf->Cell(60, 7, $student['name'], 1, 0, 'L');
            $pdf->Cell(25, 7, $stats['present'], 1, 0, 'C');
            $pdf->Cell(25, 7, $stats['absent'], 1, 0, 'C');
            $pdf->Cell(25, 7, $stats['late'], 1, 0, 'C');
            $pdf->Cell(25, 7, $stats['excused'], 1, 0, 'C');
            $pdf->Cell(30, 7, $stats['rate'] . '%', 1, 1, 'C');
        }
        
        $pdf->Ln(5);

        // Daily Attendance Record Section, if we have dates
        if (!empty($dates_in_range)) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Daily Attendance Records', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);

            // Daily Attendance Table Header
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(35, 7, 'Date', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Present', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Absent', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Late', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Excused', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Attendance Rate', 1, 1, 'C', true);

            // Daily Attendance Table Data
            foreach ($dates_in_range as $date) {
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
                
                $formatted_date = date('D, M d, Y', strtotime($date));
                
                $pdf->Cell(35, 7, $formatted_date, 1, 0, 'L');
                $pdf->Cell(25, 7, $day_stats['present'], 1, 0, 'C');
                $pdf->Cell(25, 7, $day_stats['absent'], 1, 0, 'C');
                $pdf->Cell(25, 7, $day_stats['late'], 1, 0, 'C');
                $pdf->Cell(25, 7, $day_stats['excused'], 1, 0, 'C');
                $pdf->Cell(30, 7, $day_rate . '%', 1, 1, 'C');
            }
        }

        // Footer with generation info
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 5, 'Report generated on: ' . date('Y-m-d H:i:s') . ' by ' . $_SESSION["username"], 0, 1, 'L');

        // Close and output PDF document
        $report_type_text = ucfirst($report_type);
        $pdf->Output($folder_name . '_' . $spreadsheet_name . '_' . $report_type_text . '_Attendance_Report.pdf', 'D');
        exit; // Stop further execution after PDF is output
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
    <title>Export Attendance Report - <?= htmlspecialchars($spreadsheet_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        .report-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-present { background-color: rgba(25, 135, 84, 0.15); }
        .status-absent { background-color: rgba(220, 53, 69, 0.15); }
        .status-late { background-color: rgba(255, 193, 7, 0.15); }
        .status-excused { background-color: rgba(13, 110, 253, 0.15); }
        .section-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
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
            height: 38px;
        }
        .date-range-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stats-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            min-width: 180px;
            padding: 15px;
            border-radius: 8px;
            color: white;
            text-align: center;
        }
        .stat-card h2 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        .present-card { background-color: #198754; }
        .absent-card { background-color: #dc3545; }
        .late-card { background-color: #ffc107; color: #212529; }
        .rate-card { background-color: #0d6efd; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="class_attendance.php?id=<?= $spreadsheet_id ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Attendance
        </a>
        <h2><?= htmlspecialchars($spreadsheet_name) ?> - Attendance Report</h2>
        <a href="<?= $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?') ?>format=pdf" class="btn btn-primary">
            <i class="bi bi-file-earmark-pdf"></i> Download PDF
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
                
                <button type="submit" class="btn btn-primary filter-btn">
                    <i class="bi bi-filter"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Report Content -->
    <div class="report-card">
        <h3 class="mb-4 text-center">Attendance Report - <?= $formatted_label ?></h3>
        
        <!-- Class Summary Stats -->
        <div class="section-header">
            <i class="bi bi-bar-chart-fill"></i> Class Summary
        </div>
        
        <div class="stats-summary">
            <div class="stat-card present-card">
                <h5>Present</h5>
                <h2><?= $class_stats['present'] ?></h2>
                <p>Total present</p>
            </div>
            <div class="stat-card absent-card">
                <h5>Absent</h5>
                <h2><?= $class_stats['absent'] ?></h2>
                <p>Total absent</p>
            </div>
            <div class="stat-card late-card">
                <h5>Late</h5>
                <h2><?= $class_stats['late'] ?></h2>
                <p>Total late</p>
            </div>
            <div class="stat-card rate-card">
                <h5>Class Attendance Rate</h5>
                <h2><?= $class_stats['average_rate'] ?>%</h2>
                <p>Average rate</p>
            </div>
        </div>
        
        <!-- Day of Week Analysis -->
        <div class="section-header mt-4">
            <i class="bi bi-calendar-week"></i> Attendance by Day of Week
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Day</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Excused</th>
                        <th>Attendance Rate</th>
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
                                <div class="progress" style="height: 20px;">
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
        
        <!-- Student Attendance Summary -->
        <div class="section-header mt-4">
            <i class="bi bi-people-fill"></i> Student Attendance Summary
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Excused</th>
                        <th>Attendance Rate</th>
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
                            
                            $rate_class = '';
                            if ($stats['rate'] >= 90) {
                                $rate_class = 'bg-success';
                            } else if ($stats['rate'] >= 80) {
                                $rate_class = 'bg-warning';
                            } else {
                                $rate_class = 'bg-danger';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($student['name']) ?></td>
                            <td><?= $stats['present'] ?></td>
                            <td><?= $stats['absent'] ?></td>
                            <td><?= $stats['late'] ?></td>
                            <td><?= $stats['excused'] ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?= $rate_class ?>" role="progressbar" 
                                         style="width: <?= $stats['rate'] ?>%"
                                         aria-valuenow="<?= $stats['rate'] ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?= $stats['rate'] ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Daily Attendance Records -->
        <?php if (!empty($dates_in_range)): ?>
            <div class="section-header mt-4">
                <i class="bi bi-calendar-check"></i> Daily Attendance Records
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Excused</th>
                            <th>Attendance Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dates_in_range as $date): ?>
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
                                
                                $formatted_date = date('D, M d, Y', strtotime($date));
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Report footer -->
        <div class="mt-4 text-muted text-center">
            <small>Report generated on: <?= date('Y-m-d H:i:s') ?> by <?= htmlspecialchars($_SESSION["username"]) ?></small>
        </div>
    </div>
    
    <div class="text-center mb-4">
        <a href="<?= $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?') ?>format=pdf" class="btn btn-lg btn-primary">
            <i class="bi bi-file-earmark-pdf"></i> Download as PDF
        </a>
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
</script>
</body>
</html>