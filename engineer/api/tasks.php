<?php
session_start();
require_once '../../dbconnect.php';

// Ensure no output before headers
header('Content-Type: application/json');

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_task') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input data
    if (!isset($data['project_id']) || !isset($data['task_name']) || !isset($data['due_date']) || !isset($data['assignees']) || !isset($data['category_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields',
            'data' => $data
        ]);
        exit;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Ensure we have exactly 6 parameters for 6 placeholders
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                project_id,
                category_id,
                task_name,
                description,
                status,
                due_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['project_id'],
            $data['category_id'],
            $data['task_name'],
            $data['description'] ?? '',
            'pending',
            $data['due_date'],
            $_SESSION['user_id']
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Insert task assignees
        $stmt = $pdo->prepare("
            INSERT INTO task_assignees (
                task_id,
                user_id,
                assigned_date
            ) VALUES (?, ?, NOW())
        ");
        
        foreach ($data['assignees'] as $assignee_id) {
            $stmt->execute([$task_id, $assignee_id]);
        }
        
        // Add task history
        $stmt = $pdo->prepare("
            INSERT INTO task_history (
                task_id,
                user_id,
                action,
                details,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $task_id,
            $_SESSION['user_id'],
            'created',
            json_encode([
                'assignees' => $data['assignees'],
                'due_date' => $data['due_date'],
                'category_id' => $data['category_id']
            ])
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $task_id
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating task: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method or action'
    ]);
    exit;
}

// When getting assigned tasks
$stmt = $pdo->prepare("
    SELECT t.*, p.service as project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    JOIN project_assignees pa ON p.project_id = pa.project_id
    WHERE pa.user_id = ?
"); 
?>