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

// Check if spreadsheet ID is set
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: teacher_page.php");
    exit;
}

$spreadsheet_id = $_GET['id'];
$teacher_id = $_SESSION["id"];

// Verify that the spreadsheet belongs to a folder owned by the current teacher and get folder/spreadsheet info
$sql = "SELECT s.id, s.name AS spreadsheet_name, s.folder_id, f.folder_name 
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
    $spreadsheet_name = $spreadsheet_data["spreadsheet_name"];
    $folder_id = $spreadsheet_data["folder_id"];
    $folder_name = $spreadsheet_data["folder_name"];
}

// Get percentages from the database
$sql = "SELECT written_percentage, performance_percentage, exam_percentage 
        FROM spreadsheet_settings 
        WHERE spreadsheet_id = :spreadsheet_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    // Settings exist, load them
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $written_percentage = $settings["written_percentage"];
    $performance_percentage = $settings["performance_percentage"];
    $exam_percentage = $settings["exam_percentage"];
} else {
    // Default values if not found in database
    $written_percentage = 40;
    $performance_percentage = 40;
    $exam_percentage = 20;
}

// Function to calculate transmuted grade based on the provided scale
function calculateTransmutedGrade($rawGrade) {
    if ($rawGrade == 100) return 100;
    else if ($rawGrade >= 98.40 && $rawGrade <= 99.99) return 99;
    else if ($rawGrade >= 96.80 && $rawGrade <= 98.39) return 98;
    else if ($rawGrade >= 95.20 && $rawGrade <= 96.79) return 97;
    else if ($rawGrade >= 93.60 && $rawGrade <= 95.19) return 96;
    else if ($rawGrade >= 92.00 && $rawGrade <= 93.59) return 95;
    else if ($rawGrade >= 90.40 && $rawGrade <= 91.99) return 94;
    else if ($rawGrade >= 88.80 && $rawGrade <= 90.39) return 93;
    else if ($rawGrade >= 87.20 && $rawGrade <= 88.79) return 92;
    else if ($rawGrade >= 85.60 && $rawGrade <= 87.19) return 91;
    else if ($rawGrade >= 84.00 && $rawGrade <= 85.59) return 90;
    else if ($rawGrade >= 82.40 && $rawGrade <= 83.99) return 89;
    else if ($rawGrade >= 80.80 && $rawGrade <= 82.39) return 88;
    else if ($rawGrade >= 79.20 && $rawGrade <= 80.79) return 87;
    else if ($rawGrade >= 77.60 && $rawGrade <= 79.19) return 86;
    else if ($rawGrade >= 76.00 && $rawGrade <= 77.59) return 85;
    else if ($rawGrade >= 74.40 && $rawGrade <= 75.99) return 84;
    else if ($rawGrade >= 72.80 && $rawGrade <= 74.39) return 83;
    else if ($rawGrade >= 71.20 && $rawGrade <= 72.79) return 82;
    else if ($rawGrade >= 69.60 && $rawGrade <= 71.19) return 81;
    else if ($rawGrade >= 68.00 && $rawGrade <= 69.59) return 80;
    else if ($rawGrade >= 66.40 && $rawGrade <= 67.99) return 79;
    else if ($rawGrade >= 64.80 && $rawGrade <= 66.39) return 78;
    else if ($rawGrade >= 63.20 && $rawGrade <= 64.79) return 77;
    else if ($rawGrade >= 61.60 && $rawGrade <= 63.19) return 76;
    else if ($rawGrade >= 60.00 && $rawGrade <= 61.59) return 75;
    else if ($rawGrade >= 56.00 && $rawGrade <= 59.99) return 74;
    else if ($rawGrade >= 52.00 && $rawGrade <= 55.99) return 73;
    else if ($rawGrade >= 48.00 && $rawGrade <= 51.99) return 72;
    else if ($rawGrade >= 44.00 && $rawGrade <= 47.99) return 71;
    else if ($rawGrade >= 40.00 && $rawGrade <= 43.99) return 70;
    else if ($rawGrade >= 36.00 && $rawGrade <= 39.99) return 69;
    else if ($rawGrade >= 32.00 && $rawGrade <= 35.99) return 68; 
    else if ($rawGrade >= 28.00 && $rawGrade <= 31.99) return 67;
    else if ($rawGrade >= 24.00 && $rawGrade <= 27.99) return 66;
    else if ($rawGrade >= 20.00 && $rawGrade <= 23.99) return 65;
    else if ($rawGrade >= 16.00 && $rawGrade <= 19.99) return 64;
    else if ($rawGrade >= 12.00 && $rawGrade <= 15.99) return 63;
    else if ($rawGrade >= 8.00 && $rawGrade <= 11.99) return 62;
    else if ($rawGrade >= 4.00 && $rawGrade <= 7.99) return 61;
    else return 60; // 0-3.99
}

// Get students
$students = array();
$sql = "SELECT * FROM students WHERE spreadsheet_id = :spreadsheet_id ORDER BY name";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll();
}

// Get activities
$activities = array();
$sql = "SELECT * FROM activities WHERE spreadsheet_id = :spreadsheet_id ORDER BY category, id";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->execute();
    $activities = $stmt->fetchAll();
}

// Group activities by category
$grouped_activities = [];
foreach ($activities as $activity) {
    $grouped_activities[$activity['category']][] = $activity;
}

// Get scores for all students and activities
$scores = [];
$sql = "SELECT student_id, activity_id, score FROM scores 
        WHERE student_id IN (SELECT id FROM students WHERE spreadsheet_id = :spreadsheet_id)
        AND activity_id IN (SELECT id FROM activities WHERE spreadsheet_id = :spreadsheet_id)";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $scores[$row['student_id']][$row['activity_id']] = $row['score'];
    }
}

