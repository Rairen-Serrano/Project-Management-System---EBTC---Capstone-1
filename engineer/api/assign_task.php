<?php
session_start();
require_once '../../dbconnect.php';

// Debug log for file inclusion
error_log("Current directory: " . __DIR__);
error_log("Attempting to include send_notification.php");

// Try multiple possible paths
$possiblePaths = [
    __DIR__ . '/send_notification.php',
    __DIR__ . '/../api/send_notification.php',
    'send_notification.php',
    './send_notification.php',
    '../api/send_notification.php'
];

$included = false;
foreach ($possiblePaths as $path) {
    error_log("Trying path: " . $path);
    if (file_exists($path)) {
        require_once $path;
        error_log("Successfully included send_notification.php from: " . $path);
        $included = true;
        break;
    }
}

if (!$included) {
    error_log("ERROR: Could not find send_notification.php in any of the attempted paths");
}

// Verify functions exist
if (function_exists('sendNotification')) {
    error_log("sendNotification function exists");
} else {
    error_log("ERROR: sendNotification function not found");
}

if (function_exists('notifyTaskAssignment')) {
    error_log("notifyTaskAssignment function exists");
} else {
    error_log("ERROR: notifyTaskAssignment function not found");
}

// Integrate notification function directly
function sendNotification($pdo, $user_id, $title, $message, $type, $reference_id = null) {
    try {
        error_log("Attempting to send notification to user: " . $user_id);
        error_log("Title: " . $title);
        error_log("Message: " . $message);
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, reference_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([$user_id, $title, $message, $type, $reference_id]);
        
        if (!$result) {
            throw new Exception("Failed to insert notification: " . implode(", ", $stmt->errorInfo()));
        }
        
        error_log("Notification sent successfully");
        return true;
    } catch (Exception $e) {
        error_log("Error in sendNotification: " . $e->getMessage());
        throw $e;
    }
}

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
    error_log("Received data in assign_task.php: " . print_r($data, true));
    error_log("Current user ID: " . $_SESSION['user_id']);

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

    // Get project details
    $stmt = $pdo->prepare("SELECT service as project_name FROM projects WHERE project_id = ?");
    $stmt->execute([$data['project_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get assigner details
    $stmt = $pdo->prepare("SELECT name as assigner_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $assigner = $stmt->fetch(PDO::FETCH_ASSOC);

    // Insert task
    $stmt = $pdo->prepare("
        INSERT INTO tasks (project_id, category_id, task_name, description, due_date, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");

    $result = $stmt->execute([
        $data['project_id'],
        $data['category_id'],
        $data['task_name'],
        $data['description'],
        $data['due_date'],
        $_SESSION['user_id']
    ]);

    if (!$result) {
        throw new Exception("Failed to insert task: " . implode(", ", $stmt->errorInfo()));
    }

    $task_id = $pdo->lastInsertId();
    error_log("Created task with ID: " . $task_id);

    // Insert task assignees and send notifications
    $stmt = $pdo->prepare("
        INSERT INTO task_assignees (task_id, user_id, assigned_date) 
        VALUES (?, ?, NOW())
    ");

    foreach ($data['assignees'] as $assignee_id) {
        // Insert assignee
        $result = $stmt->execute([
            $task_id,
            $assignee_id
        ]);
        
        if (!$result) {
            throw new Exception("Failed to assign task to user {$assignee_id}: " . implode(", ", $stmt->errorInfo()));
        }
        error_log("Assigned task {$task_id} to user {$assignee_id}");

        // Get assignee details
        $stmt2 = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmt2->execute([$assignee_id]);
        $assignee = $stmt2->fetch(PDO::FETCH_ASSOC);

        // Send notification to assignee
        $title = "New Task Assignment: " . $data['task_name'];
        $message = "You have been assigned to task '" . $data['task_name'] . "' in project '" . $project['project_name'] . 
                  "' by " . $assigner['assigner_name'] . ". Due date: " . date('M j, Y', strtotime($data['due_date']));

        try {
            sendNotification($pdo, $assignee_id, $title, $message, 'task', $task_id);
            error_log("Notification sent to user {$assignee_id}");
        } catch (Exception $e) {
            error_log("Failed to send notification to user {$assignee_id}: " . $e->getMessage());
        }
    }

    $pdo->commit();
    error_log("Transaction committed successfully");

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
    error_log("Error trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 