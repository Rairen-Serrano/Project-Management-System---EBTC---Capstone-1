<?php
session_start();
require_once '../../dbconnect.php';
require_once 'send_notification.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get and decode the JSON data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Debug log
    error_log("Received data: " . print_r($data, true));

    // Validate required fields
    if (empty($data['project_id'])) {
        throw new Exception('Project ID is required');
    }
    if (empty($data['task_name'])) {
        throw new Exception('Task name is required');
    }
    if (empty($data['due_date'])) {
        throw new Exception('Due date is required');
    }
    if (empty($data['assignees']) || !is_array($data['assignees'])) {
        throw new Exception('At least one assignee is required');
    }

    $pdo->beginTransaction();

    // Insert task
    $stmt = $pdo->prepare("
        INSERT INTO tasks (project_id, category_id, task_name, description, due_date, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");

    $stmt->execute([
        $data['project_id'],
        $data['category_id'],
        $data['task_name'],
        $data['description'],
        $data['due_date'],
        $_SESSION['user_id']
    ]);

    $task_id = $pdo->lastInsertId();

    // Insert task assignees
    $stmt = $pdo->prepare("
        INSERT INTO task_assignees (task_id, user_id, assigned_date) 
        VALUES (?, ?, NOW())
    ");

    foreach ($data['assignees'] as $assignee_id) {
        $stmt->execute([
            $task_id,
            $assignee_id
        ]);
    }

    // Send notifications directly without curl
    try {
        // Get project details
        $project_stmt = $pdo->prepare("SELECT service FROM projects WHERE project_id = ?");
        $project_stmt->execute([$data['project_id']]);
        $project = $project_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            throw new Exception('Project not found');
        }

        // Get assigner's name
        $assigner_stmt = $pdo->prepare("SELECT name, user_id FROM users WHERE user_id = ?");
        $assigner_stmt->execute([$_SESSION['user_id']]);
        $assigner = $assigner_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assigner) {
            throw new Exception('Assigner information not found');
        }

        // Prepare notification statement
        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,         -- sender
                recipient_id,    -- receiver
                type, 
                reference_id,    -- task_id
                title, 
                message, 
                is_read, 
                created_at
            ) VALUES (?, ?, 'task', ?, ?, ?, FALSE, NOW())
        ");

        $title = "New Task Assignment";
        $message = "You have been assigned to task '{$data['task_name']}' in project '{$project['service']}' by {$assigner['name']}.";

        // Send notification to each assignee
        foreach ($data['assignees'] as $assignee_id) {
            // Skip if the assignee is the task creator
            if ($assignee_id == $_SESSION['user_id']) {
                continue;
            }
            
            error_log("Sending notification to assignee: " . $assignee_id);
            
            $notification_stmt->execute([
                $_SESSION['user_id'],  // sender
                $assignee_id,          // receiver
                $task_id,              // reference_id
                $title,
                $message
            ]);
        }
    } catch (Exception $e) {
        error_log("Error sending notifications: " . $e->getMessage());
        // Continue with task creation even if notifications fail
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Task created and assigned successfully',
        'task_id' => $task_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in assign_task.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 