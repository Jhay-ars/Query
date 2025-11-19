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
require_once ('TCPDF-6.9.3/tcpdf.php');
// Check if spreadsheet ID is set
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: teacher_page.php");
    exit;
}

// Create grades table if it doesn't exist
$create_grades_table_sql = "CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    spreadsheet_id INT NOT NULL,
    raw_grade FLOAT NOT NULL,
    transmuted_grade FLOAT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (spreadsheet_id) REFERENCES spreadsheets(id) ON DELETE CASCADE,
    UNIQUE KEY student_spreadsheet (student_id, spreadsheet_id)
)";

try {
    $conn->exec($create_grades_table_sql);
} catch (PDOException $e) {
    // If there's an error creating the table, log it but continue
    error_log("Error creating grades table: " . $e->getMessage());
}

// Create spreadsheet_settings table if it doesn't exist
$create_settings_table_sql = "CREATE TABLE IF NOT EXISTS spreadsheet_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spreadsheet_id INT NOT NULL,
    written_percentage FLOAT NOT NULL,
    performance_percentage FLOAT NOT NULL,
    exam_percentage FLOAT NOT NULL,
    is_locked BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (spreadsheet_id) REFERENCES spreadsheets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_spreadsheet (spreadsheet_id)
)";

try {
    $conn->exec($create_settings_table_sql);
} catch (PDOException $e) {
    // If there's an error creating the table, log it but continue
    error_log("Error creating spreadsheet_settings table: " . $e->getMessage());
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

// Handle setting of percentages - save in database
if (isset($_POST['set_percentages'])) {
    $written_percentage = floatval($_POST['written_percentage']);
    $performance_percentage = floatval($_POST['performance_percentage']);
    $exam_percentage = floatval($_POST['exam_percentage']);
    
    // Check if settings already exist for this spreadsheet
    $check_sql = "SELECT id FROM spreadsheet_settings WHERE spreadsheet_id = :spreadsheet_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Update existing settings
        $update_sql = "UPDATE spreadsheet_settings 
                       SET written_percentage = :written_percentage,
                           performance_percentage = :performance_percentage,
                           exam_percentage = :exam_percentage,
                           is_locked = TRUE
                       WHERE spreadsheet_id = :spreadsheet_id";
                       
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(":written_percentage", $written_percentage, PDO::PARAM_STR);
        $update_stmt->bindParam(":performance_percentage", $performance_percentage, PDO::PARAM_STR);
        $update_stmt->bindParam(":exam_percentage", $exam_percentage, PDO::PARAM_STR);
        $update_stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $update_stmt->execute();
    } else {
        // Insert new settings
        $insert_sql = "INSERT INTO spreadsheet_settings 
                       (spreadsheet_id, written_percentage, performance_percentage, exam_percentage, is_locked) 
                       VALUES 
                       (:spreadsheet_id, :written_percentage, :performance_percentage, :exam_percentage, TRUE)";
                       
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(":written_percentage", $written_percentage, PDO::PARAM_STR);
        $insert_stmt->bindParam(":performance_percentage", $performance_percentage, PDO::PARAM_STR);
        $insert_stmt->bindParam(":exam_percentage", $exam_percentage, PDO::PARAM_STR);
        $insert_stmt->execute();
    }
    
    $_SESSION['message'] = "Percentages set successfully. These values are now locked.";
}

// Load percentages from database
$sql = "SELECT written_percentage, performance_percentage, exam_percentage, is_locked 
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
    $percentages_set = $settings["is_locked"];
} else {
    // Default values
    $written_percentage = 40;
    $performance_percentage = 40;
    $exam_percentage = 20;
    $percentages_set = false;
}

