<?php
function sendNotification($pdo, $user_id, $title, $message, $type, $reference_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, reference_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        // Debug log
        error_log("Inserting notification for user {$user_id}: {$title} - {$message}");
        
        $result = $stmt->execute([$user_id, $title, $message, $type, $reference_id]);
        
        if (!$result) {
            throw new Exception("Failed to insert notification");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in sendNotification: " . $e->getMessage());
        throw $e;
    }
}

function notifyTaskCompletion($pdo, $task_id, $completed_by) {
    // Get task details with project manager and client
    $stmt = $pdo->prepare("
        SELECT 
            t.task_name,
            t.project_id,
            p.service as project_name,
            u.name as completer_name,
            p.client_id,
            (
                SELECT user_id 
                FROM users 
                WHERE role = 'project_manager' 
                LIMIT 1
            ) as project_manager_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.project_id
        JOIN users u ON u.user_id = ?
        WHERE t.task_id = ?
    ");
    $stmt->execute([$completed_by, $task_id]);
    $taskInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all task assignees except the completer
    $stmt = $pdo->prepare("
        SELECT DISTINCT ta.user_id
        FROM task_assignees ta
        WHERE ta.task_id = ? AND ta.user_id != ?
    ");
    $stmt->execute([$task_id, $completed_by]);
    $assignees = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Notification message
    $title = "Task Completed: {$taskInfo['task_name']}";
    $message = "{$taskInfo['completer_name']} has completed the task '{$taskInfo['task_name']}' in project '{$taskInfo['project_name']}'";

    // Notify other assignees
    foreach ($assignees as $assignee_id) {
        sendNotification($pdo, $assignee_id, $title, $message, 'task', $task_id);
    }

    // Notify project manager if exists
    if ($taskInfo['project_manager_id']) {
        sendNotification(
            $pdo, 
            $taskInfo['project_manager_id'], 
            $title,
            $message,
            'task',
            $task_id
        );
    }

    // Notify client
    if ($taskInfo['client_id']) {
        $clientMessage = "A task '{$taskInfo['task_name']}' in your project '{$taskInfo['project_name']}' has been completed.";
        sendNotification(
            $pdo,
            $taskInfo['client_id'],
            $title,
            $clientMessage,
            'task',
            $task_id
        );
    }
}

function notifyTaskAssignment($pdo, $task_id, $assigned_by) {
    try {
        // Get task and assigner details
        $stmt = $pdo->prepare("
            SELECT 
                t.task_name,
                t.description,
                t.due_date,
                t.project_id,
                p.service as project_name,
                p.client_id,
                u.name as assigner_name,
                u.role as assigner_role
            FROM tasks t
            JOIN projects p ON t.project_id = p.project_id
            JOIN users u ON u.user_id = ?
            WHERE t.task_id = ?
        ");
        $stmt->execute([$assigned_by, $task_id]);
        $taskInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$taskInfo) {
            throw new Exception("Task information not found");
        }

        // Get all assignees
        $stmt = $pdo->prepare("
            SELECT 
                ta.user_id,
                u.name as assignee_name,
                u.role as assignee_role
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.user_id
            WHERE ta.task_id = ?
        ");
        $stmt->execute([$task_id]);
        $assignees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($assignees)) {
            throw new Exception("No assignees found for the task");
        }

        // Notification details
        $title = "New Task Assignment: {$taskInfo['task_name']}";
        $formattedDueDate = date('M j, Y', strtotime($taskInfo['due_date']));
        
        // Send notification to each assignee
        foreach ($assignees as $assignee) {
            $message = "You have been assigned to task '{$taskInfo['task_name']}' in project '{$taskInfo['project_name']}' by {$taskInfo['assigner_name']} ({$taskInfo['assigner_role']}). Due date: {$formattedDueDate}";

            // Debug log
            error_log("Sending notification to user {$assignee['user_id']}: {$message}");
            
            sendNotification(
                $pdo, 
                $assignee['user_id'], 
                $title,
                $message,
                'task',
                $task_id
            );
        }

        // Notify project manager if the assigner is not the project manager
        if ($taskInfo['assigner_role'] !== 'project_manager') {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE role = 'project_manager' LIMIT 1");
            $stmt->execute();
            if ($manager = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $assigneeNames = implode(', ', array_column($assignees, 'assignee_name'));
                $managerMessage = "New task '{$taskInfo['task_name']}' has been created by {$taskInfo['assigner_name']} and assigned to: {$assigneeNames}";
                
                sendNotification(
                    $pdo, 
                    $manager['user_id'],
                    $title,
                    $managerMessage,
                    'task',
                    $task_id
                );
            }
        }

        // Notify client about task assignment
        if ($taskInfo['client_id']) {
            $clientMessage = "A new task '{$taskInfo['task_name']}' has been assigned in your project '{$taskInfo['project_name']}'. Expected completion: {$formattedDueDate}";
            sendNotification(
                $pdo,
                $taskInfo['client_id'],
                $title,
                $clientMessage,
                'task',
                $task_id
            );
        }

    } catch (Exception $e) {
        error_log("Error in notifyTaskAssignment: " . $e->getMessage());
        throw $e;
    }
}
?> 