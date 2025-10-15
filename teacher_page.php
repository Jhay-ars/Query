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

// Define message variable
$message = "";

// Process folder operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Create new folder
    if (isset($_POST["create_folder"]) && !empty(trim($_POST["folder_name"]))) {
        $folder_name = trim($_POST["folder_name"]);
        $teacher_id = $_SESSION["id"];
        
        // Check if folder already exists for this teacher
        $sql = "SELECT id FROM folders WHERE teacher_id = :teacher_id AND folder_name = :folder_name";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(":folder_name", $folder_name, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $message = "Folder with this name already exists.";
            } else {
                // Create the folder
                $sql = "INSERT INTO folders (teacher_id, folder_name, created_at) VALUES (:teacher_id, :folder_name, NOW())";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
                    $stmt->bindParam(":folder_name", $folder_name, PDO::PARAM_STR);
                    
                    if ($stmt->execute()) {
                        $message = "Folder created successfully!";
                    } else {
                        $message = "Error creating folder.";
                    }
                }
            }
        }
    }
    
    // Edit folder
    if (isset($_POST["rename_folder"]) && !empty(trim($_POST["folder_id"])) && !empty(trim($_POST["new_name"]))) {
        $folder_id = trim($_POST["folder_id"]);
        $new_folder_name = trim($_POST["new_name"]);
        $teacher_id = $_SESSION["id"];
        
        // Check if folder belongs to this teacher
        $sql = "SELECT id FROM folders WHERE id = :folder_id AND teacher_id = :teacher_id";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
            $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update the folder name
                $sql = "UPDATE folders SET folder_name = :folder_name WHERE id = :folder_id";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bindParam(":folder_name", $new_folder_name, PDO::PARAM_STR);
                    $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $message = "Folder name updated successfully!";
                    } else {
                        $message = "Error updating folder name.";
                    }
                }
            } else {
                $message = "You don't have permission to edit this folder.";
            }
        }
    }
    
    // Delete folder
    if (isset($_POST["delete_folder"]) && !empty(trim($_POST["folder_id"]))) {
        $folder_id = trim($_POST["folder_id"]);
        $teacher_id = $_SESSION["id"];
        
        // Check if folder belongs to this teacher
        $sql = "SELECT id FROM folders WHERE id = :folder_id AND teacher_id = :teacher_id";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
            $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Delete the folder
                $sql = "DELETE FROM folders WHERE id = :folder_id";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $message = "Folder deleted successfully!";
                    } else {
                        $message = "Error deleting folder.";
                    }
                }
            } else {
                $message = "You don't have permission to delete this folder.";
            }
        }
    }
}

// Get teacher's folders
$folders = array();
$teacher_id = $_SESSION["id"];
$sql = "SELECT id, folder_name, created_at FROM folders WHERE teacher_id = :teacher_id ORDER BY created_at DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
    $stmt->execute();
    $folders = $stmt->fetchAll();
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
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
    }
    .card-body {
        background-color: #fafbfc; /* Very subtle off-white */
    }
    .folder-icon {
        color: #ffc107;
        margin-right: 10px;
        font-size: 24px;
    }
    .navbar {
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .toast-container {
        position: fixed;
        top: 76px; /* Adjusted to be below navbar */
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
                    <!-- Add more nav items if needed -->
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

    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title mb-4 text-primary">
                    <i class="fas fa-folder-open"></i> My Folders
                </h2>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

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
                    
                <!-- Create Folder -->
                <form method="post" class="input-group mb-4">
                    <input type="text" name="folder_name" class="form-control" placeholder="New Folder" required>
                    <button type="submit" name="create_folder" class="btn btn-success">
                        <i class="fas fa-folder-plus"></i> Add
                    </button>
                </form>

                <!-- Folders List -->
                <div class="row g-4">
                    <?php if (count($folders) > 0): ?>
                        <?php foreach ($folders as $folder): ?>
                            <div class="col-md-4">
                                <div class="card folder-card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <a href="folder_view.php?id=<?php echo $folder["id"]; ?>" class="text-decoration-none text-dark">
                                                <i class="fas fa-folder folder-icon"></i> <?php echo htmlspecialchars($folder["folder_name"]); ?>
                                            </a>
                                        </h5>
                                        <p class="card-text">
                                            <small class="text-muted">Created: <?php echo date("F j, Y, g:i a", strtotime($folder["created_at"])); ?></small>
                                        </p>
                                        <div class="mt-3">
                                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#renameModal<?php echo $folder["id"]; ?>">
                                                <i class="fas fa-pen"></i> Rename
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $folder["id"]; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rename Modal -->
                            <div class="modal fade" id="renameModal<?php echo $folder["id"]; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Rename Folder</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="folder_id" value="<?php echo $folder["id"]; ?>">
                                            <input type="text" name="new_name" class="form-control" value="<?php echo htmlspecialchars($folder["folder_name"]); ?>" required>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="rename_folder" class="btn btn-primary">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?php echo $folder["id"]; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">Delete Folder</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Are you sure you want to delete "<strong><?php echo htmlspecialchars($folder["folder_name"]); ?></strong>"? This cannot be undone.
                                            </div>
                                            <input type="hidden" name="folder_id" value="<?php echo $folder["id"]; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_folder" class="btn btn-danger">Delete Permanently</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No folders found. Create your first folder using the form above.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize toasts
            var toastEl = document.querySelector('.toast');
            if (toastEl) {
                var toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    </script>
</body>
</html>