// Add student
if (isset($_POST['add_student'])) {
    $student_id = trim($_POST['student_id']);
    $name = trim($_POST['student_name']);
    
    // First check if there's a user account with this student ID/LRN
    $has_account = false;
    
    // Check using the student_lrn field which is specifically for this purpose
    $sql = "SELECT id FROM users WHERE student_lrn = :student_id AND user_type = 'student'";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":student_id", $student_id, PDO::PARAM_STR);
        $stmt->execute();
        $has_account = ($stmt->rowCount() > 0);
    }
    
    // Try username as fallback (for backward compatibility)
    if (!$has_account) {
        $sql = "SELECT id FROM users WHERE username = :student_id AND user_type = 'student'";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_STR);
            $stmt->execute();
            $has_account = ($stmt->rowCount() > 0);
        }
    }
    
    // Only add student if they have an account
    if ($has_account) {
        // Check if student with same ID exists in this spreadsheet
        $sql = "SELECT id FROM students WHERE student_id = :student_id AND spreadsheet_id = :spreadsheet_id";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_STR);
            $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $existing = $stmt->fetch();
                $existing_id = $existing['id'];
                
                // Update existing student
                $sql = "UPDATE students SET name = :name WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":name", $name, PDO::PARAM_STR);
                $stmt->bindParam(":id", $existing_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $_SESSION['student_notification'] = "Student information updated successfully.";
            } else {
                // Insert new student
                $sql = "INSERT INTO students (student_id, name, spreadsheet_id) VALUES (:student_id, :name, :spreadsheet_id)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":student_id", $student_id, PDO::PARAM_STR);
                $stmt->bindParam(":name", $name, PDO::PARAM_STR);
                $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $_SESSION['student_notification'] = "Student added successfully.";
            }
        }
    } else {
        // Student does not have an account
        $_SESSION['student_notification'] = "Error: Cannot add student. The student with ID '" . $student_id . "' does not have an account yet. Please ask them to create an account first.";
    }
}

// Delete student
if (isset($_POST['delete_student'])) {
    $student_id = intval($_POST['student_id']);
    
    // Delete scores for this student
    $sql = "DELETE FROM scores WHERE student_id = :student_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete attendance for this student
    $sql = "DELETE FROM attendance WHERE student_id = :student_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete grades for this student
    $sql = "DELETE FROM grades WHERE student_id = :student_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete the student
    $sql = "DELETE FROM students WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":id", $student_id, PDO::PARAM_INT);
    $stmt->execute();
}

// Add activity
if (isset($_POST['add_activity'])) {
    $activity = trim($_POST['activity_name']);
    $category = $_POST['category'];
    $max_score = isset($_POST['max_score']) ? floatval($_POST['max_score']) : 100;
    
    $sql = "INSERT INTO activities (name, category, spreadsheet_id, max_score) VALUES (:name, :category, :spreadsheet_id, :max_score)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":name", $activity, PDO::PARAM_STR);
    $stmt->bindParam(":category", $category, PDO::PARAM_STR);
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    $stmt->bindParam(":max_score", $max_score, PDO::PARAM_STR);
    $stmt->execute();
}

