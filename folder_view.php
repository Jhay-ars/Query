<?php
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

// Check if folder ID is set
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: teacher_page.php");
    exit;
}

$folder_id = $_GET['id'];
$teacher_id = $_SESSION["id"];

// Verify that the folder belongs to the current teacher
$sql = "SELECT folder_name FROM folders WHERE id = :folder_id AND teacher_id = :teacher_id";
if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
    $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        // Folder doesn't belong to this teacher
        header("location: teacher_page.php");
        exit;
    }
    
    $folder = $stmt->fetch();
    $folder_name = $folder["folder_name"];
}

// Process spreadsheet operations
$message = "";

// Add new spreadsheet
if(isset($_POST['create_spreadsheet']) && !empty(trim($_POST['spreadsheet_name']))) {
    $name = trim($_POST['spreadsheet_name']);
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert new spreadsheet
        $sql = "INSERT INTO spreadsheets (name, folder_id) VALUES (:name, :folder_id)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Get the ID of the newly created spreadsheet
        $new_spreadsheet_id = $conn->lastInsertId();
        
        // Check if there are existing spreadsheets in this folder
        $sql = "SELECT id FROM spreadsheets WHERE folder_id = :folder_id AND id != :new_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
        $stmt->bindParam(":new_id", $new_spreadsheet_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $existing_spreadsheet = $stmt->fetch();
            $existing_spreadsheet_id = $existing_spreadsheet['id'];
            
            // Get all students from the existing spreadsheet
            $sql = "SELECT student_id, name FROM students WHERE spreadsheet_id = :spreadsheet_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":spreadsheet_id", $existing_spreadsheet_id, PDO::PARAM_INT);
            $stmt->execute();
            $students = $stmt->fetchAll();
            
            // Copy students to the new spreadsheet
            if(count($students) > 0) {
                $sql = "INSERT INTO students (student_id, name, spreadsheet_id) VALUES (:student_id, :name, :spreadsheet_id)";
                $stmt = $conn->prepare($sql);
                
                foreach($students as $student) {
                    $stmt->bindParam(":student_id", $student['student_id'], PDO::PARAM_STR);
                    $stmt->bindParam(":name", $student['name'], PDO::PARAM_STR);
                    $stmt->bindParam(":spreadsheet_id", $new_spreadsheet_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                $message = "Spreadsheet created successfully with " . count($students) . " students copied from existing spreadsheet!";
            } else {
                $message = "Spreadsheet created successfully! (No students were found to copy)";
            }
        } else {
            $message = "Spreadsheet created successfully!";
        }
        
        // Commit the transaction
        $conn->commit();
    } catch (Exception $e) {
        // Roll back the transaction if something failed
        $conn->rollback();
        $message = "Error creating spreadsheet: " . $e->getMessage();
    }
}

// Rename spreadsheet
if(isset($_POST['rename_spreadsheet']) && !empty($_POST['spreadsheet_id']) && !empty($_POST['new_name'])) {
    $spreadsheet_id = $_POST['spreadsheet_id'];
    $new_name = trim($_POST['new_name']);
    
    // Verify that the spreadsheet belongs to a folder owned by this teacher
    $sql = "SELECT s.id FROM spreadsheets s 
            JOIN folders f ON s.folder_id = f.id 
            WHERE s.id = :spreadsheet_id AND f.teacher_id = :teacher_id";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            // Update the spreadsheet name
            $sql = "UPDATE spreadsheets SET name = :name WHERE id = :id";
            if($stmt = $conn->prepare($sql)) {
                $stmt->bindParam(":name", $new_name, PDO::PARAM_STR);
                $stmt->bindParam(":id", $spreadsheet_id, PDO::PARAM_INT);
                
                if($stmt->execute()) {
                    $message = "Spreadsheet renamed successfully!";
                } else {
                    $message = "Error renaming spreadsheet.";
                }
            }
        } else {
            $message = "You don't have permission to rename this spreadsheet.";
        }
    }
}

// Delete spreadsheet
if(isset($_POST['delete_spreadsheet']) && !empty($_POST['spreadsheet_id'])) {
    $spreadsheet_id = $_POST['spreadsheet_id'];
    
    // Verify that the spreadsheet belongs to a folder owned by this teacher
    $sql = "SELECT s.id FROM spreadsheets s 
            JOIN folders f ON s.folder_id = f.id 
            WHERE s.id = :spreadsheet_id AND f.teacher_id = :teacher_id";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":spreadsheet_id", $spreadsheet_id, PDO::PARAM_INT);
        $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            // Delete the spreadsheet
            $sql = "DELETE FROM spreadsheets WHERE id = :id";
            if($stmt = $conn->prepare($sql)) {
                $stmt->bindParam(":id", $spreadsheet_id, PDO::PARAM_INT);
                
                if($stmt->execute()) {
                    $message = "Spreadsheet deleted successfully!";
                } else {
                    $message = "Error deleting spreadsheet.";
                }
            }
        } else {
            $message = "You don't have permission to delete this spreadsheet.";
        }
    }
}

// Get all spreadsheets in this folder
$spreadsheets = array();
$sql = "SELECT * FROM spreadsheets WHERE folder_id = :folder_id ORDER BY created_at DESC";
if($stmt = $conn->prepare($sql)) {
    $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
    $stmt->execute();
    $spreadsheets = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($folder_name) ?> - Spreadsheets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
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
        .sheet-card {
            border: 1px solid #e0e5ec;
            border-radius: 10px;
            padding: 20px;
            background-color: #fafbfc;
            transition: 0.2s;
        }
        .sheet-card:hover {
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.08);
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .container.py-4 {
            max-width: 1200px;
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
        
            <a class="navbar-brand" href="#">
                <i></i> <?= htmlspecialchars($folder_name) ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="teacher_page.php">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </li>
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

    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title mb-4 text-primary">
                    <i class="fas fa-table"></i> Spreadsheets
                </h2>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="post" class="input-group mb-4">
                    <input type="text" name="spreadsheet_name" class="form-control" placeholder="New Spreadsheet" required>
                    <button type="submit" name="create_spreadsheet" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </form>

                <div class="row g-4">
                    <?php if (count($spreadsheets) > 0): ?>
                        <?php foreach ($spreadsheets as $s): ?>
                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-file-excel text-success me-2"></i>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </h5>
                                        <p class="card-text">
                                            <small class="text-muted">Created: <?php echo date("F j, Y, g:i a", strtotime($s['created_at'])); ?></small>
                                        </p>
                                        <div class="mt-3">
                                            <a href="spreadsheet.php?id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-external-link-alt"></i> Open
                                            </a>
                                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#renameModal<?= $s['id'] ?>">
                                                <i class="fas fa-pen"></i> Rename
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rename Modal -->
                            <div class="modal fade" id="renameModal<?= $s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Rename Spreadsheet</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="spreadsheet_id" value="<?= $s['id'] ?>">
                                            <input type="text" name="new_name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="rename_spreadsheet" class="btn btn-primary">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">Delete Spreadsheet</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Are you sure you want to delete "<strong><?= htmlspecialchars($s['name']) ?></strong>"? This cannot be undone.
                                                <br>
                                                TIP: Before deleting, make sure you export your spreadsheet to create a PDF copy of your folder.
                                            </div>
                                            <input type="hidden" name="spreadsheet_id" value="<?= $s['id'] ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_spreadsheet" class="btn btn-danger">Delete Permanently</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No spreadsheets found in this folder. Create your first spreadsheet using the form above.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>