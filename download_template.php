<?php
// This script generates and offers a CSV template for downloading

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users_import_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output header row
fputcsv($output, [
    'username',
    'password',
    'email',
    'user_type',
    'reset_question1',
    'reset_answer1',
    'reset_question2',
    'reset_answer2',
    'student_lrn'
]);

// Output sample data row for teacher
fputcsv($output, [
    'teacher_sample',
    'password123',
    'teacher@example.com',
    'teacher',
    'What was your first pet\'s name?',
    'Spot',
    'What is your favorite color?',
    'Blue',
    '' // Empty for teacher
]);

// Output sample data row for student
fputcsv($output, [
    'student_sample',
    'password123',
    'student@example.com',
    'student',
    'What was your first pet\'s name?',
    'Fluffy',
    'What is your favorite color?',
    'Green',
    '12345678' // LRN for student
]);

// Close the file pointer
fclose($output);
exit;
?>