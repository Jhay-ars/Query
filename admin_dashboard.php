<?php

require_once 'config.php';

session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

if($_SESSION["user_type"] !== "admin") {
    header("location: index.php");
    exit;
}

$users = [];
$message = '';
$error = '';
$search = '';
$filter_type = 'all';
$page = 1;
$per_page = 10;

// Get success message if exists
if (isset($_GET['success'])) {
    $message = urldecode($_GET['success']);
}

// Get error message if exists
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}
if (isset($_GET['filter_type']) && in_array($_GET['filter_type'], ['all', 'student', 'teacher'])) {
    $filter_type = $_GET['filter_type'];
}
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = (int)$_GET['page'];
    if ($page < 1) $page = 1;
}

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            $conn->commit();
            $message = "User deleted successfully.";
        } else {
            $conn->rollBack();
            $error = "User not found or could not be deleted.";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle password reset
if (isset($_GET['reset_password']) && is_numeric($_GET['reset_password'])) {
    $user_id = (int)$_GET['reset_password'];
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Generate a temporary password
        $temp_password = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Reset password and security questions
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_question1 = '1', reset_answer1 = '1', reset_question2 = '2', reset_answer2 = '2' WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $conn->commit();
            $message = "Password has been reset successfully. Temporary password: " . $temp_password;
        } else {
            $conn->rollBack();
            $error = "User not found or password could not be reset.";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Build the SQL query with filters
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR student_lrn LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($filter_type != 'all') {
    $sql .= " AND user_type = ?";
    $params[] = $filter_type;
}

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM ($sql) as count_table");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Add pagination to the SQL query
$offset = ($page - 1) * $per_page;
$sql .= " ORDER BY id DESC LIMIT $per_page OFFSET $offset";

// Execute the final query
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - User Management</title>
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
            margin-top: 25px; /* Increased space between navbar and content */
        }
        .table {
            background-color: #fff;
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f8f9fa;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .user-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .user-type.student {
            background-color: #cce5ff;
            color: #004085;
        }
        .user-type.teacher {
            background-color: #d4edda;
            color: #155724;
        }
        .user-type.admin {
            background-color: #f8d7da;
            color: #721c24;
        }
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .temp-password {
            font-family: monospace;
            font-weight: bold;
            background-color: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
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
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users"></i> User Management</h5>
                <div>
                    <button id="addUserBtn" class="btn btn-success btn-sm">
                        <i class="fas fa-user-plus"></i> Add New User
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if(!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> 
                        <?php 
                        // Check if message contains temporary password
                        if(strpos($message, "Temporary password:") !== false) {
                            $parts = explode("Temporary password:", $message);
                            echo $parts[0] . " Temporary password: <span class='temp-password'>" . trim($parts[1]) . "</span>";
                        } else {
                            echo htmlspecialchars($message);
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="search-container">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by username, email or LRN..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="filter_type" class="form-select">
                                <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="student" <?php echo $filter_type == 'student' ? 'selected' : ''; ?>>Students</option>
                                <option value="teacher" <?php echo $filter_type == 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <?php if(!empty($search) || $filter_type != 'all'): ?>
                                <a href="admin_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Student LRN</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="user-type <?php echo htmlspecialchars($user['user_type']); ?>">
                                                <?php echo htmlspecialchars($user['user_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($user['student_lrn']) ? htmlspecialchars($user['student_lrn']) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-outline-secondary btn-sm edit-btn" data-id="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-usertype="<?php echo htmlspecialchars($user['user_type']); ?>"
                                                        data-lrn="<?php echo htmlspecialchars($user['student_lrn'] ?? ''); ?>"
                                                        data-q1="<?php echo htmlspecialchars($user['reset_question1']); ?>"
                                                        data-a1="<?php echo htmlspecialchars($user['reset_answer1']); ?>"
                                                        data-q2="<?php echo htmlspecialchars($user['reset_question2']); ?>"
                                                        data-a2="<?php echo htmlspecialchars($user['reset_answer2']); ?>">
                                                    <i class="fas fa-pen"></i> Edit
                                                </button>
                                                <button class="btn btn-outline-warning btn-sm reset-password-btn" data-id="<?php echo $user['id']; ?>" 
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <i class="fas fa-key"></i> Reset Password
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm delete-btn" data-id="<?php echo $user['id']; ?>" 
                                                   data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                   <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination">
                            <?php if($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&filter_type=<?php echo $filter_type; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo $filter_type; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo $filter_type; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo $filter_type; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo $filter_type; ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editForm" class="modal-content" action="admin_process.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_id" name="id" value="">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username:</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email:</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password (leave blank to keep current):</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_user_type" class="form-label">User Type:</label>
                        <select class="form-select" id="edit_user_type" name="user_type" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="lrn_group">
                        <label for="edit_student_lrn" class="form-label">Student LRN:</label>
                        <input type="text" class="form-control" id="edit_student_lrn" name="student_lrn">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_reset_question1" class="form-label">Security Question 1:</label>
                        <input type="text" class="form-control" id="edit_reset_question1" name="reset_question1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_reset_answer1" class="form-label">Answer 1:</label>
                        <input type="text" class="form-control" id="edit_reset_answer1" name="reset_answer1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_reset_question2" class="form-label">Security Question 2:</label>
                        <input type="text" class="form-control" id="edit_reset_question2" name="reset_question2" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_reset_answer2" class="form-label">Answer 2:</label>
                        <input type="text" class="form-control" id="edit_reset_answer2" name="reset_answer2" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="addForm" class="modal-content" action="admin_process.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="add_username" class="form-label">Username:</label>
                        <input type="text" class="form-control" id="add_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_email" class="form-label">Email:</label>
                        <input type="email" class="form-control" id="add_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_password" class="form-label">Password:</label>
                        <input type="password" class="form-control" id="add_password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_user_type" class="form-label">User Type:</label>
                        <select class="form-select" id="add_user_type" name="user_type" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="add_lrn_group">
                        <label for="add_student_lrn" class="form-label">Student LRN:</label>
                        <input type="text" class="form-control" id="add_student_lrn" name="student_lrn">
                    </div>
                    <div class="mb-3">
                        <label for="add_reset_question1" class="form-label">Security Question 1:</label>
                        <input type="text" class="form-control" id="add_reset_question1" name="reset_question1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_reset_answer1" class="form-label">Answer 1:</label>
                        <input type="text" class="form-control" id="add_reset_answer1" name="reset_answer1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_reset_question2" class="form-label">Security Question 2:</label>
                        <input type="text" class="form-control" id="add_reset_question2" name="reset_question2" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_reset_answer2" class="form-label">Answer 2:</label>
                        <input type="text" class="form-control" id="add_reset_answer2" name="reset_answer2" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete user "<strong id="delete_username"></strong>"? This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmDelete" href="#" class="btn btn-danger">Delete Permanently</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Confirmation Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        You are about to reset the password for user "<strong id="reset_username"></strong>".
                    </div>
                    <p>This action will:</p>
                    <ul>
                        <li>Generate a temporary password for the user</li>
                        <li>Reset the user's security questions</li>
                        <li>Require the user to set up new security questions upon login</li>
                    </ul>
                    <p>Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmResetPassword" href="#" class="btn btn-warning">Reset Password</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal initialization
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            const addModal = new bootstrap.Modal(document.getElementById('addModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const resetPasswordModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
            
            // Edit buttons
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute("data-id");
                    const username = this.getAttribute("data-username");
                    const email = this.getAttribute("data-email");
                    const userType = this.getAttribute("data-usertype");
                    const lrn = this.getAttribute("data-lrn");
                    const q1 = this.getAttribute("data-q1");
                    const a1 = this.getAttribute("data-a1");
                    const q2 = this.getAttribute("data-q2");
                    const a2 = this.getAttribute("data-a2");
                    
                    document.getElementById("edit_id").value = id;
                    document.getElementById("edit_username").value = username;
                    document.getElementById("edit_email").value = email;
                    document.getElementById("edit_user_type").value = userType;
                    document.getElementById("edit_student_lrn").value = lrn;
                    document.getElementById("edit_reset_question1").value = q1;
                    document.getElementById("edit_reset_answer1").value = a1;
                    document.getElementById("edit_reset_question2").value = q2;
                    document.getElementById("edit_reset_answer2").value = a2;
                    
                    // Toggle LRN field visibility based on user type
                    toggleLrnField();
                    
                    editModal.show();
                });
            });
            
            // Reset Password buttons
            document.querySelectorAll('.reset-password-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute("data-id");
                    const username = this.getAttribute("data-username");
                    
                    document.getElementById("reset_username").textContent = username;
                    document.getElementById("confirmResetPassword").href = `admin_dashboard.php?reset_password=${id}`;
                    
                    resetPasswordModal.show();
                });
            });
            
            // Add user button
            document.getElementById('addUserBtn').addEventListener('click', function() {
                // Reset form fields
                document.getElementById('addForm').reset();
                
                // Set default values if needed
                document.getElementById('add_user_type').value = 'student';
                
                // Toggle LRN field visibility
                toggleAddLrnField();
                
                addModal.show();
            });
            
            // Delete buttons
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute("data-id");
                    const username = this.getAttribute("data-username");
                    
                    document.getElementById("delete_username").textContent = username;
                    document.getElementById("confirmDelete").href = `admin_dashboard.php?delete=${id}`;
                    
                    deleteModal.show();
                });
            });
            
            // Toggle LRN field based on user type
            function toggleLrnField() {
                const userType = document.getElementById("edit_user_type").value;
                const lrnGroup = document.getElementById("lrn_group");
                const lrnInput = document.getElementById("edit_student_lrn");
                
                if (userType === "student") {
                    lrnGroup.style.display = "block";
                    lrnInput.setAttribute("required", "required");
                } else {
                    lrnGroup.style.display = "none";
                    lrnInput.removeAttribute("required");
                    lrnInput.value = ""; // Clear LRN for non-students
                }
            }
            
            function toggleAddLrnField() {
                const userType = document.getElementById("add_user_type").value;
                const lrnGroup = document.getElementById("add_lrn_group");
                const lrnInput = document.getElementById("add_student_lrn");
                
                if (userType === "student") {
                    lrnGroup.style.display = "block";
                    lrnInput.setAttribute("required", "required");
                } else {
                    lrnGroup.style.display = "none";
                    lrnInput.removeAttribute("required");
                    lrnInput.value = ""; // Clear LRN for non-students
                }
            }
            
            // Set up event listeners for user type change
            document.getElementById("edit_user_type").addEventListener("change", toggleLrnField);
            document.getElementById("add_user_type").addEventListener("change", toggleAddLrnField);
            
            // Initialize LRN field visibility on page load
            toggleLrnField();
            toggleAddLrnField();
        });
    </script>
</body>
</html>