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

// Check if spreadsheet ID is set
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: student_page.php");
    exit;
}

$spreadsheet_id = $_GET['id'];
$student_username = $_SESSION["username"];
$student_lrn = $student_username; // Default to username

// Try to get student_lrn if available
$sql = "SELECT student_lrn FROM users WHERE id = :user_id";
if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":user_id", $_SESSION["id"], PDO::PARAM_INT);
    $stmt->execute();
    if($row = $stmt->fetch()) {
        if(!empty($row['student_lrn'])) {
            $student_lrn = $row['student_lrn'];
        }
    }
}

// Verify that the student is enrolled in this spreadsheet and get folder/spreadsheet info
$sql = "SELECT s.id, s.name AS spreadsheet_name, s.folder_id, f.folder_name, f.teacher_id, 
        u.username AS teacher_name, st.student_id, st.id AS student_record_id, st.name AS student_name
        FROM students st
        JOIN spreadsheets s ON st.spreadsheet_id = s.id
        JOIN folders f ON s.folder_id = f.id
        JOIN users u ON f.teacher_id = u.id
        WHERE s.id = :spreadsheet_id AND st.student_id = :student_id";

if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->bindParam(":student_id", $student_lrn, PDO::PARAM_STR);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        // If no match with student_lrn, try with username as fallback
        if($student_lrn != $student_username) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
            $stmt->bindParam(":student_id", $student_username, PDO::PARAM_STR);
            $stmt->execute();
        }
        
        if($stmt->rowCount() == 0) {
            // Student isn't enrolled in this spreadsheet
            header("location: student_page.php");
            exit;
        }
    }
    
    $student_data = $stmt->fetch();
    $spreadsheet_name = $student_data["spreadsheet_name"];
    $folder_name = $student_data["folder_name"];
    $teacher_name = $student_data["teacher_name"];
    $student_record_id = $student_data["student_record_id"];
    $student_name = $student_data["student_name"];
}

// Get percentages from database instead of session
$written_percentage = 40; // Default value
$performance_percentage = 40; // Default value
$exam_percentage = 20; // Default value

// Load percentages from spreadsheet_settings table
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
}

// Get activities
$activities = array();
$sql = "SELECT * FROM activities WHERE spreadsheet_id = :spreadsheet_id ORDER BY category, id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
$stmt->execute();
$activities = $stmt->fetchAll();

// Group activities by category
$grouped_activities = [];
foreach ($activities as $activity) {
    $grouped_activities[$activity['category']][] = $activity;
}

// Calculate total possible points per category
$total_points = [
    'Written' => 0,
    'Performance' => 0,
    'Exam' => 0
];

foreach ($activities as $activity) {
    $total_points[$activity['category']] += $activity['max_score'];
}

// Get scores for this student
$scores = [];
$sql = "SELECT s.activity_id, s.score, a.max_score, a.category, a.name AS activity_name
        FROM scores s
        JOIN activities a ON s.activity_id = a.id
        WHERE s.student_id = :student_id AND a.spreadsheet_id = :spreadsheet_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(":student_id", $student_record_id, PDO::PARAM_INT);
$stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
$stmt->execute();
while ($row = $stmt->fetch()) {
    $scores[$row['activity_id']] = $row;
}

// Get student's grade from the grades table if it exists
$sql = "SELECT raw_grade, transmuted_grade 
        FROM grades 
        WHERE student_id = :student_id 
        AND spreadsheet_id = :spreadsheet_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(":student_id", $student_record_id, PDO::PARAM_INT);
$stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
$stmt->execute();
$has_grade_record = false;
$db_raw_grade = 0;
$db_transmuted_grade = 0;

if ($stmt->rowCount() > 0) {
    $grade_record = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_grade_record = true;
    $db_raw_grade = $grade_record['raw_grade'];
    $db_transmuted_grade = $grade_record['transmuted_grade'];
}

// Calculate grades
$category_scores = [
    'Written' => 0,
    'Performance' => 0,
    'Exam' => 0
];

$total_obtained = [
    'Written' => 0,
    'Performance' => 0,
    'Exam' => 0
];

foreach ($activities as $activity) {
    $activity_id = $activity['id'];
    $category = $activity['category'];
    $max_score = $activity['max_score'];
    
    if (isset($scores[$activity_id])) {
        $total_obtained[$category] += $scores[$activity_id]['score'];
    }
}

// Calculate percentage scores for each category
$percentage_scores = [
    'Written' => ($total_points['Written'] > 0) ? ($total_obtained['Written'] / $total_points['Written']) * 100 : 0,
    'Performance' => ($total_points['Performance'] > 0) ? ($total_obtained['Performance'] / $total_points['Performance']) * 100 : 0,
    'Exam' => ($total_points['Exam'] > 0) ? ($total_obtained['Exam'] / $total_points['Exam']) * 100 : 0
];

