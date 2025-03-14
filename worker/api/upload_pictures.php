<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an worker
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['task_id']) || !isset($_FILES['pictures'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    $task_id = $_POST['task_id'];
    $uploaded_pictures = [];
    
    // Verify that the user is assigned to this task
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_assignees 
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this task']);
        exit;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = "../../uploads/task_pictures/$task_id/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle file uploads
    foreach ($_FILES['pictures']['tmp_name'] as $key => $tmp_name) {
        $file_name = $_FILES['pictures']['name'][$key];
        $file_size = $_FILES['pictures']['size'][$key];
        $file_type = $_FILES['pictures']['type'][$key];
        
        // Validate file type
        if (!in_array($file_type, ['image/jpeg', 'image/png', 'image/gif'])) {
            continue;
        }
        
        // Generate unique filename
        $unique_filename = uniqid() . '_' . $file_name;
        $file_path = $upload_dir . $unique_filename;
        
        if (move_uploaded_file($tmp_name, $file_path)) {
            // Save file info to database
            $stmt = $pdo->prepare("
                INSERT INTO task_pictures (
                    task_id,
                    user_id,
                    file_name,
                    file_path,
                    uploaded_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $task_id,
                $_SESSION['user_id'],
                $file_name,
                'uploads/task_pictures/' . $task_id . '/' . $unique_filename
            ]);
            
            $uploaded_pictures[] = [
                'url' => '../uploads/task_pictures/' . $task_id . '/' . $unique_filename,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'pictures' => $uploaded_pictures
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 