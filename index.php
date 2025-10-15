<?php
// Initialize the session
session_start();
 
// Check if the user is already logged in, if yes then redirect to appropriate page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    if($_SESSION["user_type"] === "teacher"){
        header("location: teacher_page.php");
    }if($_SESSION["user_type"] === "admin"){
        header("location: admin_dashboard.php");
    }else{
        header("location: student_page.php");
    }
    exit;
}
 
// Include database connection
require_once "config.php";
 
// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";
 
// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // Check if username is empty
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        // Prepare a select statement
        $sql = "SELECT id, username, password, user_type FROM users WHERE username = :username";
        
        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if($stmt->execute()){
                // Check if username exists, if yes then verify password
                if($stmt->rowCount() == 1){
                    if($row = $stmt->fetch()){
                        $id = $row["id"];
                        $username = $row["username"];
                        $hashed_password = $row["password"];
                        $user_type = $row["user_type"];
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["user_type"] = $user_type;

                            $_SESSION['login_success'] = "Welcome, " . $username . "!";

                            // Redirect user to appropriate page based on user type
                            if($user_type === "teacher"){
                                header("location: teacher_page.php");
                            } else {
                                header("location: student_page.php");
                            }
                            exit;
                        }
                        else{
                            $login_err = "Invalid username or password.";
                        }
                    }
                }
                else{
                    $login_err = "Invalid username or password.";
                }
            }
            else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            unset($stmt);
        }
    }
    unset($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Joaquin Smith National High School</title>

    <!-- Bootstrap CSS -->
     <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font: 14px sans-serif;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
             background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .login-wrapper {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
        }

        .school-logo {
            max-width: 150px;
            margin: 0 auto 20px;
            display: block;
        }

        .toast-container {
            position: fixed;
            top: 20px;
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
    </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<body>
    <div class="login-wrapper">
        <!-- School Logo -->
        <img src="images/logo.png" alt="Joaquin Smith National High School Logo" class="school-logo">
        
        <h2 class="text-center">Login</h2>
        <p class="text-center">Please enter your credentials to login.</p>

        <?php 
        if (!empty($login_err)) {
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        } 
        ?>

        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
            <?php if(isset($_SESSION['signup_success'])): ?>
            <div class="toast custom-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
                <div class="toast-header">
                    <i class="fas fa-check-circle toast-icon text-success me-2"></i>
                    <strong class="me-auto">Success</strong>
                    <small>Just now</small>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?= $_SESSION['signup_success'] ?>
                </div>
            </div>
            <?php unset($_SESSION['signup_success']); ?>
            <?php endif; ?>
        </div>


        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username"
                       class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>"
                       value="<?php echo $username; ?>">
                <span class="invalid-feedback"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                       class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group text-center">
                <input type="submit" class="btn btn-primary btn-block" value="Login">
            </div>
            <p class="text-center">Don't have an account? Tell your Teacher.</p>
            <p class="text-center">Forgot password? <a href="forgot_password.php">Reset it here</a>.</p>
        </form>
    </div>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const toastEl = document.querySelector('.toast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    </script>
</body>
</html>