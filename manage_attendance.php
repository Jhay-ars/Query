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

// Check if spreadsheet ID is set and is a valid integer
$spreadsheet_id = isset($_GET['spreadsheet_id']) ? filter_var($_GET['spreadsheet_id'], FILTER_VALIDATE_INT) : 0;

if(!$spreadsheet_id) {
    header("location: teacher_page.php");
    exit;
}

// Initialize variables to avoid undefined variable warnings
$folder_id = 0;
$spreadsheet_name = '';
$folder_name = '';

try {
    // Get folder and spreadsheet info
    $sql = "SELECT s.folder_id, s.name AS spreadsheet_name, f.folder_name 
            FROM spreadsheets s 
            JOIN folders f ON s.folder_id = f.id 
            WHERE s.id = :spreadsheet_id AND f.teacher_id = :teacher_id";
            
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->bindParam(":teacher_id", $_SESSION["id"], PDO::PARAM_INT);
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

    // Validate date format
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d'); // Default to today if invalid format
    }

    // Save attendance records
    if(isset($_POST['save_attendance'])) {
        $attendance_date = filter_var($_POST['attendance_date'], FILTER_SANITIZE_STRING);
        
        // Validate date format again
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
            throw new Exception("Invalid date format.");
        }
        
        $conn->beginTransaction();
        
        try {
            // First, delete existing records for this date
            $sql = "DELETE FROM attendance 
                    WHERE date = :date AND student_id IN 
                    (SELECT id FROM students WHERE spreadsheet_id = :spreadsheet_id)";
            
            if($stmt = $conn->prepare($sql)) {
                $stmt->bindParam(":date", $attendance_date, PDO::PARAM_STR);
                $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Insert new attendance records
            if(isset($_POST['status'])) {
                foreach($_POST['status'] as $student_id => $status) {
                    // Validate student_id is integer
                    $student_id = filter_var($student_id, FILTER_VALIDATE_INT);
                    if(!$student_id) {
                        continue; // Skip invalid student IDs
                    }
                    
                    // Validate status is one of the allowed values
                    $allowed_statuses = ['present', 'absent', 'late', 'excused'];
                    if(!in_array($status, $allowed_statuses)) {
                        continue; // Skip invalid statuses
                    }
                    
                    $notes = isset($_POST['notes'][$student_id]) ? 
                             filter_var($_POST['notes'][$student_id], FILTER_SANITIZE_STRING) : '';
                    
                    $sql = "INSERT INTO attendance (student_id, spreadsheet_id, date, status, notes) 
                            VALUES (:student_id, :spreadsheet_id, :date, :status, :notes)";
                            
                    if($stmt = $conn->prepare($sql)) {
                        $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
                        $stmt->bindParam(":date", $attendance_date, PDO::PARAM_STR);
                        $stmt->bindParam(":status", $status, PDO::PARAM_STR);
                        $stmt->bindParam(":notes", $notes, PDO::PARAM_STR);
                        $stmt->execute();
                    }
                }
            }
            
            $conn->commit();
            $_SESSION['attendance_saved'] = true;
            
            // Redirect to refresh the page
            header("location: manage_attendance.php?spreadsheet_id=" . $spreadsheet_id . "&date=" . $attendance_date);
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error_message'] = "Failed to save attendance: " . $e->getMessage();
        }
    }

    // Fetch students for this spreadsheet
    $students = [];
    $sql = "SELECT * FROM students WHERE spreadsheet_id = :spreadsheet_id ORDER BY name";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll();
    }

    // Get attendance for current date
    $attendance = [];
    $sql = "SELECT student_id, status, notes FROM attendance 
            WHERE date = :date AND spreadsheet_id = :spreadsheet_id";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":date", $date, PDO::PARAM_STR);
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->execute();
        
        while($row = $stmt->fetch()) {
            $attendance[$row['student_id']] = [
                'status' => $row['status'],
                'notes' => $row['notes']
            ];
        }
    }

    // Statistics for current month
    $current_month = date('Y-m', strtotime($date));
    $stats = [];

    // Initialize stats for all students first to avoid undefined variable errors
    foreach($students as $student) {
        $stats[$student['id']] = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0
        ];
    }

    foreach($students as $student) {
        $student_id = $student['id'];
        
        // Stats are already initialized above, now we'll update with actual counts
        
        // Get the attendance counts for the current month
        $sql = "SELECT status, COUNT(*) as count 
                FROM attendance 
                WHERE student_id = :student_id 
                AND spreadsheet_id = :spreadsheet_id 
                AND DATE_FORMAT(date, '%Y-%m') = :month 
                GROUP BY status";
                
        if($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
            $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
            $stmt->bindParam(":month", $current_month, PDO::PARAM_STR);
            $stmt->execute();
            
            while($row = $stmt->fetch()) {
                $stats[$student_id][$row['status']] = $row['count'];
            }
        }
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - <?= htmlspecialchars($spreadsheet_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            padding-top: 56px; /* Added to accommodate fixed navbar */
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .container-fluid {
            padding: 0 20px;
        }
        .table-responsive {
            overflow-x: auto;
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
        .month-stats {
            font-size: 0.8rem;
        }
        .attendance-status-label {
            margin-right: 10px;
            cursor: pointer;
        }
        .attendance-notes {
            width: 100%;
            font-size: 0.9rem;
        }
        tr.status-present td:not(:nth-child(4)),
        tr.status-absent td:not(:nth-child(4)),
        tr.status-late td:not(:nth-child(4)),
        tr.status-excused td:not(:nth-child(4)) {
            transition: background-color 0.3s;
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .card {
            border: 1px solid #e0e5ec;
            border-radius: 8px;
        }
        .card-body {
            background-color: #fafbfc; /* Very subtle off-white */
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="spreadsheet.php?id=<?= $spreadsheet_id ?>">
                            <i class="fas fa-arrow-left"></i> Back to Spreadsheet
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="teacher_page.php ?id=<?= $spreadsheet_id ?>">
                            <i class="fas fa-folder"></i> Folder
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

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Manage Attendance - <?= htmlspecialchars($spreadsheet_name) ?></h2>
            <div></div>
        </div>
        
        <?php if(isset($_SESSION['attendance_saved']) && $_SESSION['attendance_saved']): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> Attendance saved successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['attendance_saved']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Date navigation -->
        <div class="row mb-4">
            <div class="col-md-6 mx-auto">
                <form method="get" class="d-flex justify-content-center">
                    <input type="hidden" name="spreadsheet_id" value="<?= $spreadsheet_id ?>">
                    <a href="?spreadsheet_id=<?= $spreadsheet_id ?>&date=<?= date('Y-m-d', strtotime('-1 day', strtotime($date))) ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <input type="date" name="date" class="form-control mx-2 text-center" style="max-width: 200px;" value="<?= $date ?>" onchange="this.form.submit()">
                    <a href="?spreadsheet_id=<?= $spreadsheet_id ?>&date=<?= date('Y-m-d', strtotime('+1 day', strtotime($date))) ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </form>
            </div>
        </div>
        
        <!-- Mark All Present Button (Moved to be more visible) -->
        <div class="mb-3">
            <button type="button" class="btn btn-success" onclick="setAllStatus('present')">
                <i class="fas fa-check-circle"></i> Mark All Present
            </button>
        </div>
        
        <!-- Attendance form -->
        <form method="post" id="attendanceForm">
            <input type="hidden" name="attendance_date" value="<?= $date ?>">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Month Stats</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($students) == 0): ?>
                            <tr>
                                <td colspan="5" class="text-center">No students found</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach($students as $student): ?>
                            <?php
                                $student_id = $student['id'];
                                $current_status = isset($attendance[$student_id]) ? $attendance[$student_id]['status'] : 'present';
                                $current_notes = isset($attendance[$student_id]) ? $attendance[$student_id]['notes'] : '';
                            ?>
                            <tr class="status-<?= $current_status ?>" id="student-row-<?= $student_id ?>">
                                <td><?= htmlspecialchars($student['student_id']) ?></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td class="month-stats">
                                    <div class="badge bg-success">Present: <?= isset($stats[$student_id]['present']) ? $stats[$student_id]['present'] : 0 ?></div>
                                    <div class="badge bg-danger">Absent: <?= isset($stats[$student_id]['absent']) ? $stats[$student_id]['absent'] : 0 ?></div>
                                    <div class="badge bg-warning">Late: <?= isset($stats[$student_id]['late']) ? $stats[$student_id]['late'] : 0 ?></div>
                                    <div class="badge bg-primary">Excused: <?= isset($stats[$student_id]['excused']) ? $stats[$student_id]['excused'] : 0 ?></div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap">
                                        <label class="attendance-status-label">
                                            <input type="radio" name="status[<?= $student_id ?>]" value="present" <?= $current_status == 'present' ? 'checked' : '' ?> onchange="updateRowStatus(<?= $student_id ?>, 'present')">
                                            Present
                                        </label>
                                        <label class="attendance-status-label">
                                            <input type="radio" name="status[<?= $student_id ?>]" value="absent" <?= $current_status == 'absent' ? 'checked' : '' ?> onchange="updateRowStatus(<?= $student_id ?>, 'absent')">
                                            Absent
                                        </label>
                                        <label class="attendance-status-label">
                                            <input type="radio" name="status[<?= $student_id ?>]" value="late" <?= $current_status == 'late' ? 'checked' : '' ?> onchange="updateRowStatus(<?= $student_id ?>, 'late')">
                                            Late
                                        </label>
                                        <label class="attendance-status-label">
                                            <input type="radio" name="status[<?= $student_id ?>]" value="excused" <?= $current_status == 'excused' ? 'checked' : '' ?> onchange="updateRowStatus(<?= $student_id ?>, 'excused')">
                                            Excused
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="notes[<?= $student_id ?>]" class="form-control attendance-notes" 
                                           placeholder="Optional notes" value="<?= htmlspecialchars($current_notes) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" name="save_attendance" class="btn btn-primary">Save Attendance</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Window load event
        window.addEventListener('DOMContentLoaded', (event) => {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Form submission confirmation
            document.getElementById('attendanceForm').addEventListener('submit', function(e) {
                // Check if any change has been made
                const hasChanges = checkForChanges();
                
                if (!hasChanges) {
                    e.preventDefault();
                    if (!confirm('No changes detected. Do you still want to save?')) {
                        return false;
                    }
                }
                
                return true;
            });
        });
        
        // Function to check if any changes were made to the form
        function checkForChanges() {
            // This is a simple implementation - you may want to enhance this
            // to compare with original values from the server
            return true;
        }
        
        function setAllStatus(status) {
            document.querySelectorAll(`input[type="radio"][value="${status}"]`).forEach(radio => {
                radio.checked = true;
                
                // Update row styling
                const studentId = radio.name.match(/\[(\d+)\]/)[1];
                updateRowStatus(studentId, status);
            });
        }
        
        function updateRowStatus(studentId, status) {
            const row = document.getElementById(`student-row-${studentId}`);
            row.className = ''; // Remove all classes
            row.classList.add(`status-${status}`);
        }
    </script>
</body>
</html>