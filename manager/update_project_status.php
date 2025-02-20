<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if project ID and current status are provided
if (!isset($_POST['project_id']) || !isset($_POST['current_status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $project_id = $_POST['project_id'];
    $current_status = $_POST['current_status'];
    
    // Toggle the status
    $new_status = $current_status === 'ongoing' ? 'completed' : 'ongoing';
    
    // Update the project status
    $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE project_id = ?");
    $stmt->execute([$new_status, $project_id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Project status updated successfully',
        'new_status' => $new_status
    ]);
} catch (Exception $e) {
    error_log("Error in update_project_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating project status']);
} 