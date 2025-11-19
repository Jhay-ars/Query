<?php
// Include database configuration
require_once 'config.php';

// Start session for admin authentication
session_start();

// Admin authentication check - FIXED to match admin_dashboard.php authentication method
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "admin") {
    // Redirect to login page with error message
    header("Location: index.php?error=unauthorized");
    exit;
}

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'No action specified',
    'redirect' => 'admin_dashboard.php'
];

// Process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the action
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    try {
        switch ($action) {
            case 'create':
                // Handle user creation
                $response = createUser($conn);
                break;
            
            case 'update':
                // Handle user update
                $response = updateUser($conn);
                break;
            
            default:
                $response['message'] = 'Invalid action specified';
                break;
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    // Redirect with message
    $status = $response['status'];
    $message = urlencode($response['message']);
    header("Location: {$response['redirect']}?$status=$message");
    exit();
}

/**
 * Create a new user
 * 
 * @param PDO $conn Database connection
 * @return array Response array with status and message
 */
function createUser($conn) {
    // Validate required fields
    $requiredFields = ['username', 'email', 'password', 'user_type', 'reset_question1', 'reset_answer1', 'reset_question2', 'reset_answer2'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            return [
                'status' => 'error',
                'message' => "Missing required field: $field",
                'redirect' => 'admin_dashboard.php'
            ];
        }
    }
    
    // Get form data and sanitize
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    $student_lrn = ($user_type === 'student') ? trim($_POST['student_lrn']) : null;
    $reset_question1 = trim($_POST['reset_question1']);
    $reset_answer1 = trim($_POST['reset_answer1']);
    $reset_question2 = trim($_POST['reset_question2']);
    $reset_answer2 = trim($_POST['reset_answer2']);
    
    // Additional validation
    if ($user_type === 'student' && empty($student_lrn)) {
        return [
            'status' => 'error',
            'message' => 'Student LRN is required for student users',
            'redirect' => 'admin_dashboard.php'
        ];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'status' => 'error',
            'message' => 'Invalid email format',
            'redirect' => 'admin_dashboard.php'
        ];
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        return [
            'status' => 'error',
            'message' => 'Username or email already exists',
            'redirect' => 'admin_dashboard.php'
        ];
    }
    
    // Check if student_lrn is already used (for student accounts)
    if ($user_type === 'student' && !empty($student_lrn)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE student_lrn = ?");
        $stmt->execute([$student_lrn]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'status' => 'error',
                'message' => 'LRN already assigned to another student',
                'redirect' => 'admin_dashboard.php'
            ];
        }
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, user_type, student_lrn, reset_question1, reset_answer1, reset_question2, reset_answer2)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $username,
            $email,
            $hashed_password,
            $user_type,
            $student_lrn,
            $reset_question1,
            $reset_answer1,
            $reset_question2,
            $reset_answer2
        ]);
        
        if ($result) {
            $conn->commit();
            return [
                'status' => 'success',
                'message' => 'User created successfully',
                'redirect' => 'admin_dashboard.php'
            ];
        } else {
            $conn->rollBack();
            return [
                'status' => 'error',
                'message' => 'Failed to create user',
                'redirect' => 'admin_dashboard.php'
            ];
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'redirect' => 'admin_dashboard.php'
        ];
    }
}

/**
 * Update an existing user
 * 
 * @param PDO $conn Database connection
 * @return array Response array with status and message
 */
function updateUser($conn) {
    // Validate required fields
    $requiredFields = ['id', 'username', 'email', 'user_type', 'reset_question1', 'reset_answer1', 'reset_question2', 'reset_answer2'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            return [
                'status' => 'error',
                'message' => "Missing required field: $field",
                'redirect' => 'admin_dashboard.php'
            ];
        }
    }
    
    // Get form data and sanitize
    $id = (int)$_POST['id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $user_type = $_POST['user_type'];
    $student_lrn = ($user_type === 'student') ? trim($_POST['student_lrn']) : null;
    $reset_question1 = trim($_POST['reset_question1']);
    $reset_answer1 = trim($_POST['reset_answer1']);
    $reset_question2 = trim($_POST['reset_question2']);
    $reset_answer2 = trim($_POST['reset_answer2']);
    
    // Additional validation
    if ($user_type === 'student' && empty($student_lrn)) {
        return [
            'status' => 'error',
            'message' => 'Student LRN is required for student users',
            'redirect' => 'admin_dashboard.php'
        ];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'status' => 'error',
            'message' => 'Invalid email format',
            'redirect' => 'admin_dashboard.php'
        ];
    }
    
    // Check if username or email already exists for other users
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $id]);
    if ($stmt->fetchColumn() > 0) {
        return [
            'status' => 'error',
            'message' => 'Username or email already exists for another user',
            'redirect' => 'admin_dashboard.php'
        ];
    }
    
    // Check if student_lrn is already used by another user (for student accounts)
    if ($user_type === 'student' && !empty($student_lrn)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE student_lrn = ? AND id != ?");
        $stmt->execute([$student_lrn, $id]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'status' => 'error',
                'message' => 'LRN already assigned to another student',
                'redirect' => 'admin_dashboard.php'
            ];
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Prepare SQL - conditionally include password update
        if (!empty($password)) {
            // Hash password if provided
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "
                UPDATE users SET 
                username = ?,
                email = ?,
                password = ?,
                user_type = ?,
                student_lrn = ?,
                reset_question1 = ?,
                reset_answer1 = ?,
                reset_question2 = ?,
                reset_answer2 = ?
                WHERE id = ?
            ";
            $params = [
                $username,
                $email,
                $hashed_password,
                $user_type,
                $student_lrn,
                $reset_question1,
                $reset_answer1,
                $reset_question2,
                $reset_answer2,
                $id
            ];
        } else {
            // Skip password update if not provided
            $sql = "
                UPDATE users SET 
                username = ?,
                email = ?,
                user_type = ?,
                student_lrn = ?,
                reset_question1 = ?,
                reset_answer1 = ?,
                reset_question2 = ?,
                reset_answer2 = ?
                WHERE id = ?
            ";
            $params = [
                $username,
                $email,
                $user_type,
                $student_lrn,
                $reset_question1,
                $reset_answer1,
                $reset_question2,
                $reset_answer2,
                $id
            ];
        }
        
        // Execute update
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $conn->commit();
            return [
                'status' => 'success',
                'message' => 'User updated successfully',
                'redirect' => 'admin_dashboard.php'
            ];
        } else {
            $conn->rollBack();
            return [
                'status' => 'error',
                'message' => 'Failed to update user',
                'redirect' => 'admin_dashboard.php'
            ];
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'redirect' => 'admin_dashboard.php'
        ];
    }
}