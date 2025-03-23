<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if project_id is provided
if (!isset($_GET['project_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID is required']);
    exit;
}

try {
    $projectId = $_GET['project_id'];

    // Get all task categories for the project
    $stmt = $pdo->prepare("
        SELECT status 
        FROM task_categories 
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if any category is still in progress
    $hasInProgressCategories = false;
    foreach ($categories as $category) {
        if ($category['status'] === 'in_progress') {
            $hasInProgressCategories = true;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'hasInProgressCategories' => $hasInProgressCategories
    ]);

} catch (Exception $e) {
    error_log("Error in get_task_categories_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?> 