<?php
require_once 'config.php';

session_start();


$response = [
    'status' => 'error',
    'message' => 'No action specified',
    'redirect' => 'admin_settings.php'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'reset_defaults':
                $response = resetDefaultSettings($conn);
                break;
                
            case 'backup_settings':
                $response = backupSettings($conn);
                break;
                
            case 'restore_settings':
                $response = restoreSettings($conn);
                break;
                
            default:
                $response['message'] = 'Invalid action specified';
                break;
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    $status = $response['status'];
    $message = urlencode($response['message']);
    header("Location: {$response['redirect']}?$status=$message");
    exit();
}

function resetDefaultSettings($conn) {
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("TRUNCATE TABLE settings");
        $stmt->execute();
        
        $defaultSettings = [
            'school_name' => 'School Portal',
            'school_address' => '123 Education St., City',
            'school_email' => 'info@schoolportal.com',
            'school_phone' => '123-456-7890',
            'portal_title' => 'School Portal System',
            'maintenance_mode' => 'off',
            'academic_year' => date('Y') . '-' . (date('Y') + 1),
            'allow_password_reset' => 'on',
            'max_login_attempts' => '5',
            'lockout_time' => '30',
            'enable_lrn_verification' => 'on',
            'custom_js' => '',
            'custom_css' => ''
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
        
        $conn->commit();
        return [
            'status' => 'success',
            'message' => 'Settings have been reset to default values.',
            'redirect' => 'admin_settings.php'
        ];
    } catch (PDOException $e) {
        $conn->rollBack();
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'redirect' => 'admin_settings.php'
        ];
    }
}


function backupSettings($conn) {
    try {
        $stmt = $conn->query("SELECT setting_key, value FROM settings");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['value'];
        }
        
        $settings['_metadata'] = [
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['username'],
            'version' => '1.0'
        ];
        
        $json = json_encode($settings, JSON_PRETTY_PRINT);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="school_portal_settings_' . date('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
        
    } catch (PDOException $e) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'redirect' => 'admin_settings.php'
        ];
    }
}

function restoreSettings($conn) {
    if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
        return [
            'status' => 'error',
            'message' => 'No file uploaded or upload error.',
            'redirect' => 'admin_settings.php'
        ];
    }
    
    $file = $_FILES['settings_file'];
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'json') {
        return [
            'status' => 'error',
            'message' => 'Only JSON files are allowed.',
            'redirect' => 'admin_settings.php'
        ];
    }
    
    $jsonContent = file_get_contents($file['tmp_name']);
    $settings = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error',
            'message' => 'Invalid JSON file: ' . json_last_error_msg(),
            'redirect' => 'admin_settings.php'
        ];
    }
    
    if (!isset($settings['_metadata'])) {
        return [
            'status' => 'error',
            'message' => 'Invalid settings file format.',
            'redirect' => 'admin_settings.php'
        ];
    }
    
    unset($settings['_metadata']);
    
    try {
        $conn->beginTransaction();
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
            
            if ($stmt->rowCount() == 0) {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        
        $conn->commit();
        return [
            'status' => 'success',
            'message' => 'Settings have been restored successfully.',
            'redirect' => 'admin_settings.php'
        ];
    } catch (PDOException $e) {
        $conn->rollBack();
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'redirect' => 'admin_settings.php'
        ];
    }
}
