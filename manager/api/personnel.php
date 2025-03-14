<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'DELETE':
            // Get request body
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['project_id']) || !isset($data['user_id'])) {
                throw new Exception('Missing required parameters');
            }

            // Check if project exists and belongs to the manager
            $stmt = $pdo->prepare("
                SELECT project_id 
                FROM projects 
                WHERE project_id = ?
            ");
            $stmt->execute([$data['project_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Project not found');
            }

            // Check if user is assigned to the project
            $stmt = $pdo->prepare("
                SELECT user_id 
                FROM project_assignees 
                WHERE project_id = ? AND user_id = ?
            ");
            $stmt->execute([$data['project_id'], $data['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('User is not assigned to this project');
            }

            // Start transaction
            $pdo->beginTransaction();

            // Remove user from project
            $stmt = $pdo->prepare("
                DELETE FROM project_assignees 
                WHERE project_id = ? AND user_id = ?
            ");
            $stmt->execute([$data['project_id'], $data['user_id']]);

            // Remove user from all tasks in this project
            $stmt = $pdo->prepare("
                DELETE ta 
                FROM task_assignees ta
                JOIN tasks t ON ta.task_id = t.task_id
                WHERE t.project_id = ? AND ta.user_id = ?
            ");
            $stmt->execute([$data['project_id'], $data['user_id']]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Team member removed successfully']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 