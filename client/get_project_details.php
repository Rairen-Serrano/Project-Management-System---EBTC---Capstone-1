<?php
// First, ensure there's no output before session_start
ob_start();

// Start session and include database connection
session_start();
require_once '../dbconnect.php';

// Clear any previous output
ob_clean();

// Set headers
header('Content-Type: application/json');

try {
    // Check if user is logged in and is a client
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
        throw new Exception('Unauthorized access');
    }

    // Validate project ID
    if (!isset($_GET['project_id'])) {
        throw new Exception('Project ID is required');
    }

    $projectId = (int)$_GET['project_id'];
    $userId = $_SESSION['user_id'];

    // Get project details
    $stmt = $pdo->prepare("
        SELECT * FROM projects 
        WHERE project_id = ? AND client_id = ?
    ");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Project not found');
    }

    // Get personnel
    $stmt = $pdo->prepare("
        SELECT u.name, u.role, u.email 
        FROM project_assignees pa 
        JOIN users u ON pa.user_id = u.user_id 
        WHERE pa.project_id = ?
    ");
    $stmt->execute([$projectId]);
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get tasks
    $stmt = $pdo->prepare("
        SELECT t.task_name, t.due_date, t.status, u.name as assigned_to 
        FROM tasks t 
        LEFT JOIN task_assignees ta ON t.task_id = ta.task_id 
        LEFT JOIN users u ON ta.user_id = u.user_id 
        WHERE t.project_id = ?
    ");
    $stmt->execute([$projectId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'project' => $project,
        'personnel' => $personnel,
        'tasks' => $tasks
    ];

    // Send response
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    exit;
} 