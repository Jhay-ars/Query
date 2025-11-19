<?php
// This script will update existing users' student_lrn field with their corresponding student_id from the students table
// Run this script after adding the student_lrn column to the users table

// Include database connection
require_once "config.php";

echo "Starting update of student_lrn field for existing users...\n";

try {
    // First, update users who have the same username as student_id in the students table
    $sql = "UPDATE users u
            JOIN students s ON u.username = s.student_id
            SET u.student_lrn = s.student_id
            WHERE u.user_type = 'student' AND (u.student_lrn IS NULL OR u.student_lrn = '')";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $count1 = $stmt->rowCount();
    echo "Updated $count1 users based on exact username match.\n";
    
    // Then, try to update remaining users with case-insensitive match
    $sql = "UPDATE users u
            JOIN students s ON LOWER(u.username) = LOWER(s.student_id)
            SET u.student_lrn = s.student_id
            WHERE u.user_type = 'student' AND (u.student_lrn IS NULL OR u.student_lrn = '')";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $count2 = $stmt->rowCount();
    echo "Updated $count2 additional users based on case-insensitive username match.\n";
    
    // Check how many student accounts still need updating
    $sql = "SELECT COUNT(*) as remaining FROM users 
            WHERE user_type = 'student' AND (student_lrn IS NULL OR student_lrn = '')";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "Remaining student accounts without student_lrn: " . $result['remaining'] . "\n";
    
    if ($result['remaining'] > 0) {
        echo "The following student accounts need manual updating:\n";
        
        $sql = "SELECT id, username, email FROM users 
                WHERE user_type = 'student' AND (student_lrn IS NULL OR student_lrn = '')";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        echo "ID\tUsername\tEmail\n";
        echo "------------------------\n";
        
        while ($row = $stmt->fetch()) {
            echo $row['id'] . "\t" . $row['username'] . "\t" . $row['email'] . "\n";
        }
    }
    
    echo "\nUpdate completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>