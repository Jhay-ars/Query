<?php
require_once 'config.php';


ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session for admin authentication
session_start();

// Admin authentication check
if (!isset($_SESSION['loggedin']) || $_SESSION['user_type'] !== 'admin') {
    // Redirect to login page with error message
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';
$success_count = 0;
$error_count = 0;
$error_records = [];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"])) {
    // Validate file upload
    if ($_FILES["csv_file"]["error"] > 0) {
        $error = "Error: " . $_FILES["csv_file"]["error"];
    } else {
        // Check file extension
        $file_ext = strtolower(pathinfo($_FILES["csv_file"]["name"], PATHINFO_EXTENSION));
        if ($file_ext != "csv") {
            $error = "Only CSV files are allowed.";
        } else {
            // Process CSV file
            $file = $_FILES["csv_file"]["tmp_name"];
            
            // Open uploaded CSV file
            if (($handle = fopen($file, "r")) !== FALSE) {
                // Read the first row as header
                $header = fgetcsv($handle);
                
                // Normalize header names (trim whitespace, lowercase)
                $header = array_map(function($item) {
                    return strtolower(trim($item));
                }, $header);
                
                // Check required columns
                $required_columns = ['username', 'password', 'email', 'user_type', 'reset_question1', 'reset_answer1', 'reset_question2', 'reset_answer2'];
                $missing_columns = array_diff($required_columns, $header);
                
                if (!empty($missing_columns)) {
                    $error = "Missing required columns: " . implode(", ", $missing_columns);
                } else {
                    // Map CSV columns to database columns
                    $column_map = array_flip($header);
                    
                    // Begin transaction
                    $conn->beginTransaction();
                    
                    // Prepare statement
                    $stmt = $conn->prepare("INSERT INTO users 
                        (username, password, email, user_type, created_at, reset_question1, reset_answer1, reset_question2, reset_answer2, student_lrn) 
                        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)");
                    
                    // Process each row
                    $line_number = 2; // Start at 2 because line 1 is the header
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        try {
                            // Check if we have enough columns in this row
                            if (count($data) < count($required_columns)) {
                                $error_records[] = "Line $line_number: Not enough columns";
                                $error_count++;
                                $line_number++;
                                continue;
                            }
                            
                            // Extract data from CSV
                            $username = trim($data[$column_map['username']]);
                            $password = trim($data[$column_map['password']]);
                            $email = trim($data[$column_map['email']]);
                            $user_type = strtolower(trim($data[$column_map['user_type']]));
                            $reset_q1 = trim($data[$column_map['reset_question1']]);
                            $reset_a1 = trim($data[$column_map['reset_answer1']]);
                            $reset_q2 = trim($data[$column_map['reset_question2']]);
                            $reset_a2 = trim($data[$column_map['reset_answer2']]);
                            
                            // Get student_lrn if it exists in the CSV
                            $student_lrn = null;
                            if (isset($column_map['student_lrn']) && isset($data[$column_map['student_lrn']])) {
                                $student_lrn = trim($data[$column_map['student_lrn']]);
                                if (empty($student_lrn)) {
                                    $student_lrn = null;
                                }
                            }
                            
                            // Basic validation
                            if (empty($username) || empty($password) || empty($email)) {
                                $error_records[] = "Line $line_number: Username, password, and email are required";
                                $error_count++;
                                $line_number++;
                                continue;
                            }
                            
                            // Validate email format
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $error_records[] = "Line $line_number: Invalid email format for $email";
                                $error_count++;
                                $line_number++;
                                continue;
                            }
                            
                            // Validate user_type
                            if ($user_type != 'student' && $user_type != 'teacher') {
                                $error_records[] = "Line $line_number: User type must be 'student' or 'teacher'";
                                $error_count++;
                                $line_number++;
                                continue;
                            }
                            
                            // Check if student_lrn is provided for student accounts
                            if ($user_type == 'student' && empty($student_lrn)) {
                                $error_records[] = "Line $line_number: Student LRN is required for student accounts";
                                $error_count++;
                                $line_number++;
                                continue;
                            }
                            
                            // Check if username or email already exists
                            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                            $check_stmt->execute([$username, $email]);
                            if ($check_stmt->rowCount() > 0) {
                                $error_records[] = "Line $line_number: Username or email already exists";
                                $error_count++;
                                $line_number++;
                                continue;
                            }
                            
                            // Hash password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Execute insert
                            $stmt->execute([
                                $username,
                                $hashed_password,
                                $email,
                                $user_type,
                                $reset_q1,
                                $reset_a1,
                                $reset_q2,
                                $reset_a2,
                                $student_lrn
                            ]);
                            
                            $success_count++;
                            
                        } catch (Exception $e) {
                            $error_records[] = "Line $line_number: " . $e->getMessage();
                            $error_count++;
                        }
                        
                        $line_number++;
                    }
                    
                    // Commit transaction if there were successful imports
                    if ($success_count > 0) {
                        $conn->commit();
                        $message .= "$success_count records imported successfully. ";
                    } else {
                        $conn->rollBack();
                    }
                    
                    // Add error summary if there were errors
                    if ($error_count > 0) {
                        $message .= "$error_count records failed. ";
                    }
                    
                    // Close file
                    fclose($handle);
                }
            } else {
                $error = "Could not open file.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Users from CSV</title>
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
            margin-bottom: 20px;
        }
        .card-body {
            background-color: #fafbfc; /* Very subtle off-white */
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .container-fluid {
            padding: 20px;
        }
        .page-content {
            margin-top: 25px; /* Space between navbar and content */
        }
        .error-list {
            max-height: 200px;
            overflow-y: auto;
            background-color: #f8f9fa;
            padding: 10px;
            border: 1px solid #ddd;
            margin-top: 10px;
            border-radius: 4px;
        }
        .table-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        .template-link {
            margin-left: 10px;
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
        <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
            <img src="images/logo.png" class="navbar-logo me-2" alt="Joaquin Smith National High School Logo">
            School Portal Admin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="admin_dashboard.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="csv_import_users.php">
                        <i class="fas fa-file-import"></i> Import Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reset_password.php">
                        <i class="fas fa-key"></i> Reset Password
                    </a>
                </li>
            </ul>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION["username"]); ?>
                </span>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

    <div class="container-fluid py-4">
        <div class="card shadow-sm page-content">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-import"></i> Import Users from CSV</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_records)): ?>
                    <div class="error-list">
                        <h5><i class="fas fa-exclamation-circle text-danger"></i> Error Details:</h5>
                        <ul class="mb-0">
                            <?php foreach ($error_records as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="mt-4">
                    <div class="row align-items-end mb-4">
                        <div class="col-md-8">
                            <label for="csv_file" class="form-label">Select CSV File:</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload"></i> Import Users
                                </button>
                                <a href="download_template.php" class="btn btn-outline-primary template-link">
                                    <i class="fas fa-download"></i> Download Template
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-container p-3">
                    <h4 class="mb-3"><i class="fas fa-info-circle text-primary"></i> CSV Format Requirements</h4>
                    <p><strong>Note:</strong> Only use the given template. Otherwise, the input will not be processed correctly.</p>

                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Column Name</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>username</td><td>Yes</td><td>Unique username for login</td></tr>
                            <tr><td>password</td><td>Yes</td><td>Password (will be hashed)</td></tr>
                            <tr><td>email</td><td>Yes</td><td>Valid email address (must be unique)</td></tr>
                            <tr><td>user_type</td><td>Yes</td><td>Either "student" or "teacher"</td></tr>
                            <tr><td>reset_question1</td><td>Yes</td><td>Security question 1</td></tr>
                            <tr><td>reset_answer1</td><td>Yes</td><td>Answer to security question 1</td></tr>
                            <tr><td>reset_question2</td><td>Yes</td><td>Security question 2</td></tr>
                            <tr><td>reset_answer2</td><td>Yes</td><td>Answer to security question 2</td></tr>
                            <tr><td>student_lrn</td><td>Only for students</td><td>Student's LRN number</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>