<?php
session_start();
require_once '../../dbconnect.php';
require_once 'send_notification.php';

header('Content-Type: application/json');

// Check if user is logged in and is an worker
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing task ID']);
    exit;
}

try {
    $task_id = $data['task_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify that the user is assigned to this task
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_assignees 
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$task_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this task']);
        exit;
    }
    
    // Check if task has check-in record
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_check_ins 
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$task_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You must check in before completing the task']);
        exit;
    }
    
    // Check if task has at least one picture
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_pictures 
        WHERE task_id = ?
    ");
    $stmt->execute([$task_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You must upload at least one picture before completing the task']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();
    
    // Mark this user's completion status
    $stmt = $pdo->prepare("
        INSERT INTO task_completion_status (task_id, user_id, completed, completed_at)
        VALUES (?, ?, TRUE, NOW())
        ON DUPLICATE KEY UPDATE 
            completed = TRUE,
            completed_at = NOW()
    ");
    $stmt->execute([$task_id, $user_id]);

    // Get task and project details for notification
    $stmt = $pdo->prepare("
        SELECT 
            t.task_name,
            p.service as project_name,
            (SELECT user_id FROM users WHERE role = 'project_manager' LIMIT 1) as project_manager_id,
            u.name as completer_name
        FROM tasks t
        JOIN projects p ON t.project_id = p.project_id
        JOIN users u ON u.user_id = ?
        WHERE t.task_id = ?
    ");
    $stmt->execute([$user_id, $task_id]);
    $taskDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all assignees except the current user
    $stmt = $pdo->prepare("
        SELECT 
            ta.user_id,
            u.name,
            COALESCE(tcs.completed, FALSE) as completed
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.user_id
        LEFT JOIN task_completion_status tcs ON ta.task_id = tcs.task_id AND ta.user_id = tcs.user_id
        WHERE ta.task_id = ? AND ta.user_id != ?
    ");
    $stmt->execute([$task_id, $user_id]);
    $otherAssignees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Send notifications to other assignees who haven't completed their part
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
        ) VALUES (?, ?, 'task_progress', ?, ?, ?, FALSE, NOW())
    ");

    foreach ($otherAssignees as $assignee) {
        if (!$assignee['completed']) {
            $title = "Task Progress Update";
            $message = "{$taskDetails['completer_name']} has completed their part of the task '{$taskDetails['task_name']}' in project '{$taskDetails['project_name']}'";
            
            error_log("Sending notification to assignee: " . $assignee['user_id']);
            
            $notification_stmt->execute([
                $user_id,               // sender (current user)
                $assignee['user_id'],   // receiver
                $task_id,               // reference_id
                $title,
                $message
            ]);
        }
    }

    // Check if all assignees have completed the task
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(ta.user_id) as total_assignees,
            SUM(CASE WHEN tcs.completed = TRUE THEN 1 ELSE 0 END) as completed_count
        FROM task_assignees ta
        LEFT JOIN task_completion_status tcs ON ta.task_id = tcs.task_id AND ta.user_id = tcs.user_id
        WHERE ta.task_id = ?
        GROUP BY ta.task_id
    ");
    $stmt->execute([$task_id]);
    $completion_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get completion status of all assignees
    $stmt = $pdo->prepare("
        SELECT 
            u.name,
            COALESCE(tcs.completed, FALSE) as completed,
            tcs.completed_at
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.user_id
        LEFT JOIN task_completion_status tcs ON ta.task_id = tcs.task_id AND ta.user_id = tcs.user_id
        WHERE ta.task_id = ?
    ");
    $stmt->execute([$task_id]);
    $assignees_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only update task status if all assignees have completed
    if ($completion_stats['total_assignees'] == $completion_stats['completed_count']) {
        // Update task status
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET 
                status = 'completed',
                completed_at = NOW(),
                completed_by = ?,
                updated_at = NOW()
            WHERE task_id = ?
        ");
        $stmt->execute([$user_id, $task_id]);
        
        // Log the completion
        $stmt = $pdo->prepare("
            INSERT INTO task_history (
                task_id,
                user_id,
                action,
                details,
                created_at
            ) VALUES (?, ?, 'completed', ?, NOW())
        ");
        $stmt->execute([
            $task_id,
            $user_id,
            json_encode(['status' => 'completed', 'final_completion' => true])
        ]);

        // Send notification to project manager
        $title = "Task Completed";
        $message = "{$taskDetails['completer_name']} and team have completed the task '{$taskDetails['task_name']}' in project '{$taskDetails['project_name']}'";
        
        $notification_stmt->execute([
            $user_id,                           // sender (current user)
            $taskDetails['project_manager_id'], // receiver (project manager)
            $task_id,                          // reference_id
            $title,
            $message
        ]);

        $message = 'Task marked as completed successfully';
    } else {
        // Log the individual completion
        $stmt = $pdo->prepare("
            INSERT INTO task_history (
                task_id,
                user_id,
                action,
                details,
                created_at
            ) VALUES (?, ?, 'marked_complete', ?, NOW())
        ");
        $stmt->execute([
            $task_id,
            $user_id,
            json_encode([
                'status' => 'individual_completion',
                'pending_users' => array_filter($assignees_status, function($a) {
                    return !$a['completed'];
                })
            ])
        ]);

        $message = 'Your part has been marked as completed. Waiting for other team members to complete their parts.';
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'all_completed' => $completion_stats['total_assignees'] == $completion_stats['completed_count'],
        'assignees_status' => $assignees_status
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 