<?php
require_once "config.php";

$username = $email = "";
$username_err = $email_err = "";
$user_id = 0;
$step = 1; 

if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    if(isset($_POST["step"]) && is_numeric($_POST["step"])){
        $step = (int)$_POST["step"];
    }
    
    if($step == 1){
        // Check if username is empty
        if(empty(trim($_POST["username"]))){
            $username_err = "Please enter your username.";
        } else{
            $username = trim($_POST["username"]);
        }
        
        // Check if email is empty
        if(empty(trim($_POST["email"]))){
            $email_err = "Please enter your email.";
        } else{
            $email = trim($_POST["email"]);
        }
        
        // If no errors, check if the user exists
        if(empty($username_err) && empty($email_err)){
            // Prepare a select statement
            $sql = "SELECT id, reset_question1, reset_question2 FROM users WHERE username = :username AND email = :email";
            
            if($stmt = $conn->prepare($sql)){
                // Bind variables to the prepared statement as parameters
                $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
                $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
                
                // Set parameters
                $param_username = $username;
                $param_email = $email;
                
                // Attempt to execute the prepared statement
                if($stmt->execute()){
                    // Check if user exists
                    if($stmt->rowCount() == 1){
                        $row = $stmt->fetch();
                        $user_id = $row["id"];
                        $question1 = $row["reset_question1"];
                        $question2 = $row["reset_question2"];
                        
                        // Check if security questions have been set up
                        if(empty($question1) || $question1 == "1" || empty($question2) || $question2 == "2"){
                            // Security questions not set up - show error message
                            $setup_err = "You have not set up your security questions yet. Please contact your system administrator for assistance.";
                        } else {
                            // Move to step 2
                            $step = 2;
                        }
                    } else{
                        $username_err = "No account found with that username and email combination.";
                    }
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
                }
                
                // Close statement
                unset($stmt);
            }
        }
    }
    // Step 2: Validate security answers
    else if($step == 2){
        $user_id = isset($_POST["user_id"]) ? (int)$_POST["user_id"] : 0;
        $answer1 = isset($_POST["answer1"]) ? trim($_POST["answer1"]) : "";
        $answer2 = isset($_POST["answer2"]) ? trim($_POST["answer2"]) : "";
        $question1 = isset($_POST["question1"]) ? trim($_POST["question1"]) : "";
        $question2 = isset($_POST["question2"]) ? trim($_POST["question2"]) : "";
        
        $answer1_err = $answer2_err = "";
        
        // Validate answers
        if(empty($answer1)){
            $answer1_err = "Please enter your answer.";
        }
        
        if(empty($answer2)){
            $answer2_err = "Please enter your answer.";
        }
        
        if(empty($answer1_err) && empty($answer2_err) && $user_id > 0){
            // Prepare a select statement
            $sql = "SELECT reset_answer1, reset_answer2 FROM users WHERE id = :id";
            
            if($stmt = $conn->prepare($sql)){
                // Bind variables to the prepared statement as parameters
                $stmt->bindParam(":id", $param_id, PDO::PARAM_INT);
                
                // Set parameters
                $param_id = $user_id;
                
                // Attempt to execute the prepared statement
                if($stmt->execute()){
                    if($row = $stmt->fetch()){
                        // Check if answers match (case-insensitive comparison)
                        if(strtolower($answer1) == strtolower($row["reset_answer1"]) && 
                           strtolower($answer2) == strtolower($row["reset_answer2"])){
                            // Move to step 3
                            $step = 3;
                        } else{
                            $answer_err = "Incorrect answers to the security questions.";
                        }
                    }
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
                }
                
                // Close statement
                unset($stmt);
            }
        }
    }
    // Step 3: Update password
    else if($step == 3){
        $user_id = isset($_POST["user_id"]) ? (int)$_POST["user_id"] : 0;
        $new_password = isset($_POST["new_password"]) ? trim($_POST["new_password"]) : "";
        $confirm_password = isset($_POST["confirm_password"]) ? trim($_POST["confirm_password"]) : "";
        
        $password_err = $confirm_password_err = "";
        
        // Validate password
        if(empty($new_password)){
            $password_err = "Please enter a new password.";     
        } elseif(strlen($new_password) < 6){
            $password_err = "Password must have at least 6 characters.";
        }
        
        // Validate confirm password
        if(empty($confirm_password)){
            $confirm_password_err = "Please confirm the password.";     
        } else{
            if(empty($password_err) && ($new_password != $confirm_password)){
                $confirm_password_err = "Password did not match.";
            }
        }
        
        // Check input errors before updating the database
        if(empty($password_err) && empty($confirm_password_err) && $user_id > 0){
            // Prepare an update statement
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            
            if($stmt = $conn->prepare($sql)){
                // Bind variables to the prepared statement as parameters
                $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
                $stmt->bindParam(":id", $param_id, PDO::PARAM_INT);
                
                // Set parameters
                $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                $param_id = $user_id;
                
                // Attempt to execute the prepared statement
                if($stmt->execute()){
                    // Password updated successfully - we'll show the modal instead of redirecting immediately
                    // The redirect will happen after the countdown in the modal
                    $password_reset_success = true;
                    // We DON'T redirect here anymore - the modal handles that
                    // header("location: index.php");
                    // exit();
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
                }
                
                // Close statement
                unset($stmt);
            }
        }
    }
}

