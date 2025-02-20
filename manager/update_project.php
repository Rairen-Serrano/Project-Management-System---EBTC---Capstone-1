<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if project ID is provided
if (!isset($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

try {
    $project_id = $_POST['project_id'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update project notes
    $stmt = $pdo->prepare("UPDATE projects SET notes = ? WHERE project_id = ?");
    $stmt->execute([$notes, $project_id]);

    // Handle quotation file upload if provided
    if (isset($_FILES['quotation_file']) && $_FILES['quotation_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['quotation_file'];
        
        // Validate file type
        $allowed_types = ['application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Only PDF files are allowed');
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/quotations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $filename = 'quotation_' . $project_id . '_' . time() . '.pdf';
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Update database with new file path
            $stmt = $pdo->prepare("UPDATE projects SET quotation_file = ? WHERE project_id = ?");
            $stmt->execute([$filename, $project_id]);
        } else {
            throw new Exception('Error uploading file');
        }
    }
    
    // If remove_quotation flag is set, remove the quotation file
    if (isset($_POST['remove_quotation']) && $_POST['remove_quotation'] === 'true') {
        // Get current quotation file
        $stmt = $pdo->prepare("SELECT quotation_file FROM projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $current_file = $stmt->fetchColumn();
        
        if ($current_file) {
            // Delete file from server
            $filepath = '../uploads/quotations/' . $current_file;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Update database to remove file reference
            $stmt = $pdo->prepare("UPDATE projects SET quotation_file = NULL WHERE project_id = ?");
            $stmt->execute([$project_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Project updated successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in update_project.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 