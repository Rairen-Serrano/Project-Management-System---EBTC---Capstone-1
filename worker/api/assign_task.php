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

    // Send notifications
    notifyTaskAssignment($pdo, $task_id, $_SESSION['user_id']);

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