// Delete activity
if (isset($_POST['delete_activity'])) {
    $activity_id = intval($_POST['activity_id']);
    
    // Delete scores for this activity
    $sql = "DELETE FROM scores WHERE activity_id = :activity_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":activity_id", $activity_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete the activity
    $sql = "DELETE FROM activities WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":id", $activity_id, PDO::PARAM_INT);
    $stmt->execute();
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

// Save scores
if (isset($_POST['save_scores']) && isset($_POST['scores'])) {
    $conn->beginTransaction();
    
    try {
        foreach ($_POST['scores'] as $student_id => $activity_scores) {
            $totals = ['Written' => [0,0], 'Performance' => [0,0], 'Exam' => [0,0]];
            
            // Get all activities for this spreadsheet
            $sql = "SELECT id, category, max_score FROM activities WHERE spreadsheet_id = :spreadsheet_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
            $stmt->execute();
            $all_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create a lookup for activities by ID
            $activities_by_id = [];
            foreach ($all_activities as $activity) {
                $activities_by_id[$activity['id']] = $activity;
            }
            
            foreach ($activity_scores as $activity_id => $score) {
                $score = floatval($score);
                
                // Update the score in the database
                $sql = "SELECT id FROM scores WHERE student_id = :student_id AND activity_id = :activity_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                $stmt->bindParam(":activity_id", $activity_id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Update existing score
                    $sql = "UPDATE scores SET score = :score WHERE student_id = :student_id AND activity_id = :activity_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(":score", $score, PDO::PARAM_STR);
                    $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                    $stmt->bindParam(":activity_id", $activity_id, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    // Insert new score
                    $sql = "INSERT INTO scores (student_id, activity_id, score) VALUES (:student_id, :activity_id, :score)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                    $stmt->bindParam(":activity_id", $activity_id, PDO::PARAM_INT);
                    $stmt->bindParam(":score", $score, PDO::PARAM_STR);
                    $stmt->execute();
                }
                
                // Add to totals for calculating grade
                if (isset($activities_by_id[$activity_id])) {
                    $activity = $activities_by_id[$activity_id];
                    $category = $activity['category'];
                    $max_score = $activity['max_score'];
                    
                    $totals[$category][0] += $score;
                    $totals[$category][1] += $max_score;
                }
            }
            
            // Calculate raw grade using the formula
            $written_component = ($totals['Written'][1] > 0) ? 
                (($totals['Written'][0] / $totals['Written'][1]) * $written_percentage) : 0;
                
            $performance_component = ($totals['Performance'][1] > 0) ? 
                (($totals['Performance'][0] / $totals['Performance'][1]) * $performance_percentage) : 0;
                
            $exam_component = ($totals['Exam'][1] > 0) ? 
                (($totals['Exam'][0] / $totals['Exam'][1]) * $exam_percentage) : 0;
            
            $raw_grade = $written_component + $performance_component + $exam_component;
            
            // Calculate transmuted grade
            $transmuted_grade = calculateTransmutedGrade($raw_grade);
            
            // Update or insert grade in the grades table
            $sql = "SELECT id FROM grades WHERE student_id = :student_id AND spreadsheet_id = :spreadsheet_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
            $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update existing grade
                $sql = "UPDATE grades 
                        SET raw_grade = :raw_grade, transmuted_grade = :transmuted_grade, updated_at = NOW() 
                        WHERE student_id = :student_id AND spreadsheet_id = :spreadsheet_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":raw_grade", $raw_grade, PDO::PARAM_STR);
                $stmt->bindParam(":transmuted_grade", $transmuted_grade, PDO::PARAM_STR);
                $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // Insert new grade
                $sql = "INSERT INTO grades (student_id, spreadsheet_id, raw_grade, transmuted_grade) 
                        VALUES (:student_id, :spreadsheet_id, :raw_grade, :transmuted_grade)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
                $stmt->bindParam(":raw_grade", $raw_grade, PDO::PARAM_STR);
                $stmt->bindParam(":transmuted_grade", $transmuted_grade, PDO::PARAM_STR);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        echo "<script>alert('Scores and grades saved successfully.');</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error saving scores: " . $e->getMessage() . "');</script>";
    }
}

// Fetch students and activities
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';

if (!empty($search)) {
    $search_condition = " AND (student_id LIKE :search OR name LIKE :search)";
}

// Get students
$students = array();
$sql = "SELECT * FROM students WHERE spreadsheet_id = :spreadsheet_id" . $search_condition . " ORDER BY name";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        $stmt->bindParam(":search", $search_param, PDO::PARAM_STR);
    }
    
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

