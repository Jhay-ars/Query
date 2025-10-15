<?php
session_start();
 
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

if($_SESSION["user_type"] !== "student") {
    header("location: index.php");
    exit;
}

require_once "config.php";

// Get student's LRN/student ID
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

// Find all spreadsheets where the student is enrolled - try with student_lrn, then fallback to username
$sql = "SELECT s.id AS spreadsheet_id, s.name AS spreadsheet_name, 
        f.id AS folder_id, f.folder_name, f.teacher_id,
        u.username AS teacher_name
        FROM students st
        JOIN spreadsheets s ON st.spreadsheet_id = s.id
        JOIN folders f ON s.folder_id = f.id
        JOIN users u ON f.teacher_id = u.id
        WHERE st.student_id = :student_id
        ORDER BY f.folder_name, s.name";

if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":student_id", $student_lrn, PDO::PARAM_STR);
    $stmt->execute();
    $student_courses = $stmt->fetchAll();
    
    // If no courses found, try with username as fallback
    if(count($student_courses) == 0 && $student_lrn != $student_username) {
        $stmt->bindParam(":student_id", $student_username, PDO::PARAM_STR);
        $stmt->execute();
        $student_courses = $stmt->fetchAll();
    }
}

// Check for pending invitations
$pending_invitations = [];
$sql = "SELECT it.*, st.name AS student_name, st.student_id, sp.id AS spreadsheet_id, sp.name AS spreadsheet_name, 
        f.folder_name, u.username AS teacher_name
        FROM invitation_tokens it
        JOIN students st ON it.student_id = st.id
        JOIN spreadsheets sp ON st.spreadsheet_id = sp.id
        JOIN folders f ON sp.folder_id = f.id
        JOIN users u ON f.teacher_id = u.id
        WHERE st.student_id = :student_id AND it.used = 0 AND it.expires_at > NOW()";

if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":student_id", $student_username, PDO::PARAM_STR);
    $stmt->execute();
    $pending_invitations = $stmt->fetchAll();
}

// Accept invitation
if(isset($_POST['accept_invitation']) && !empty($_POST['token'])) {
    $token = $_POST['token'];
    
    // Mark invitation as used
    $sql = "UPDATE invitation_tokens SET used = 1 WHERE token = :token AND used = 0";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);
        $stmt->execute();
        
        $_SESSION['invitation_accepted'] = "You have successfully joined the class.";
        
        // Redirect to refresh page
        header("location: student_page.php");
        exit;
    }
}

