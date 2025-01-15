<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get current file path
    $stmt = $pdo->prepare("SELECT quotation_file FROM projects WHERE project_id = ?");
    $stmt->execute([$data['project_id']]);
    $current_file = $stmt->fetchColumn();

    if ($current_file) {
        // Delete file from storage
        $file_path = '../uploads/quotations/' . $current_file;
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Update database
        $stmt = $pdo->prepare("UPDATE projects SET quotation_file = NULL WHERE project_id = ?");
        $stmt->execute([$data['project_id']]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'File removed successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in remove_quotation_file.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error removing file: ' . $e->getMessage()]);
} 