// Calculate weighted scores
$weighted_scores = [
    'Written' => $percentage_scores['Written'] * ($written_percentage / 100),
    'Performance' => $percentage_scores['Performance'] * ($performance_percentage / 100),
    'Exam' => $percentage_scores['Exam'] * ($exam_percentage / 100)
];

// Calculate final grade
$final_grade = $weighted_scores['Written'] + $weighted_scores['Performance'] + $weighted_scores['Exam'];

// Calculate transmuted grade
$transmuted_grade = calculateTransmutedGrade($final_grade);

// If we have a grade record, use the values from the database
if ($has_grade_record) {
    $final_grade = $db_raw_grade;
    $transmuted_grade = $db_transmuted_grade;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Grades - <?= htmlspecialchars($spreadsheet_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a86ff;
            --secondary-color: #8338ec;
            --success-color: #06d6a0;
            --warning-color: #ffbe0b;
            --danger-color: #ef476f;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body { 
            padding-top: 56px; /* Added to accommodate fixed navbar */
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .container {
            padding: 20px;
        }
        
        .page-content {
            margin-top: 25px; /* Space between navbar and content */
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .grade-card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s;
            margin-bottom: 1.5rem;
            background-color: white;
            border: none;
        }
        
        .grade-card .card-header {
            padding: 1rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .grade-summary {
            background-color: var(--light-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .grade-summary .row {
            margin-bottom: 1rem;
        }
        
        .grade-summary .col-md-4 {
            margin-bottom: 1rem;
        }
        
        .grade-pill {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            color: white;
        }
        
        .grade-a { background-color: #06d6a0; }
        .grade-b { background-color: #3a86ff; }
        .grade-c { background-color: #ffbe0b; }
        .grade-d { background-color: #ff9f1c; }
        .grade-f { background-color: #ef476f; }
        
        .grade-progress {
            height: 10px;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }
        
        .table-grades th {
            background-color: #f8f9fa;
        }
        
        .table-grades td, .table-grades th {
            vertical-align: middle;
        }
        
        .missing-score {
            color: #dc3545;
            font-style: italic;
        }
        
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                padding: 0;
                background-color: white;
            }
            
            .container {
                max-width: 100%;
                width: 100%;
            }
            
            .grade-card, .grade-summary {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
        }
        .navbar-logo {
            height: 30px;
            width: auto;
            max-height: 30px;
            object-fit: contain;
        }   

        .navbar-brand {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <!-- Fixed Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background-color: #343a40;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="student_page.php">
                    <img src="images/logo.png" class="navbar-logo me-2" alt="Joaquin Smith National High School Logo">
                    Student Portal
                </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="student_page.php">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-chart-bar"></i> <?= htmlspecialchars($spreadsheet_name) ?> Grades
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3 text-white">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION["username"]); ?>
                    </span>
                    <button class="btn btn-outline-light btn-sm me-2 no-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="logout.php" class="btn btn-danger btn-sm no-print">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container page-content">
        <!-- Course Info -->
        <div class="alert alert-info">
            <div class="row">
                <div class="col-md-4">
                    <strong><i class="fas fa-book me-1"></i> Course:</strong>
                    <div><?= htmlspecialchars($spreadsheet_name) ?></div>
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-folder me-1"></i> Folder:</strong>
                    <div><?= htmlspecialchars($folder_name) ?></div>
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-chalkboard-teacher me-1"></i> Teacher:</strong>
                    <div><?= htmlspecialchars($teacher_name) ?></div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-4">
                    <strong><i class="fas fa-user-graduate me-1"></i> Student:</strong>
                    <div><?= htmlspecialchars($student_name) ?></div>
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-id-card me-1"></i> Student ID:</strong>
                    <div><?= htmlspecialchars($student_lrn) ?></div>
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-calendar me-1"></i> Date:</strong>
                    <div><?= date('F j, Y') ?></div>
                </div>
            </div>
        </div>
        
<!-- Grade Summary -->
<div class="grade-summary">
    <h4 class="mb-3">Grade Summary</h4>
    <div class="row">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow">
                <div class="card-body text-center">
                    <?php if($has_grade_record): ?>
                        <!-- Display official transmuted grade from teacher -->
                        <h1 class="display-1 fw-bold" style="color: #3a86ff;"><?= number_format($transmuted_grade, 0) ?></h1>
                        <div class="badge bg-secondary mb-2">Raw Score: <?= number_format($final_grade, 1) ?>%</div>
                    <?php else: ?>
                        <!-- Calculate and display transmuted grade -->
                        <h1 class="display-1 fw-bold" style="color: #3a86ff;"><?= number_format($transmuted_grade, 0) ?></h1>
                        <div class="badge bg-secondary mb-2">Raw Score: <?= number_format($final_grade, 1) ?>%</div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <!-- Grade badge based on transmuted grade -->
                        <?php
                        $gradeClass = 'grade-f';
                        if ($transmuted_grade >= 90) {
                            $gradeClass = 'grade-a';
                        } elseif ($transmuted_grade >= 85) {
                            $gradeClass = 'grade-b';
                        } elseif ($transmuted_grade >= 80) {
                            $gradeClass = 'grade-c';
                        } elseif ($transmuted_grade >= 75) {
                            $gradeClass = 'grade-d';
                        }
                        ?>
                        <span class="grade-pill <?= $gradeClass ?>"><?= $transmuted_grade >= 75 ? 'PASSED' : 'FAILED' ?></span>
                    </div>
                    <p class="fw-bold text-uppercase mt-3 mb-0">Final Grade</p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Grade Breakdown</h5>
                    
                    <!-- Written Work -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Written Work (<?= $written_percentage ?>%)</span>
                            <span><?= number_format($percentage_scores['Written'], 1) ?>%</span>
                        </div>
                        <div class="progress grade-progress">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?= min(100, $percentage_scores['Written']) ?>%" 
                                 aria-valuenow="<?= $percentage_scores['Written'] ?>" 
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Score: <?= $total_obtained['Written'] ?> / <?= $total_points['Written'] ?></span>
                            <span>Weighted: <?= number_format($weighted_scores['Written'], 1) ?>% of <?= $written_percentage ?>%</span>
                        </div>
                    </div>
                    
                    <!-- Performance Tasks -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Performance Tasks (<?= $performance_percentage ?>%)</span>
                            <span><?= number_format($percentage_scores['Performance'], 1) ?>%</span>
                        </div>
                        <div class="progress grade-progress">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= min(100, $percentage_scores['Performance']) ?>%" 
                                 aria-valuenow="<?= $percentage_scores['Performance'] ?>" 
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Score: <?= $total_obtained['Performance'] ?> / <?= $total_points['Performance'] ?></span>
                            <span>Weighted: <?= number_format($weighted_scores['Performance'], 1) ?>% of <?= $performance_percentage ?>%</span>
                        </div>
                    </div>
                    
                    <!-- Exams -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Exams (<?= $exam_percentage ?>%)</span>
                            <span><?= number_format($percentage_scores['Exam'], 1) ?>%</span>
                        </div>
                        <div class="progress grade-progress">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?= min(100, $percentage_scores['Exam']) ?>%" 
                                 aria-valuenow="<?= $percentage_scores['Exam'] ?>" 
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Score: <?= $total_obtained['Exam'] ?> / <?= $total_points['Exam'] ?></span>
                            <span>Weighted: <?= number_format($weighted_scores['Exam'], 1) ?>% of <?= $exam_percentage ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        
        <!-- Detailed Grades -->
        <?php if(count($activities) > 0): ?>
            <?php foreach(['Written', 'Performance', 'Exam'] as $category): ?>
                <?php if(isset($grouped_activities[$category]) && count($grouped_activities[$category]) > 0): ?>
                    <div class="grade-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php if($category == 'Written'): ?>
                                    <i class="fas fa-pencil-alt me-2"></i> Written Work
                                <?php elseif($category == 'Performance'): ?>
                                    <i class="fas fa-tasks me-2"></i> Performance Tasks
                                <?php elseif($category == 'Exam'): ?>
                                    <i class="fas fa-file-alt me-2"></i> Exams
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-grades">
                                    <thead>
                                        <tr>
                                            <th>Activity Name</th>
                                            <th class="text-center">Your Score</th>
                                            <th class="text-center">Max Score</th>
                                            <th class="text-center">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($grouped_activities[$category] as $activity): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($activity['name']) ?></td>
                                                <?php 
                                                    $has_score = isset($scores[$activity['id']]);
                                                    $score = $has_score ? $scores[$activity['id']]['score'] : null;
                                                    $max_score = $activity['max_score'];
                                                    $percentage = $has_score ? ($score / $max_score) * 100 : 0;
                                                ?>
                                                <td class="text-center">
                                                    <?php if($has_score): ?>
                                                        <?= $score ?>
                                                    <?php else: ?>
                                                        <span class="missing-score">Not graded</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= $max_score ?></td>
                                                <td class="text-center">
                                                    <?php if($has_score): ?>
                                                        <?= number_format($percentage, 1) ?>%
                                                    <?php else: ?>
                                                        <span class="missing-score">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>Total</td>
                                            <td class="text-center"><?= $total_obtained[$category] ?></td>
                                            <td class="text-center"><?= $total_points[$category] ?></td>
                                            <td class="text-center"><?= number_format($percentage_scores[$category], 1) ?>%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> No graded activities found for this course yet.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>