// Decline invitation
if(isset($_POST['decline_invitation']) && !empty($_POST['token'])) {
    $token = $_POST['token'];
    
    // Mark invitation as used but also mark it as declined
    $sql = "UPDATE invitation_tokens SET used = 1, declined = 1 WHERE token = :token AND used = 0";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);
        $stmt->execute();
        
        $_SESSION['invitation_declined'] = "You have declined to join the class.";
        
        // Redirect to refresh page
        header("location: student_page.php");
        exit;
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
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
            border-radius: 15px;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        /* Alerts */
        .alert {
            margin-top: 1rem;
        }

        /* Course Invitations */
        .invitation-card {
            border-left: 4px solid var(--warning-color);
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        /* Folder Section */
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .folder-card {
            border: 1px solid #e0e5ec;
            border-radius: 10px;
            overflow: hidden;
            background-color: white;
            transition: all 0.4s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .folder-header {
            background-color: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .folder-card.collapsed .folder-content-wrapper {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            padding: 0;
            display: none;
        }

        .folder-content-wrapper {
            transition: max-height 0.4s ease-in-out, opacity 0.4s ease-in-out;
            padding: 15px;
        }

        .rotate-icon {
            transition: transform 0.3s ease;
        }

        .folder-card.collapsed .rotate-icon {
            transform: rotate(-90deg);
        }

        /* Course Cards */
        .course-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .course-card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background-color: white;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .course-card .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }

        .course-card .card-body {
            padding: 1rem;
        }

        .teacher-badge {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-block;
        }

        /* Buttons */
        .action-btn {
            border-radius: 5px;
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding: 20px 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            border-top: 1px solid #dee2e6;
        }

        .toast-container {
            position: fixed;
            top: 76px; /* Just below the navbar */
            right: 20px;
            z-index: 1050;
        }
        
        .custom-toast {
            min-width: 300px;
            max-width: 400px;
            font-size: 1rem;
            background-color: #fff;
            border-left: 5px solid #28a745;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            opacity: 0;
            transition: opacity 0.4s ease, transform 0.4s ease;
            transform: translateX(100%);
            padding: 0.75rem 1rem;
        }
        
        .custom-toast.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .custom-toast .toast-header {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .toast-icon {
            font-size: 1.2rem;
            margin-right: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .course-list {
                grid-template-columns: 1fr;
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
                        <a class="nav-link active" href="student_page.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-light">
                            <i class="far fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
                        </span>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3 text-white">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION["username"]); ?>
                    </span>
                    <a href="reset_password.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="fas fa-key"></i> Reset Password
                    </a>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div>
                <h2 class="mb-0">
                    <i class="fas fa-user-graduate text-primary me-2"></i>
                    Student Dashboard
                </h2>
                <p class="text-muted mb-0">Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?></p>
            </div>
        </div>

        <!-- Toast notification container -->
        <div class="toast-container">
            <?php if(isset($_SESSION['login_success'])): ?>
                <div class="toast custom-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
                    <div class="toast-header">
                        <i class="fas fa-check-circle toast-icon text-success"></i>
                        <strong class="me-auto">Success</strong>
                        <small>Just now</small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        <i class="fas fa-user-check me-2"></i> 
                        <?= $_SESSION['login_success'] ?? 'You have successfully logged in.' ?>
                    </div>
                </div>
                <?php unset($_SESSION['login_success']); ?>
            <?php endif; ?>
        </div>

        <!-- Rest of your HTML remains unchanged -->
        <?php if(isset($_SESSION['invitation_accepted'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['invitation_accepted'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['invitation_accepted']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['invitation_declined'])): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-times-circle me-2"></i> <?= $_SESSION['invitation_declined'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['invitation_declined']); ?>
        <?php endif; ?>

        <!-- Course Invitations -->
        <?php if(count($pending_invitations) > 0): ?>
            <div class="card mb-4 shadow-sm border-warning border-start border-4">
                <div class="card-header bg-warning fw-bold">
                    <i class="fas fa-bell me-2"></i>Course Invitations
                </div>
                <div class="card-body">
                    <?php foreach($pending_invitations as $invitation): ?>
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">
                                <div class="d-md-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($invitation["spreadsheet_name"]) ?></h5>
                                        <p class="mb-1">
                                            <span class="badge bg-secondary"><?= htmlspecialchars($invitation["folder_name"]) ?></span>
                                            <span class="text-muted ms-2">Teacher: <?= htmlspecialchars($invitation["teacher_name"]) ?></span>
                                        </p>
                                        <?php if(!empty($invitation["message"])): ?>
                                            <div class="alert alert-info mt-2 mb-2 p-2">
                                                <small><i class="fas fa-info-circle me-1"></i> <?= nl2br(htmlspecialchars($invitation["message"])) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <p class="text-muted small mb-md-0">
                                            <i class="far fa-clock me-1"></i> Expires: <?= date('F j, Y', strtotime($invitation["expires_at"])) ?>
                                        </p>
                                    </div>
                                    <div class="mt-2 mt-md-0">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="token" value="<?= $invitation["token"] ?>">
                                            <button type="submit" name="accept_invitation" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Accept
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to decline this invitation?');">
                                            <input type="hidden" name="token" value="<?= $invitation["token"] ?>">
                                            <button type="submit" name="decline_invitation" class="btn btn-outline-danger btn-sm ms-1">
                                                <i class="fas fa-times me-1"></i> Decline
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- My Courses -->
        <div class="mb-4">
            <h4 class="mb-3">Your Courses</h4>
            
            <?php if(count($student_courses) > 0): ?>
                <?php 
                    // Group courses by folder
                    $grouped_courses = [];
                    foreach($student_courses as $course) {
                        $folder_id = $course['folder_id'];
                        $folder_name = $course['folder_name'];
                        
                        if(!isset($grouped_courses[$folder_id])) {
                            $grouped_courses[$folder_id] = [
                                'folder_name' => $folder_name,
                                'teacher_name' => $course['teacher_name'],
                                'courses' => []
                            ];
                        }
                        
                        $grouped_courses[$folder_id]['courses'][] = $course;
                    }
                ?>

                <!-- Grid container for folder cards -->
                <div class="folder-grid">
                    <?php foreach($grouped_courses as $folder_id => $folder_data): ?>
                        
                        <div class="folder-card">
                            <div class="folder-header" onclick="toggleFolder(this)">
                                <div>
                                    <i class="fas fa-folder me-2 text-warning"></i>
                                    <strong><?= htmlspecialchars($folder_data['folder_name']) ?></strong>
                                    <div class="small text-muted">
                                        <i class="fas fa-user me-1"></i> <?= htmlspecialchars($folder_data['teacher_name']) ?>
                                    </div>
                                </div>
                                <div>
                                    <i class="fas fa-chevron-down rotate-icon"></i>
                                </div>
                            </div>
                            <div class="folder-content-wrapper">
                                <div class="folder-content">
                                    <div class="course-list">
                                        <?php foreach($folder_data['courses'] as $course): ?>
                                            <div class="course-card">
                                                <div class="card-header">
                                                    <h5 class="mb-1"><?= htmlspecialchars($course["spreadsheet_name"]) ?></h5>
                                                    <span class="teacher-badge">
                                                        <i class="fas fa-chalkboard-teacher me-1"></i> <?= htmlspecialchars($folder_data['teacher_name']) ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="d-flex flex-column">
                                                        <a href="student_grade_view.php?id=<?= $course["spreadsheet_id"] ?>" class="btn btn-primary action-btn mb-2">
                                                            <i class="fas fa-chart-bar"></i> View Grades
                                                        </a>
                                                        <a href="student_attendance_detail.php?student_id=<?= $student_lrn ?>&spreadsheet_id=<?= $course["spreadsheet_id"] ?>" class="btn btn-info action-btn text-white">
                                                            <i class="fas fa-calendar-check"></i> View Attendance
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You are not enrolled in any courses yet. Please contact your teacher.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Automatically dismiss alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Initialize toasts
            var toastEl = document.querySelector('.toast');
            if (toastEl) {
                var toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    
        function toggleFolder(headerEl) {
            const folderCard = headerEl.closest('.folder-card');
            folderCard.classList.toggle('collapsed');
            
            // Access the content wrapper directly
            const contentWrapper = folderCard.querySelector('.folder-content-wrapper');
            
            // Toggle visibility based on collapsed state
            if (folderCard.classList.contains('collapsed')) {
                contentWrapper.style.display = "none";
            } else {
                contentWrapper.style.display = "block";
            }
        }
    </script>
</body>
</html>