// Get saved grades from database (if available)
$grades = [];
$sql = "SELECT student_id, raw_grade, transmuted_grade FROM grades 
        WHERE spreadsheet_id = :spreadsheet_id";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $grades[$row['student_id']] = [
            'raw_grade' => $row['raw_grade'],
            'transmuted_grade' => $row['transmuted_grade']
        ];
    }
}

// Include the TCPDF library
require_once('TCPDF-6.9.3/tcpdf.php');

// Create new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('School Portal');
$pdf->SetAuthor($_SESSION["username"]);
$pdf->SetTitle($spreadsheet_name . ' - Grades');
$pdf->SetSubject('Class Grades');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont('courier');

// Set margins
$pdf->SetMargins(10, 10, 10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 10);

// Set image scale factor
$pdf->setImageScale(1.25);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Set title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, $folder_name . ' - ' . $spreadsheet_name . ' Grades', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Ln(5);

// Generate the PDF content
$html = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:8pt;">
    <thead>
        <tr style="background-color:#f0f0f0; font-weight:bold;">
            <th>LRN</th>
            <th>Name</th>';

// Add categories and activities
$categories = ['Written', 'Performance', 'Exam'];
foreach ($categories as $cat) {
    if (isset($grouped_activities[$cat])) {
        $html .= '<th colspan="' . (count($grouped_activities[$cat]) + 1) . '" style="text-align:center;">' . $cat . '</th>';
    }
}
$html .= '<th>Overall</th><th>Raw Grade</th><th>Final Grade</th></tr><tr style="background-color:#f0f0f0; font-weight:bold;"><th></th><th></th>';

// Add activity names
foreach ($categories as $cat) {
    if (isset($grouped_activities[$cat])) {
        foreach ($grouped_activities[$cat] as $a) {
            $html .= '<th>' . $a['name'] . ' (' . $a['max_score'] . ')</th>';
        }
        $html .= '<th>Total</th>';
    }
}
$html .= '<th>Total</th><th>Raw</th><th>Transmuted</th></tr></thead><tbody>';

// Add student rows
foreach ($students as $s) {
    $html .= '<tr>
        <td>' . htmlspecialchars($s['student_id']) . '</td>
        <td>' . htmlspecialchars($s['name']) . '</td>';
    
    $totals = ['Written' => [0, 0], 'Performance' => [0, 0], 'Exam' => [0, 0]];
    
    // Add scores for each category
    foreach ($categories as $cat) {
        if (isset($grouped_activities[$cat])) {
            foreach ($grouped_activities[$cat] as $a) {
                $score = isset($scores[$s['id']][$a['id']]) ? floatval($scores[$s['id']][$a['id']]) : 0;
                $totals[$cat][0] += $score;
                $totals[$cat][1] += $a['max_score'];
                
                $html .= '<td>' . $score . '</td>';
            }
            $html .= '<td><strong>' . $totals[$cat][0] . '/' . $totals[$cat][1] . '</strong></td>';
        }
    }
    
    // Calculate overall totals
    $overall_earned = array_sum(array_column($totals, 0));
    $overall_max = array_sum(array_column($totals, 1));
    
    // Check if we have saved grades for this student
    if (isset($grades[$s['id']])) {
        $raw_grade = $grades[$s['id']]['raw_grade'];
        $transmuted_grade = $grades[$s['id']]['transmuted_grade'];
    } else {
        // Calculate grades if not found in database
        $written_score = $totals['Written'][1] ? ($totals['Written'][0] / $totals['Written'][1]) * $written_percentage : 0;
        $performance_score = $totals['Performance'][1] ? ($totals['Performance'][0] / $totals['Performance'][1]) * $performance_percentage : 0;
        $exam_score = $totals['Exam'][1] ? ($totals['Exam'][0] / $totals['Exam'][1]) * $exam_percentage : 0;
        $raw_grade = $written_score + $performance_score + $exam_score;
        $transmuted_grade = calculateTransmutedGrade($raw_grade);
    }
    
    // Style the final grade (green for passing, red for failing)
    $grade_style = $transmuted_grade >= 75 ? 'color:#28a745;font-weight:bold;' : 'color:#dc3545;font-weight:bold;';
    
    $html .= '<td><strong>' . $overall_earned . '/' . $overall_max . '</strong></td>
              <td><strong>' . round($raw_grade, 2) . '%</strong></td>
              <td style="' . $grade_style . '">' . round($transmuted_grade, 0) . '</td>
              </tr>';
}

$html .= '</tbody></table>';

// Add grading system explanation
$html .= '<p style="font-size:9pt;"><strong>Grading System</strong></p>';

// Add percentages table
$html .= '<table border="1" cellpadding="3" cellspacing="0" style="width:50%; font-size:9pt;">
    <tr style="background-color:#f0f0f0; font-weight:bold;">
        <th>Category</th>
        <th>Percentage</th>
    </tr>
    <tr>
        <td>Written</td>
        <td>' . $written_percentage . '%</td>
    </tr>
    <tr>
        <td>Performance</td>
        <td>' . $performance_percentage . '%</td>
    </tr>
    <tr>
        <td>Exam</td>
        <td>' . $exam_percentage . '%</td>
    </tr>
</table>';

// Add transmutation scale explanation
$html .= '<p style="font-size:8pt;"><strong>Note:</strong> The Final Grade is calculated by applying the transmutation table to the Raw Grade. Passing grade is 75.</p>';

// Export Info
$html .= '<p style="font-size:8pt;">Generated on: ' . date('Y-m-d H:i:s') . ' by ' . $_SESSION["username"] . '</p>';

// Write the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($folder_name . '_' . $spreadsheet_name . '_grades.pdf', 'D');