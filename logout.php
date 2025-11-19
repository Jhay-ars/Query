<?php
// Initialize the session
session_start();

// Check if the user is already logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Get user information for the goodbye message
$username = $_SESSION["username"] ?? "User";
$user_type = $_SESSION["user_type"] ?? "user";

// Process logout if confirmed
if(isset($_POST["confirm_logout"]) && $_POST["confirm_logout"] === "yes") {
    // Unset all of the session variables
    $_SESSION = array();
    
    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Set logout success flag in a session for the login page
    session_start();
    $_SESSION["logout_success"] = true;
    
    // Redirect to login page
    header("location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - School Grading System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            margin: 0;
        }
        
        .logout-card {
            width: 450px;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .icon-section {
            margin-bottom: 20px;
        }
        
        .icon-logout {
            font-size: 64px;
            background: linear-gradient(135deg, #4e73df 0%, #1cc88a 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logout-title {
            font-size: 28px;
            color: var(--dark-color);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .logout-message {
            color: #6c757d;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .btn-logout {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .btn-logout:hover {
            background-color: #d32f2f;
            border-color: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(217, 48, 37, 0.3);
        }
        
        .btn-back {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background-color: #3a5ccc;
            border-color: #3a5ccc;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }
        
        .school-logo {
            width: 100px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .warning-text {
            font-size: 14px;
            color: #856404;
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin-bottom: 20px;
            text-align: left;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <div class="icon-section">
            <img src="images/logo.png" alt="School Logo" class="school-logo">
        </div>
        <h2 class="logout-title">Ready to Leave?</h2>
        <p class="logout-message">
            Hello, <strong><?php echo htmlspecialchars($username); ?></strong>!<br>
            Are you sure you want to end your current session?
        </p>
        
        <div class="warning-text">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> Any unsaved changes will be lost when you log out.
        </div>
        
        <div class="d-grid gap-2">
            <form method="post">
                <input type="hidden" name="confirm_logout" value="yes">
                <button type="submit" class="btn btn-danger btn-logout">
                    <i class="fas fa-sign-out-alt me-2"></i>Yes, Log Me Out
                </button>
            </form>
            
            <?php
            // Determine the appropriate redirect page based on user type
            $redirect_page = 'index.php'; // Default fallback
            $dashboard_name = 'Dashboard';
            
            if(isset($_SESSION["user_type"])) {
                if($_SESSION["user_type"] === 'student') {
                    $redirect_page = 'student_page.php';
                    $dashboard_name = 'Student Dashboard';
                } 
                elseif($_SESSION["user_type"] === 'teacher') {
                    $redirect_page = 'teacher_page.php';
                    $dashboard_name = 'Teacher Dashboard';
                }
                elseif($_SESSION["user_type"] === 'admin') {
                    $redirect_page = 'admin_dashboard.php';
                    $dashboard_name = 'Admin Dashboard';
                }
            }
            ?>
            
            <a href="<?php echo $redirect_page; ?>" class="btn btn-primary btn-back">
                <i class="fas fa-arrow-left me-2"></i>No, Return to <?php echo $dashboard_name; ?>
            </a>
        </div>
    </div>
</body>
</html>