// If coming back to step 2, retrieve the questions again
if($step == 2 && empty($question1) && !empty($user_id)){
    $sql = "SELECT reset_question1, reset_question2 FROM users WHERE id = :id";
    if($stmt = $conn->prepare($sql)){
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        if($stmt->execute()){
            if($row = $stmt->fetch()){
                $question1 = $row["reset_question1"];
                $question2 = $row["reset_question2"];
            }
        }
        unset($stmt);
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
            width: 450px;
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
        
        .steps-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 15px;
            color: #6c757d;
            font-weight: 600;
            position: relative;
        }
        
        .step.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .step::before {
            content: '';
            position: absolute;
            left: -15px;
            right: -15px;
            height: 3px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #e9ecef;
            z-index: -1;
        }
        
        .step:first-child::before {
            left: 50%;
        }
        
        .step:last-child::before {
            right: 50%;
        }
        
        .step.active::before, .step.completed::before {
            background-color: var(--primary-color);
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 576px) {
            .wrapper {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="icon-section">
            <i class="fas fa-unlock-alt icon-lock"></i>
        </div>
        <h2>Forgot Password</h2>
        
        <div class="steps-indicator">
            <div class="step <?php echo ($step >= 1) ? 'active' : ''; ?> <?php echo ($step > 1) ? 'completed' : ''; ?>">1</div>
            <div class="step <?php echo ($step >= 2) ? 'active' : ''; ?> <?php echo ($step > 2) ? 'completed' : ''; ?>">2</div>
            <div class="step <?php echo ($step >= 3) ? 'active' : ''; ?>">3</div>
        </div>
        
        <?php if(isset($setup_err)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $setup_err; ?>
                <div class="mt-3">
                    <a class="btn btn-danger" href="index.php">
                        <i class="fas fa-arrow-left me-2"></i>Return to Login
                    </a>
                </div>
            </div>
        <?php else: ?>
        
            <?php if($step == 1): ?>
                <p>Enter your username and registered email to start the password recovery process.</p>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user me-2"></i>Username</label>
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>    
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope me-2"></i>Email</label>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>Continue
                        </button>
                        <a class="btn btn-link" href="index.php">
                            <i class="fas fa-arrow-left me-2"></i>Back to Login
                        </a>
                    </div>
                </form>
                
            <?php elseif($step == 2): ?>
                <p>Please answer your security questions to verify your identity.</p>
                
                <?php if(isset($answer_err)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $answer_err; ?>
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="question1" value="<?php echo htmlspecialchars($question1); ?>">
                    <input type="hidden" name="question2" value="<?php echo htmlspecialchars($question2); ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-question-circle me-2"></i>Question 1:</label>
                        <p class="form-control-static"><strong><?php echo htmlspecialchars($question1); ?></strong></p>
                        <input type="text" name="answer1" class="form-control <?php echo (!empty($answer1_err)) ? 'is-invalid' : ''; ?>">
                        <span class="invalid-feedback"><?php echo $answer1_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-question-circle me-2"></i>Question 2:</label>
                        <p class="form-control-static"><strong><?php echo htmlspecialchars($question2); ?></strong></p>
                        <input type="text" name="answer2" class="form-control <?php echo (!empty($answer2_err)) ? 'is-invalid' : ''; ?>">
                        <span class="invalid-feedback"><?php echo $answer2_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>Verify Answers
                        </button>
                        <a class="btn btn-link" href="forgot_password.php">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </form>
                
            <?php elseif($step == 3): ?>
                <p>Please create your new password.</p>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="step" value="3">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-key me-2"></i>New Password</label>
                        <div class="password-container">
                            <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                            <i class="fas fa-eye password-toggle" id="toggle-new-password"></i>
                        </div>
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                        <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-check-circle me-2"></i>Confirm Password</label>
                        <div class="password-container">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                            <i class="fas fa-eye password-toggle" id="toggle-confirm-password"></i>
                        </div>
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Reset Password
                        </button>
                        <a class="btn btn-link" href="forgot_password.php?step=2&user_id=<?php echo $user_id; ?>">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel"><i class="fas fa-check-circle me-2"></i>Password Reset Successful</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="py-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                        <h4 class="mt-3">Password Reset Complete!</h4>
                        <p class="mt-3">Your password has been successfully reset. You will now be redirected to the login page.</p>
                        <div class="mt-3 d-flex justify-content-center">
                            <div class="spinner-border text-primary me-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span>Redirecting in <span id="countdown">5</span> seconds...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>

    <script>
        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const toggleNewPassword = document.getElementById('toggle-new-password');
            const toggleConfirmPassword = document.getElementById('toggle-confirm-password');
            
            if(toggleNewPassword) {
                toggleNewPassword.addEventListener('click', function() {
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
            }
            
            if(toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', function() {
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
            }

            // Show success modal if step 3 form was successfully submitted
            <?php if($step == 3 && isset($_POST["new_password"]) && empty($password_err) && empty($confirm_password_err)): ?>
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Start countdown for redirect
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
            <?php endif; ?>
        });
    </script>
</body>
</html>