// Get grades for all students
$grades = [];
$sql = "SELECT student_id, raw_grade, transmuted_grade FROM grades 
        WHERE student_id IN (SELECT id FROM students WHERE spreadsheet_id = :spreadsheet_id)
        AND spreadsheet_id = :spreadsheet_id";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($folder_name . '_' . $spreadsheet_name) ?> - Spreadsheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            padding-top: 56px; /* Added to accommodate fixed navbar */
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .card {
            border: 1px solid #e0e5ec;
            border-radius: 8px;
        }
        .card-body {
            background-color: #fafbfc; /* Very subtle off-white */
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .spreadsheet-table input[type='number'] { width: 70px; }
        .container-fluid { padding: 0 20px; }
        .table-container { overflow-x: auto; }
        .btn-attendance { padding: 2px 8px; font-size: 0.8rem; }
        .student-actions { white-space: nowrap; }
        .quick-actions-header {
            background-color: #6c757d; /* Dark gray header for quick actions */
        }
        .btn-manage-attendance {
            background-color: #17a2b8; /* Distinctive blue for manage attendance */
            border-color: #17a2b8;
            color: white;
        }
        .btn-manage-attendance:hover {
            background-color: #138496;
            border-color: #117a8b;
            color: white;
        }
        .btn-view-attendance {
            background-color: #5a6268; /* Darker gray for view attendance */
            border-color: #5a6268;
            color: white;
        }
        .btn-view-attendance:hover {
            background-color: #4e555b;
            border-color: #4e555b;
            color: white;
        }
        .btn-export-pdf {
            background-color: #6610f2; /* Purple for PDF export */
            border-color: #6610f2;
            color: white;
        }
        .btn-export-pdf:hover {
            background-color: #520dc2;
            border-color: #4d0db7;
            color: white;
        }
        .percentages-card .card-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .percentages-info {
            flex: 1;
        }
        .percentage-item {
            display: inline-block;
            margin-right: 20px;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .percentage-item strong {
            color: #495057;
        }
        .percentage-value {
            font-weight: bold;
            color: #0d6efd;
        }
        /* Grade styling */
        .grade-value {
            font-weight: bold;
        }
        .raw-grade {
            color: #6c757d;
        }
        .transmuted-grade {
            color: #28a745;
            font-size: 1.1em;
        }
        .legend-box {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin-top: 20px;
        }
        .legend-title {
            font-weight: bold;
            margin-bottom: 10px;
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
            <a class="navbar-brand d-flex align-items-center" href="teacher_page.php">
                    <img src="images/logo.png" class="navbar-logo me-2" alt="Joaquin Smith National High School Logo">
                    Teacher Dashboard
                </a>
            <a class="navbar-brand" href="#">
                 <?= htmlspecialchars($folder_name) ?> - <?= htmlspecialchars($spreadsheet_name) ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="folder_view.php?id=<?= $folder_id ?>">
                            <i class="fas fa-arrow-left"></i> Back to Folder
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3 text-white">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION["username"]); ?>
                    </span>
                    <a href="reset_password.php" class="btn btn-warning btn-sm me-2">
                        <i class="fas fa-key"></i> Reset Password
                    </a>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4 pt-3" id="spreadsheet-container">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="card-title mb-4 text-primary">
                    <i class="fas fa-table"></i> Spreadsheet Manager
                </h2>

                <?php if(isset($_SESSION['student_notification'])): ?>
                    <div class="alert <?= (strpos($_SESSION['student_notification'], 'Error') !== false) ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
                        <i class="<?= (strpos($_SESSION['student_notification'], 'Error') !== false) ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle' ?>"></i>
                        <?= $_SESSION['student_notification'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['student_notification']); ?>
                <?php endif; ?>
                    <?php if(isset($_SESSION['message'])): ?>
                    <div class="alert <?= (strpos($_SESSION['message'], 'Error') !== false) ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
                        <i class="<?= (strpos($_SESSION['message'], 'Error') !== false) ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle' ?>"></i>
                        <?= $_SESSION['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header quick-actions-header text-white">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="manage_attendance.php?spreadsheet_id=<?= $spreadsheet_id ?>" class="btn btn-manage-attendance">
                                        <i class="fas fa-calendar-check"></i> Manage Attendance
                                    </a>
                                    <a href="class_attendance.php?id=<?= $spreadsheet_id ?>" class="btn btn-view-attendance">
                                        <i class="fas fa-calendar-alt"></i> View Class Attendance
                                    </a>
                                    <a href="export_to_pdf_spreadsheets.php?id=<?= $spreadsheet_id ?>" class="btn btn-export-pdf" target="_blank">
                                        <i class="fas fa-file-pdf"></i> Export to PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade Percentages Display -->
                <div class="card mb-4 percentages-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Grade Percentages</h5>
                    </div>
                    <div class="card-body">
                        <div class="percentages-info">
                            <div class="percentage-item">
                                <strong>Written:</strong> <span class="percentage-value"><?= $written_percentage ?>%</span>
                            </div>
                            <div class="percentage-item">
                                <strong>Performance:</strong> <span class="percentage-value"><?= $performance_percentage ?>%</span>
                            </div>
                            <div class="percentage-item">
                                <strong>Exam:</strong> <span class="percentage-value"><?= $exam_percentage ?>%</span>
                            </div>
                        </div>
                        <?php if (!$percentages_set): ?>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#percentagesModal">
                                <i class="fas fa-cog"></i> Set Percentages
                            </button>
                        </div>
                        <?php else: ?>
                        <div>
                            <span class="badge bg-success">
                                <i class="fas fa-lock"></i> Percentages Locked
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search Students -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Student Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <form method="get" class="d-flex">
                                    <input type="hidden" name="id" value="<?= $spreadsheet_id ?>">
                                    <input type="text" name="search" class="form-control me-2" placeholder="Search by ID or Name" value="<?= htmlspecialchars($search) ?>">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <?php if($search): ?>
                                        <a href="?id=<?= $spreadsheet_id ?>" class="btn btn-success">Clear</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Add Student -->
                        <form method="post" class="row g-2">
                            <div class="col-md-3">
                                <input type="text" name="student_id" class="form-control" placeholder="Student ID" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="student_name" class="form-control" placeholder="Student Name" required>
                            </div>
                            <div class="col-md-2">
                                <button name="add_student" class="btn btn-primary">Add Student</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Add Activity -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Activity Management</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-2" id="activity-form">
                            <div class="col-md-3">
                                <input type="text" name="activity_name" class="form-control" placeholder="Activity Name" required>
                            </div>
                            <div class="col-md-3">
                                <select name="category" class="form-select" required>
                                    <option value="Written">Written</option>
                                    <option value="Performance">Performance</option>
                                    <option value="Exam">Exam</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" step="0.1" name="max_score" class="form-control" placeholder="Max Score" required>
                            </div>
                            <div class="col-md-2">
                                <button name="add_activity" class="btn btn-primary">Add Activity</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Score Table -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Grades</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" onsubmit="return confirm('Save all scores?')">
                            <input type="hidden" name="save_scores">
                            <div class="table-container">
                                <table class="table table-bordered spreadsheet-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Actions</th>
                                            <th>LRN</th>
                                            <th>Name</th>
                                            <?php
                                            $categories = ['Written', 'Performance', 'Exam'];
                                            foreach ($categories as $cat):
                                                if (isset($grouped_activities[$cat])):
                                            ?>
                                                <th colspan="<?= count($grouped_activities[$cat]) + 1 ?>" class="text-center"><?= $cat ?></th>
                                            <?php endif; endforeach; ?>
                                            <th>Overall</th>
                                            <th>Raw Grade</th>
                                            <th>Transmuted Grade</th>
                                        </tr>
                                        <tr>
                                            <td></td><td></td><td></td>
                                            <?php foreach ($categories as $cat):
                                                if (isset($grouped_activities[$cat])):
                                                    foreach ($grouped_activities[$cat] as $a): ?>
                                                        <td>
                                                            <?= htmlspecialchars($a['name']) ?> (<?= $a['max_score'] ?>)
                                                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this activity?')">
                                                                <input type="hidden" name="activity_id" value="<?= $a['id'] ?>">
                                                                <button name="delete_activity" class="btn btn-sm btn-outline-danger">×</button>
                                                            </form>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td><strong>Total</strong></td>
                                            <?php endif; endforeach; ?>
                                            <td><strong>Total</strong></td>
                                            <td><strong>Raw</strong></td>
                                            <td><strong>Final</strong></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td class="student-actions">
                                                <a href="student_attendance_teacher.php?student_id=<?= $s['id'] ?>&spreadsheet_id=<?= $spreadsheet_id ?>" class="btn btn-sm btn-primary btn-attendance text-white">Attendance</a>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete student?')">
                                                    <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                                    <button name="delete_student" class="btn btn-sm btn-danger">×</button>
                                                </form>
                                            </td>
                                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                                            <td><?= htmlspecialchars($s['name']) ?></td>

                                            <?php
                                            $totals = ['Written' => [0,0], 'Performance' => [0,0], 'Exam' => [0,0]];
                                            foreach ($categories as $cat):
                                                if (isset($grouped_activities[$cat])):
                                                    foreach ($grouped_activities[$cat] as $a):
                                                        $score = isset($scores[$s['id']][$a['id']]) ? floatval($scores[$s['id']][$a['id']]) : 0;
                                                        $totals[$cat][0] += $score;
                                                        $totals[$cat][1] += $a['max_score'];
                                            ?>
                                                <td><input type="number" step="0.1" class="form-control" name="scores[<?= $s['id'] ?>][<?= $a['id'] ?>]" value="<?= $score ?>"></td>
                                            <?php endforeach; ?>
                                                <td><strong><?= $totals[$cat][0] ?>/<?= $totals[$cat][1] ?></strong></td>
                                            <?php endif; endforeach; ?>

                                            <?php
                                            // Calculate overall scores
                                            $overall_earned = array_sum(array_column($totals, 0));
                                            $overall_max = array_sum(array_column($totals, 1));

                                            // Calculate raw grade using the correct formula
                                            $written_component = ($totals['Written'][1] > 0) ? 
                                                (($totals['Written'][0] / $totals['Written'][1]) * $written_percentage) : 0;
                                                
                                            $performance_component = ($totals['Performance'][1] > 0) ? 
                                                (($totals['Performance'][0] / $totals['Performance'][1]) * $performance_percentage) : 0;
                                                
                                            $exam_component = ($totals['Exam'][1] > 0) ? 
                                                (($totals['Exam'][0] / $totals['Exam'][1]) * $exam_percentage) : 0;
                                                
                                            // Sum all components to get the final raw grade
                                            $raw_grade = $written_component + $performance_component + $exam_component;
                                            
                                            // Get the transmuted grade
                                            $transmuted_grade = isset($grades[$s['id']]) ? $grades[$s['id']]['transmuted_grade'] : calculateTransmutedGrade($raw_grade);
                                            ?>

                                            <td><strong><?= $overall_earned ?>/<?= $overall_max ?></strong></td>
                                            <td class="raw-grade"><strong><?= round($raw_grade, 2) ?>%</strong></td>
                                            <td class="transmuted-grade"><strong><?= round($transmuted_grade, 0) ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-primary mt-3">Save Scores</button>
                            
                            <!-- Grading System Legend -->
                            <div class="legend-box mt-4">
                                <div class="legend-title">Grading System</div>
                                <p class="mb-2">The final grade is calculated using the following steps:</p>
                                <ol>
                                    <li>Calculate the percentage score for each category (Written, Performance, Exam)</li>
                                    <li>Apply the weights to each category (Written: <?= $written_percentage ?>%, Performance: <?= $performance_percentage ?>%, Exam: <?= $exam_percentage ?>%)</li>
                                    <li>Sum these weighted components to get the raw grade</li>
                                    <li>Apply the transmutation table to convert the raw grade to the final transmuted grade</li>
                                </ol>
                                <p><strong>Note:</strong> The transmuted grade is calculated automatically when you save scores.</p>
                                
                                <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#transmutationTable" role="button">
                                    View Transmutation Table
                                </a>
                                
                                <div class="collapse mt-3" id="transmutationTable">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Raw Grade Range</th>
                                                    <th>Transmuted Grade</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>100</td><td>100</td></tr>
                                                <tr><td>98.40 - 99.99</td><td>99</td></tr>
                                                <tr><td>96.80 - 98.39</td><td>98</td></tr>
                                                <tr><td>95.20 - 96.79</td><td>97</td></tr>
                                                <tr><td>93.60 - 95.19</td><td>96</td></tr>
                                                <tr><td>92.00 - 93.59</td><td>95</td></tr>
                                                <tr><td>90.40 - 91.99</td><td>94</td></tr>
                                                <tr><td>88.80 - 90.39</td><td>93</td></tr>
                                                <tr><td>87.20 - 88.79</td><td>92</td></tr>
                                                <tr><td>85.60 - 87.19</td><td>91</td></tr>
                                                <tr><td>84.00 - 85.59</td><td>90</td></tr>
                                                <tr><td>82.40 - 83.99</td><td>89</td></tr>
                                                <tr><td>80.80 - 82.39</td><td>88</td></tr>
                                                <tr><td>79.20 - 80.79</td><td>87</td></tr>
                                                <tr><td>77.60 - 79.19</td><td>86</td></tr>
                                                <tr><td>76.00 - 77.59</td><td>85</td></tr>
                                                <tr><td>74.40 - 75.99</td><td>84</td></tr>
                                                <tr><td>72.80 - 74.39</td><td>83</td></tr>
                                                <tr><td>71.20 - 72.79</td><td>82</td></tr>
                                                <tr><td>69.60 - 71.19</td><td>81</td></tr>
                                                <tr><td>68.00 - 69.59</td><td>80</td></tr>
                                                <tr><td>66.40 - 67.99</td><td>79</td></tr>
                                                <tr><td>64.80 - 66.39</td><td>78</td></tr>
                                                <tr><td>63.20 - 64.79</td><td>77</td></tr>
                                                <tr><td>61.60 - 63.19</td><td>76</td></tr>
                                                <tr><td>60.00 - 61.59</td><td>75</td></tr>
                                                <tr><td>56.00 - 59.99</td><td>74</td></tr>
                                                <tr><td>52.00 - 55.99</td><td>73</td></tr>
                                                <tr><td>48.00 - 51.99</td><td>72</td></tr>
                                                <tr><td>44.00 - 47.99</td><td>71</td></tr>
                                                <tr><td>40.00 - 43.99</td><td>70</td></tr>
                                                <tr><td>36.00 - 39.99</td><td>69</td></tr>
                                                <tr><td>32.00 - 35.99</td><td>68</td></tr>
                                                <tr><td>28.00 - 31.99</td><td>67</td></tr>
                                                <tr><td>24.00 - 27.99</td><td>66</td></tr>
                                                <tr><td>20.00 - 23.99</td><td>65</td></tr>
                                                <tr><td>16.00 - 19.99</td><td>64</td></tr>
                                                <tr><td>12.00 - 15.99</td><td>63</td></tr>
                                                <tr><td>8.00 - 11.99</td><td>62</td></tr>
                                                <tr><td>4.00 - 7.99</td><td>61</td></tr>
                                                <tr><td>0 - 3.99</td><td>60</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Percentages Modal -->
    <div class="modal fade" id="percentagesModal" tabindex="-1" aria-labelledby="percentagesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="percentagesModalLabel">Set Grade Percentages</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="percentagesForm">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Important:</strong> Once you set these percentages, they cannot be changed for this spreadsheet session.
                        </div>
                        <div class="mb-3">
                            <label for="written_percentage" class="form-label">Written Percentage (%)</label>
                            <input type="number" class="form-control" id="written_percentage" name="written_percentage" value="<?= $written_percentage ?>" min="0" max="100" step="0.1" required>
                            <div class="form-text">Percentage allocated to written activities</div>
                        </div>
                        <div class="mb-3">
                            <label for="performance_percentage" class="form-label">Performance Percentage (%)</label>
                            <input type="number" class="form-control" id="performance_percentage" name="performance_percentage" value="<?= $performance_percentage ?>" min="0" max="100" step="0.1" required>
                            <div class="form-text">Percentage allocated to performance activities</div>
                        </div>
                        <div class="mb-3">
                            <label for="exam_percentage" class="form-label">Exam Percentage (%)</label>
                            <input type="number" class="form-control" id="exam_percentage" name="exam_percentage" value="<?= $exam_percentage ?>" min="0" max="100" step="0.1" required>
                            <div class="form-text">Percentage allocated to exams</div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Note: The sum of all percentages should equal 100%.
                            <div id="totalPercentage" class="mt-2 fw-bold"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmSetPercentages">Set Percentages</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Confirm Percentages</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Warning:</strong> You are about to set the grading percentages for this spreadsheet. These values will be locked after confirmation and cannot be changed later.
                    </div>
                    <p>Please confirm the following percentages:</p>
                    <ul>
                        <li><strong>Written:</strong> <span id="confirm-written"></span>%</li>
                        <li><strong>Performance:</strong> <span id="confirm-performance"></span>%</li>
                        <li><strong>Exam:</strong> <span id="confirm-exam"></span>%</li>
                        <li><strong>Total:</strong> <span id="confirm-total"></span>%</li>
                    </ul>
                    <p>Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="percentagesForm" name="set_percentages" class="btn btn-danger">Yes, I'm Sure</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modal objects
            var percentagesModal = new bootstrap.Modal(document.getElementById('percentagesModal'));
            var confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            
            // Calculate total percentage
            const writtenInput = document.getElementById('written_percentage');
            const performanceInput = document.getElementById('performance_percentage');
            const examInput = document.getElementById('exam_percentage');
            const totalPercentageDiv = document.getElementById('totalPercentage');
            
            function updateTotal() {
                const written = parseFloat(writtenInput.value) || 0;
                const performance = parseFloat(performanceInput.value) || 0;
                const exam = parseFloat(examInput.value) || 0;
                const total = written + performance + exam;
                
                totalPercentageDiv.textContent = `Current total: ${total.toFixed(1)}%`;
                
                if (total !== 100) {
                    totalPercentageDiv.classList.add('text-danger');
                    totalPercentageDiv.classList.remove('text-success');
                } else {
                    totalPercentageDiv.classList.add('text-success');
                    totalPercentageDiv.classList.remove('text-danger');
                }
            }
            
            writtenInput.addEventListener('input', updateTotal);
            performanceInput.addEventListener('input', updateTotal);
            examInput.addEventListener('input', updateTotal);
            
            // Initial calculation
            updateTotal();
            
            // Handle confirmation flow
            const confirmBtn = document.getElementById('confirmSetPercentages');
            
            confirmBtn.addEventListener('click', function() {
                const written = parseFloat(writtenInput.value) || 0;
                const performance = parseFloat(performanceInput.value) || 0;
                const exam = parseFloat(examInput.value) || 0;
                const total = written + performance + exam;
                
                // Update confirmation modal values
                document.getElementById('confirm-written').textContent = written.toFixed(1);
                document.getElementById('confirm-performance').textContent = performance.toFixed(1);
                document.getElementById('confirm-exam').textContent = exam.toFixed(1);
                document.getElementById('confirm-total').textContent = total.toFixed(1);
                
                // Hide percentages modal
                percentagesModal.hide();
                
                // Show confirmation modal
                confirmationModal.show();
            });
        });
    </script>
</body>
</html>