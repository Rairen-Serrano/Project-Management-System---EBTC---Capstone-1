<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if project ID is provided
if (!isset($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    $project_id = $_POST['project_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    $quotation_file = null;

    // Handle file upload if a file is provided
    if (isset($_FILES['quotation_file']) && $_FILES['quotation_file']['error'] === UPLOAD_ERR_OK) {
        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/quotations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $file_extension = pathinfo($_FILES['quotation_file']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('quotation_') . '.' . $file_extension;
        $target_path = $upload_dir . $filename;

        // Check if file is a PDF
        $allowed_types = ['application/pdf'];
        $file_type = mime_content_type($_FILES['quotation_file']['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Only PDF files are allowed');
        }

        // Move uploaded file
        if (move_uploaded_file($_FILES['quotation_file']['tmp_name'], $target_path)) {
            $quotation_file = $filename;

            // Delete old file if exists
            $stmt = $pdo->prepare("SELECT quotation_file FROM projects WHERE project_id = ?");
            $stmt->execute([$project_id]);
            $old_file = $stmt->fetchColumn();

            if ($old_file && file_exists($upload_dir . $old_file)) {
                unlink($upload_dir . $old_file);
            }
        } else {
            throw new Exception('Failed to upload file');
        }
    }

    // Update project
    $query = "UPDATE projects SET status = ?, notes = ?";
    $params = [$status, $notes];

    if ($quotation_file !== null) {
        $query .= ", quotation_file = ?";
        $params[] = $quotation_file;
    }

    $query .= " WHERE project_id = ?";
    $params[] = $project_id;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Project updated successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in update_project.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating project: ' . $e->getMessage()]);
} 