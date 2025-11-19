<?php
// Include database connection
require_once "config.php";

// Initialize variables
$username = $email = $password = $confirm_password = $student_id = "";
$reset_question1 = $reset_answer1 = $reset_question2 = $reset_answer2 = "";
$username_err = $email_err = $password_err = $confirm_password_err = $student_id_err = "";
$reset_question1_err = $reset_answer1_err = $reset_question2_err = $reset_answer2_err = "";

// Check if registration token is provided
$invited = false;
$invitation_id = 0;
$student_name = "";

if(isset($_GET["token"]) && !empty($_GET["token"])) {
    $token = $_GET["token"];
    
    // Verify the token
    $sql = "SELECT it.*, s.name, s.student_id FROM invitation_tokens it 
            JOIN students s ON it.student_id = s.id 
            WHERE it.token = :token AND it.used = 0 AND it.expires_at > NOW()";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $invited = true;
            $invitation = $stmt->fetch();
            $student_id = $invitation["student_id"];
            $student_name = $invitation["name"];
            $invitation_id = $invitation["id"];
            $email = $invitation["email"];
            
            // Pre-fill the username field with the student ID
            $username = $student_id;
        }
    }
}

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // If this is an invited student, ensure they use their assigned student ID
        if($invited && trim($_POST["username"]) != $student_id) {
            $username_err = "You must use your assigned Student ID as your username.";
        } else {
            // Prepare a select statement
            $sql = "SELECT id FROM users WHERE username = :username";
            
            if($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
                
                // Set parameters
                $param_username = trim($_POST["username"]);
                
                // Attempt to execute the prepared statement
                if($stmt->execute()) {
                    if($stmt->rowCount() > 0) {
                        $username_err = "This username is already taken.";
                    } else {
                        $username = trim($_POST["username"]);
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                unset($stmt);
            }
        }
    }
    
    // Validate student ID/LRN for student accounts
    if(strpos(trim($_POST["username"]), "Teacher") !== 0) { // Not a teacher
        if(!$invited && empty(trim($_POST["student_id"]))) {
            $student_id_err = "Please enter your LRN/Student ID.";
        } else {
            if(!$invited) {
                $student_id = trim($_POST["student_id"]);
            }
        }
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = :email";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            
            // Set parameters
            $param_email = trim($_POST["email"]);
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                if($stmt->rowCount() > 0) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            unset($stmt);
        }
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate security questions and answers
    if(empty(trim($_POST["reset_question1"]))) {
        $reset_question1_err = "Please enter your first security question.";
    } else {
        $reset_question1 = trim($_POST["reset_question1"]);
    }
    
    if(empty(trim($_POST["reset_answer1"]))) {
        $reset_answer1_err = "Please enter your answer to the first security question.";
    } else {
        $reset_answer1 = trim($_POST["reset_answer1"]);
    }
    
    if(empty(trim($_POST["reset_question2"]))) {
        $reset_question2_err = "Please enter your second security question.";
    } else {
        $reset_question2 = trim($_POST["reset_question2"]);
    }
    
    if(empty(trim($_POST["reset_answer2"]))) {
        $reset_answer2_err = "Please enter your answer to the second security question.";
    } else {
        $reset_answer2 = trim($_POST["reset_answer2"]);
    }
    
    // Check input errors before inserting in database
    if(empty($username_err) && empty($email_err) && empty($password_err) && 
        empty($confirm_password_err) && empty($reset_question1_err) && 
        empty($reset_answer1_err) && empty($reset_question2_err) && 
        empty($reset_answer2_err) && (empty($student_id_err) || strpos($username, "Teacher") === 0)) {
        
        // Determine if user is a teacher based on username prefix or invitation
        if($invited) {
            $user_type = "student"; // Invited users are always students
            $student_lrn_value = $student_lrn; // Use the provided student LRN from invitation
        } else {
            $user_type = (strpos($username, "Teacher") === 0) ? "teacher" : "student";
            $student_lrn_value = ($user_type === "student") ? $student_id : null; // Only set for students
        }
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, email, password, user_type, reset_question1, reset_answer1, reset_question2, reset_answer2, student_lrn) 
                VALUES (:username, :email, :password, :user_type, :reset_question1, :reset_answer1, :reset_question2, :reset_answer2, :student_lrn)";
         
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
            $stmt->bindParam(":user_type", $param_user_type, PDO::PARAM_STR);
            $stmt->bindParam(":reset_question1", $param_reset_question1, PDO::PARAM_STR);
            $stmt->bindParam(":reset_answer1", $param_reset_answer1, PDO::PARAM_STR);
            $stmt->bindParam(":reset_question2", $param_reset_question2, PDO::PARAM_STR);
            $stmt->bindParam(":reset_answer2", $param_reset_answer2, PDO::PARAM_STR);
            $stmt->bindParam(":student_lrn", $param_student_lrn, PDO::PARAM_STR);
            
            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_user_type = $user_type;
            $param_reset_question1 = $reset_question1;
            $param_reset_answer1 = $reset_answer1;
            $param_reset_question2 = $reset_question2;
            $param_reset_answer2 = $reset_answer2;
            $param_student_lrn = $student_lrn_value;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // If this is an invited student, mark the invitation as used
                if($invited && isset($invitation_id)) {
                    $sql = "UPDATE invitation_tokens SET used = 1 WHERE id = :id";
                    if($stmt = $conn->prepare($sql)) {
                        $stmt->bindParam(":id", $invitation_id, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                }
                // Redirect to login page
                header("location: index.php");
                exit();
            } else {
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
    <title>Sign Up</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font: 14px sans-serif;
        background-image: url('images/1.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        }
        .wrapper {
            width: 100%;
            max-width: 500px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title text-center">Sign Up</h2>

                <?php if($invited): ?>
                    <div class="alert alert-success">
                        <p>Welcome, <strong><?= htmlspecialchars($student_name) ?></strong>!</p>
                        <p>You've been invited to create an account. Please complete the registration form below.</p>
                    </div>
                <?php else: ?>
                    <p class="text-center">Please fill this form to create an account.</p>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?><?= $invited ? '?token='.urlencode($_GET["token"]) : '' ?>" method="post">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?= $username; ?>" <?= $invited ? 'readonly' : '' ?>>
                        <span class="invalid-feedback"><?= $username_err; ?></span>
                        <?php if(!$invited): ?>
                            <small class="form-text text-muted">Note: Start your username with "Teacher" if you are registering as a teacher.</small>
                        <?php endif; ?>
                    </div>

                    <?php if(strpos($username, "Teacher") !== 0 && !$invited): ?>
                        <div class="form-group">
                            <label>LRN/Student ID</label>
                            <input type="text" name="student_id" class="form-control <?= (!empty($student_id_err)) ? 'is-invalid' : ''; ?>" value="<?= $student_id; ?>">
                            <span class="invalid-feedback"><?= $student_id_err; ?></span>
                            <small class="form-text text-muted">Enter your official Learner Reference Number or Student ID.</small>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?= $email; ?>" <?= $invited ? 'readonly' : '' ?>>
                        <span class="invalid-feedback"><?= $email_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?= $password; ?>">
                        <span class="invalid-feedback"><?= $password_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control <?= (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?= $confirm_password; ?>">
                        <span class="invalid-feedback"><?= $confirm_password_err; ?></span>
                    </div>

                    <hr>
                    <h5>Security Questions</h5>
                    <p class="text-muted">These questions will help reset your password if needed.</p>

                    <div class="form-group">
                        <label>Security Question 1</label>
                        <input type="text" name="reset_question1" class="form-control <?= (!empty($reset_question1_err)) ? 'is-invalid' : ''; ?>" value="<?= $reset_question1; ?>">
                        <span class="invalid-feedback"><?= $reset_question1_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label>Answer 1</label>
                        <input type="text" name="reset_answer1" class="form-control <?= (!empty($reset_answer1_err)) ? 'is-invalid' : ''; ?>" value="<?= $reset_answer1; ?>">
                        <span class="invalid-feedback"><?= $reset_answer1_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label>Security Question 2</label>
                        <input type="text" name="reset_question2" class="form-control <?= (!empty($reset_question2_err)) ? 'is-invalid' : ''; ?>" value="<?= $reset_question2; ?>">
                        <span class="invalid-feedback"><?= $reset_question2_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label>Answer 2</label>
                        <input type="text" name="reset_answer2" class="form-control <?= (!empty($reset_answer2_err)) ? 'is-invalid' : ''; ?>" value="<?= $reset_answer2; ?>">
                        <span class="invalid-feedback"><?= $reset_answer2_err; ?></span>
                    </div>

                    <?php if($invited): ?>
                        <input type="hidden" name="invitation_id" value="<?= $invitation_id ?>">
                    <?php endif; ?>

                    <div class="form-group text-center">
                        <button type="submit" class="btn btn-primary">Submit</button>
                        <button type="reset" class="btn btn-secondary ml-2">Reset</button>
                    </div>
                    <p class="text-center">Already have an account? <a href="index.php">Login here</a>.</p>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (optional, for dropdowns, modals, etc.) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
