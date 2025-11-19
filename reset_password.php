<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
 
require_once "config.php";

// Check if this is the user's first time changing password
$first_time_reset = false;
$sql = "SELECT reset_question1, reset_answer1 FROM users WHERE id = :id";
if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":id", $_SESSION["id"], PDO::PARAM_INT);
    if($stmt->execute()) {
        if($row = $stmt->fetch()) {
            // If both security questions/answers are empty or the default values, it's first time
            if(empty($row["reset_question1"]) || $row["reset_question1"] == "1") {
                $first_time_reset = true;
            }
        }
    }
    unset($stmt);
}

// Define available security questions
$security_questions = [
    "What was your first pet's name?",
    "In what city were you born?",
    "What is your mother's maiden name?",
    "What high school did you attend?",
    "What was your childhood nickname?",
    "What is your favorite movie?",
    "What is your favorite color?",
    "What was the make of your first car?",
    "What is your favorite food?",
    "What was the name of your first teacher?"
];
 
$new_password = $confirm_password = "";
$reset_question1 = $reset_answer1 = $reset_question2 = $reset_answer2 = "";
$new_password_err = $confirm_password_err = "";
$reset_question1_err = $reset_answer1_err = $reset_question2_err = $reset_answer2_err = "";
 
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate password
    if(empty(trim($_POST["new_password"]))){
        $new_password_err = "Please enter the new password.";     
    } elseif(strlen(trim($_POST["new_password"])) < 6){
        $new_password_err = "Password must have at least 6 characters.";
    } else{
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm the password.";
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($new_password_err) && ($new_password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate security questions if it's the first time reset
    if($first_time_reset) {
        // Validate first security question
        if(empty(trim($_POST["reset_question1"])) || $_POST["reset_question1"] == "0"){
            $reset_question1_err = "Please select a security question.";
        } else {
            $reset_question1 = trim($_POST["reset_question1"]);
        }
        
        // Validate first answer
        if(empty(trim($_POST["reset_answer1"]))){
            $reset_answer1_err = "Please provide an answer.";
        } else {
            $reset_answer1 = trim($_POST["reset_answer1"]);
        }
        
        // Validate second security question
        if(empty(trim($_POST["reset_question2"])) || $_POST["reset_question2"] == "0"){
            $reset_question2_err = "Please select a security question.";
        } elseif($_POST["reset_question1"] == $_POST["reset_question2"]) {
            $reset_question2_err = "Please select a different question than the first one.";
        } else {
            $reset_question2 = trim($_POST["reset_question2"]);
        }
        
        // Validate second answer
        if(empty(trim($_POST["reset_answer2"]))){
            $reset_answer2_err = "Please provide an answer.";
        } else {
            $reset_answer2 = trim($_POST["reset_answer2"]);
        }
    }
        
    // Check input errors before updating the database
    $all_security_valid = !$first_time_reset || 
                         (empty($reset_question1_err) && empty($reset_answer1_err) && 
                          empty($reset_question2_err) && empty($reset_answer2_err));
                         
    if(empty($new_password_err) && empty($confirm_password_err) && $all_security_valid){
        // Prepare an update statement
        if($first_time_reset) {
            $sql = "UPDATE users SET password = :password, reset_question1 = :reset_question1, 
                    reset_answer1 = :reset_answer1, reset_question2 = :reset_question2, 
                    reset_answer2 = :reset_answer2 WHERE id = :id";
        } else {
            $sql = "UPDATE users SET password = :password WHERE id = :id";
        }
        
        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
            $stmt->bindParam(":id", $param_id, PDO::PARAM_INT);
            
            // Bind security question parameters if it's first time reset
            if($first_time_reset) {
                $stmt->bindParam(":reset_question1", $reset_question1, PDO::PARAM_STR);
                $stmt->bindParam(":reset_answer1", $reset_answer1, PDO::PARAM_STR);
                $stmt->bindParam(":reset_question2", $reset_question2, PDO::PARAM_STR);
                $stmt->bindParam(":reset_answer2", $reset_answer2, PDO::PARAM_STR);
            }
            
            // Set parameters
            $param_password = password_hash($new_password, PASSWORD_DEFAULT);
            $param_id = $_SESSION["id"];
            
            // Attempt to execute the prepared statement
            if($stmt->execute()){
                // Password updated successfully. Destroy the session, and redirect to index page
                session_destroy();
                header("location: index.php");
                exit();
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            unset($stmt);
        }
    }
    
    // Close connection
    unset($conn);
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
        
        .wrapper {
            width: 500px;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .wrapper h2 {
            font-size: 28px;
            color: var(--dark-color);
            margin-bottom: 10px;
            font-weight: 600;
            text-align: center;
        }
        
        .wrapper p {
            color: #6c757d;
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger-color);
        }
        
        .invalid-feedback {
            font-size: 80%;
            color: var(--danger-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #3a5ccc;
            border-color: #3a5ccc;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }
        
        .btn-link {
            color: var(--primary-color);
            width: 100%;
            text-align: center;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-link:hover {
            background-color: #f1f5ff;
            color: #3a5ccc;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }

        .icon-section {
            text-align: center;
            margin-bottom: 20px;
        }

        .icon-lock {
            font-size: 48px;
            background: linear-gradient(135deg, #4e73df 0%, #1cc88a 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        label {
            font-weight: 500;
            color: #495057;
        }
        
        .security-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .security-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        .security-note {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #ffc107;
        }

        /* Modal styles */
        .modal-header {
            background: linear-gradient(135deg, #4e73df 0%, #1cc88a 100%);
            color: white;
            border-bottom: none;
        }
        
        .modal-content {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-footer {
            border-top: none;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .btn-confirm {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-confirm:hover {
            background-color: #18aa7a;
            border-color: #18aa7a;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-icon {
            font-size: 48px;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        @media (max-width: 576px) {
            .wrapper {
                width: 90%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="icon-section">
            <i class="fas fa-lock icon-lock"></i>
        </div>
        <h2>Reset Password</h2>
        <p>Please fill out this form to reset your password.</p>
        
        <form id="resetPasswordForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post"> 
            <div class="form-group">
                <label><i class="fas fa-key me-2"></i>New Password</label>
                <div class="password-container">
                    <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $new_password; ?>">
                    <i class="fas fa-eye password-toggle" id="toggle-new-password"></i>
                </div>
                <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-check-circle me-2"></i>Confirm Password</label>
                <div class="password-container">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-eye password-toggle" id="toggle-confirm-password"></i>
                </div>
                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
            </div>
            
            <?php if($first_time_reset): ?>
            <div class="security-section">
                <h3><i class="fas fa-shield-alt me-2"></i>Security Questions</h3>
                <div class="security-note">
                    <i class="fas fa-info-circle me-2"></i>For account recovery purposes, please set up security questions. You'll only need to do this once.
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-question-circle me-2"></i>Security Question 1</label>
                    <select name="reset_question1" class="form-control <?php echo (!empty($reset_question1_err)) ? 'is-invalid' : ''; ?>">
                        <option value="0">Select a security question</option>
                        <?php foreach($security_questions as $question): ?>
                            <option value="<?php echo htmlspecialchars($question); ?>" <?php echo ($reset_question1 == $question) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($question); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $reset_question1_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-reply me-2"></i>Answer 1</label>
                    <input type="text" name="reset_answer1" class="form-control <?php echo (!empty($reset_answer1_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $reset_answer1; ?>">
                    <span class="invalid-feedback"><?php echo $reset_answer1_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-question-circle me-2"></i>Security Question 2</label>
                    <select name="reset_question2" class="form-control <?php echo (!empty($reset_question2_err)) ? 'is-invalid' : ''; ?>">
                        <option value="0">Select a security question</option>
                        <?php foreach($security_questions as $question): ?>
                            <option value="<?php echo htmlspecialchars($question); ?>" <?php echo ($reset_question2 == $question) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($question); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $reset_question2_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-reply me-2"></i>Answer 2</label>
                    <input type="text" name="reset_answer2" class="form-control <?php echo (!empty($reset_answer2_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $reset_answer2; ?>">
                    <span class="invalid-feedback"><?php echo $reset_answer2_err; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
           <div class="form-group">
                <button type="button" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Update Password <?php echo $first_time_reset ? 'and Security Questions' : ''; ?>
                </button>
                
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
                
                <a class="btn btn-link" href="<?php echo $redirect_page; ?>">
                    <i class="fas fa-arrow-left me-2"></i>Return to <?php echo $dashboard_name; ?>
                </a>
            </div>
        </form>
    </div>
    
    <!-- Confirmation Modal -->
     <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel"><i class="fas fa-exclamation-circle me-2"></i>Confirm Password Reset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="modal-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <p>Are you sure you want to update your password<?php echo $first_time_reset ? ' and security questions' : ''; ?>?</p>
                    <p class="text-warning"><strong>Note:</strong> You will be logged out immediately and redirected to the login page.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmReset" class="btn btn-confirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal - ADD THIS SECTION -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel"><i class="fas fa-check-circle me-2"></i>Password Updated Successfully</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="py-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                        <h4 class="mt-3">Password Changed!</h4>
                        <p class="mt-3">Your password has been updated successfully. You will now be logged out and redirected to the login page.</p>
                        <div class="mt-3 d-flex justify-content-center">
                            <div class="spinner-border text-primary me-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span>Logging out in <span id="countdown">5</span> seconds...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End of Success Modal -->
    
    <!-- Include Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('toggle-new-password').addEventListener('click', function() {
            const passwordField = document.getElementById('new_password');
            const fieldType = passwordField.getAttribute('type');
            
            if (fieldType === 'password') {
                passwordField.setAttribute('type', 'text');
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordField.setAttribute('type', 'password');
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
        
        document.getElementById('toggle-confirm-password').addEventListener('click', function() {
            const passwordField = document.getElementById('confirm_password');
            const fieldType = passwordField.getAttribute('type');
            
            if (fieldType === 'password') {
                passwordField.setAttribute('type', 'text');
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordField.setAttribute('type', 'password');
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
        
        <?php if($first_time_reset): ?>
        // Prevent selecting the same security question twice
        document.querySelector('select[name="reset_question1"]').addEventListener('change', function() {
            const question1 = this.value;
            const question2Select = document.querySelector('select[name="reset_question2"]');
            
            Array.from(question2Select.options).forEach(option => {
                if (option.value === question1 && option.value !== '0') {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        });
        
        document.querySelector('select[name="reset_question2"]').addEventListener('change', function() {
            const question2 = this.value;
            const question1Select = document.querySelector('select[name="reset_question1"]');
            
            Array.from(question1Select.options).forEach(option => {
                if (option.value === question2 && option.value !== '0') {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        });
        <?php endif; ?>
        
        // Modal confirmation logic
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('resetPasswordForm');
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const confirmResetBtn = document.getElementById('confirmReset');
        
        submitBtn.addEventListener('click', function() {
            // Check form validity before showing modal
            if (form.checkValidity()) {
                confirmModal.show();
            } else {
                // Trigger HTML5 validation
                form.reportValidity();
            }
        });
        
        confirmResetBtn.addEventListener('click', function() {
            // Submit the form if confirmed
            form.submit();
        });
        
        // Success modal logic - ADD THIS SECTION
        <?php if(isset($_POST["new_password"]) && empty($new_password_err) && empty($confirm_password_err) && $all_security_valid): ?>
            // Show success modal after form submission if there are no errors
            document.addEventListener('DOMContentLoaded', function() {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Start countdown for logout
                let countdown = 5;
                const countdownElement = document.getElementById('countdown');
                
                const timer = setInterval(function() {
                    countdown--;
                    countdownElement.textContent = countdown;
                    
                    if (countdown <= 0) {
                        clearInterval(timer);
                        window.location.href = "index.php"; // Redirect to login page
                    }
                }, 1000);
            });
        <?php endif; ?>
        // End of success modal logic
    </script>
</body>
</html>