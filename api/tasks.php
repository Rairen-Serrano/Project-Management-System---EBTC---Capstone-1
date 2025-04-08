<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No action specified']);
    exit;
}

$action = $_GET['action'];
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// Timeline action
if ($action === 'timeline') {
    try {
        // Log the project ID for debugging
        error_log("Processing timeline for project ID: " . $project_id);

        // Get project details
        $stmt = $pdo->prepare("
            SELECT project_id, start_date, end_date, service
            FROM projects 
            WHERE project_id = ?
        ");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            throw new Exception('Project not found');
        }

        // Get tasks with categories - using created_at instead of due_date
        $stmt = $pdo->prepare("
            SELECT 
                t.task_id,
                t.task_name,
                t.description,
                DATE(t.created_at) as created_date,  -- Get only the date part
                t.status,
                tc.category_name
            FROM tasks t
            LEFT JOIN task_categories tc ON t.category_id = tc.category_id
            WHERE t.project_id = ?
            ORDER BY t.created_at ASC
        ");
        $stmt->execute([$project_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log the number of tasks found
        error_log("Found " . count($tasks) . " tasks");

        // Get assignees for each task
        foreach ($tasks as &$task) {
            $stmt = $pdo->prepare("
                SELECT 
                    u.user_id,
                    u.name,
                    u.email,
                    u.role
                FROM task_assignees ta
                JOIN users u ON ta.user_id = u.user_id
                WHERE ta.task_id = ?
            ");
            $stmt->execute([$task['task_id']]);
            $task['assignees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Send response
        $response = [
            'success' => true,
            'project' => $project,
            'tasks' => $tasks
        ];

        // Log the response for debugging
        error_log("Sending response: " . json_encode($response));

        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        error_log("Timeline error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Categories action
if ($action === 'categories') {
    if (!isset($_GET['project_id'])) {
        echo json_encode(['success' => false, 'message' => 'Project ID is required']);
        exit;
    }

    $project_id = $_GET['project_id'];
    
    try {
        // Get all categories ordered by creation date
        $stmt = $pdo->prepare("
            SELECT 
                tc.category_id,
                tc.category_name,
                tc.status,
                tc.created_at,
                CASE 
                    WHEN tc.status = 'completed' THEN true
                    WHEN (
                        SELECT COUNT(*) 
                        FROM task_categories prev 
                        WHERE prev.project_id = tc.project_id 
                        AND prev.created_at < tc.created_at 
                        AND prev.status != 'completed'
                    ) = 0 THEN true
                    ELSE false
                END as is_available
            FROM task_categories tc
            WHERE tc.project_id = ?
            ORDER BY tc.created_at ASC
        ");
        
        $stmt->execute([$project_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
    } catch (PDOException $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load categories'
        ]);
    }
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if previous categories are completed
if ($action === 'check_previous_categories') {
    $category_id = $_GET['category_id'];
    
    // Get all categories up to the current one
    $stmt = $pdo->prepare("
        SELECT category_id, status
        FROM task_categories
        WHERE project_id = ? AND created_at <= (
            SELECT created_at FROM task_categories WHERE category_id = ?
        )
        ORDER BY created_at ASC
    ");
    $stmt->execute([$project_id, $category_id]);
    $categories = $stmt->fetchAll();
    
    // Check if all previous categories are completed
    $can_proceed = true;
    foreach ($categories as $category) {
        if ($category['category_id'] == $category_id) {
            break;
        }
        if ($category['status'] !== 'completed') {
            $can_proceed = false;
            break;
        }
    }
    
    echo json_encode(['can_proceed' => $can_proceed]);
    exit;
}

// Complete a category
if ($action === 'complete_category') {
    try {
        // Check if category_id is provided
        if (!isset($_GET['category_id'])) {
            throw new Exception('Category ID is required');
        }
        
        $category_id = $_GET['category_id'];
        
        // First, check if all non-archived tasks in this category are completed
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as incomplete_tasks 
            FROM tasks 
            WHERE category_id = ? 
            AND status != 'completed'
            AND (is_archived = 0 OR is_archived IS NULL)
        ");
        $stmt->execute([$category_id]);
        $result = $stmt->fetch();
        
        if ($result['incomplete_tasks'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'All tasks in this category must be completed before marking the category as complete'
            ]);
            exit;
        }
        
        // Update the category status to completed
        $stmt = $pdo->prepare("
            UPDATE task_categories 
            SET status = 'completed' 
            WHERE category_id = ? 
            AND project_id = ?
        ");
        $stmt->execute([$category_id, $project_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Category marked as complete successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Check if a task can be updated
if ($action === 'can_update_task') {
    $task_id = $_GET['task_id'];
    
    // Get the category of this task and all previous categories
    $stmt = $pdo->prepare("
        SELECT tc.status
        FROM tasks t
        JOIN task_categories tc ON t.category_id = tc.category_id
        WHERE t.task_id = ?
    ");
    $stmt->execute([$task_id]);
    $taskCategory = $stmt->fetch();
    
    $can_update = true;
    // Add your logic to check if previous categories are completed
    
    echo json_encode(['can_update' => $can_update]);
    exit;
}

// Project members action
if ($action === 'project_members') {
    if (!isset($_GET['project_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Project ID is required']);
        exit;
    }

    $project_id = $_GET['project_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                u.user_id, 
                u.name, 
                u.role
            FROM project_assignees pa
            JOIN users u ON pa.user_id = u.user_id
            WHERE pa.project_id = ?
            AND u.role NOT IN ('client', 'admin')
            ORDER BY u.name ASC
        ");
        
        $stmt->execute([$project_id]);
        $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'personnel' => $personnel
        ]);
        
    } catch (Exception $e) {
        error_log("Error fetching project members: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load project personnel'
        ]);
    }
    exit;
}

// Notify task assignment action
if ($action === 'notify_task_assignment') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        error_log('Received notification data: ' . print_r($data, true));
        
        if (!isset($data['task_id']) || !isset($data['assignees']) || !isset($data['task_name']) || !isset($data['project_id'])) {
            throw new Exception('Missing required notification data: ' . print_r($data, true));
        }

        // Get project details
        $project_stmt = $pdo->prepare("
            SELECT p.service 
            FROM projects p 
            WHERE p.project_id = ?
        ");
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

        // Start transaction for notifications
        $pdo->beginTransaction();

        try {
            // Debug: Log the SQL statement
            error_log("Preparing to insert notifications with the following SQL:");
            error_log("INSERT INTO notifications (user_id, recipient_id, type, reference_id, title, message, is_read, created_at) VALUES (?, ?, 'task', ?, ?, ?, FALSE, NOW())");

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
                // Skip if the assignee is the project manager (current user)
                if ($assignee_id == $assigner['user_id']) {
                    error_log("Skipping notification for self (user_id: {$assignee_id})");
                    continue;
                }
                
                error_log("Attempting to send notification - From user_id: {$_SESSION['user_id']} to recipient_id: {$assignee_id}");
                
                try {
                    $notification_stmt->execute([
                        $_SESSION['user_id'],  // user_id (sender)
                        $assignee_id,          // recipient_id (receiver)
                        $data['task_id'],      // reference_id (task_id)
                        $title,
                        $message
                    ]);
                    error_log("Successfully inserted notification for recipient_id: {$assignee_id}");
                } catch (PDOException $e) {
                    error_log("Failed to insert notification: " . $e->getMessage());
                    throw $e;
                }
            }

            $pdo->commit();
            error_log("Successfully committed all notifications");
            
            echo json_encode([
                'success' => true,
                'message' => 'Notifications sent successfully'
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Rolling back notification transaction: " . $e->getMessage());
            throw new Exception('Failed to send notifications: ' . $e->getMessage());
        }

    } catch (Exception $e) {
        error_log("Error sending task assignment notifications: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Add task action
if ($action === 'add_task') {
    try {
        // Get and validate input data
        $data = json_decode(file_get_contents('php://input'), true);
        error_log('Received task data: ' . print_r($data, true));
        
        // Validate required fields
        if (empty($data['task_name']) || empty($data['category_id']) || 
            empty($data['due_date']) || empty($data['assignees'])) {
            throw new Exception('Missing required fields');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert task
        $task_stmt = $pdo->prepare("
            INSERT INTO tasks (
                project_id, 
                category_id, 
                task_name, 
                description, 
                due_date, 
                status, 
                created_by, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");

        $task_stmt->execute([
            $project_id,
            $data['category_id'],
            $data['task_name'],
            $data['description'] ?? '',
            $data['due_date'],
            $_SESSION['user_id']
        ]);

        $task_id = $pdo->lastInsertId();

        // Insert task assignees
        $assignee_stmt = $pdo->prepare("
            INSERT INTO task_assignees (
                task_id, 
                user_id, 
                assigned_date
            ) VALUES (?, ?, NOW())
        ");

        foreach ($data['assignees'] as $assignee_id) {
            $assignee_stmt->execute([
                $task_id,
                $assignee_id
            ]);
        }

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $task_id
        ]);

    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error adding task: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Delete task action
if ($action === 'delete_task') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        error_log('Delete task data received: ' . print_r($data, true)); // Debug log
        
        if (!isset($data['task_id'])) {
            throw new Exception('Task ID is required');
        }

        // Start transaction
        $pdo->beginTransaction();

        try {
            // Delete task assignees first
            $delete_assignees = $pdo->prepare("
                DELETE FROM task_assignees 
                WHERE task_id = ?
            ");
            $delete_assignees->execute([$data['task_id']]);

            // Delete the task
            $delete_task = $pdo->prepare("
                DELETE FROM tasks 
                WHERE task_id = ? 
                AND project_id = ?
            ");
            $delete_task->execute([$data['task_id'], $project_id]);

            if ($delete_task->rowCount() === 0) {
                throw new Exception('Task not found or not authorized to delete');
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error_log("Error deleting task: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Add this new case for archiving tasks
if ($action === 'archive_task') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['task_id'])) {
            throw new Exception('Task ID is required');
        }

        // First get task details and assignees before archiving
        $task_stmt = $pdo->prepare("
            SELECT t.task_name, t.project_id, GROUP_CONCAT(ta.user_id) as assignee_ids
            FROM tasks t
            LEFT JOIN task_assignees ta ON t.task_id = ta.task_id
            WHERE t.task_id = ?
            GROUP BY t.task_id
        ");
        $task_stmt->execute([$data['task_id']]);
        $task_info = $task_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task_info) {
            throw new Exception('Task not found');
        }

        // Update task to archived status
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET is_archived = 1 
            WHERE task_id = ? AND project_id = ?
        ");
        $stmt->execute([$data['task_id'], $project_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Task not found or already archived');
        }

        // Get project name
        $project_stmt = $pdo->prepare("SELECT service FROM projects WHERE project_id = ?");
        $project_stmt->execute([$project_id]);
        $project = $project_stmt->fetch(PDO::FETCH_ASSOC);

        // Get assigner's name
        $assigner_stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $assigner_stmt->execute([$_SESSION['user_id']]);
        $assigner = $assigner_stmt->fetch(PDO::FETCH_ASSOC);

        // Send notifications to assignees
        if ($task_info['assignee_ids']) {
            $assignees = explode(',', $task_info['assignee_ids']);
            $notification_stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    recipient_id,
                    type,
                    reference_id,
                    title,
                    message,
                    is_read,
                    created_at
                ) VALUES (?, ?, 'task_archived', ?, ?, ?, FALSE, NOW())
            ");

            $title = "Task Archived";
            $message = "Task '{$task_info['task_name']}' in project '{$project['service']}' has been archived by {$assigner['name']}.";

            foreach ($assignees as $assignee_id) {
                if ($assignee_id != $_SESSION['user_id']) { // Don't notify the archiver
                    $notification_stmt->execute([
                        $_SESSION['user_id'],
                        $assignee_id,
                        $data['task_id'],
                        $title,
                        $message
                    ]);
                }
            }
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Task archived successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Modify the existing tasks fetching query in the get_tasks case to exclude archived tasks
if ($action === 'tasks') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                tc.category_name,
                GROUP_CONCAT(DISTINCT CONCAT(u.user_id, ':', u.name) SEPARATOR ',') as assigned_users
            FROM tasks t
            LEFT JOIN task_categories tc ON t.category_id = tc.category_id
            LEFT JOIN task_assignees ta ON t.task_id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.user_id
            WHERE t.project_id = ? 
            AND (t.is_archived = 0 OR t.is_archived IS NULL)
            GROUP BY t.task_id
            ORDER BY t.due_date ASC
        ");
        
        $stmt->execute([$project_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process tasks to include assignees
        foreach ($tasks as &$task) {
            // Get assignees for each task
            $assignee_stmt = $pdo->prepare("
                SELECT 
                    u.user_id,
                    u.name,
                    u.role
                FROM task_assignees ta
                JOIN users u ON ta.user_id = u.user_id
                WHERE ta.task_id = ?
            ");
            $assignee_stmt->execute([$task['task_id']]);
            $task['assignees'] = $assignee_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'tasks' => $tasks
        ]);
    } catch (Exception $e) {
        error_log("Error fetching tasks: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Add this new case for getting archived tasks
if ($action === 'archived_tasks') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                tc.category_name,
                tc.status as category_status,
                GROUP_CONCAT(DISTINCT CONCAT(u.user_id, ':', u.name) SEPARATOR ',') as assigned_users
            FROM tasks t
            LEFT JOIN task_categories tc ON t.category_id = tc.category_id
            LEFT JOIN task_assignees ta ON t.task_id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.user_id
            WHERE t.project_id = ? 
            AND t.is_archived = 1
            GROUP BY t.task_id
            ORDER BY t.due_date ASC
        ");
        
        $stmt->execute([$project_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process tasks to include assignees
        foreach ($tasks as &$task) {
            $assignee_stmt = $pdo->prepare("
                SELECT 
                    u.user_id,
                    u.name,
                    u.role
                FROM task_assignees ta
                JOIN users u ON ta.user_id = u.user_id
                WHERE ta.task_id = ?
                AND EXISTS (
                    SELECT 1 FROM tasks t 
                    WHERE t.task_id = ta.task_id 
                    AND t.project_id = ?
                )
            ");
            $assignee_stmt->execute([$task['task_id'], $project_id]);
            $task['assignees'] = $assignee_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'tasks' => $tasks
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Add this new case for returning tasks
if ($action === 'return_task') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['task_id'])) {
            throw new Exception('Task ID is required');
        }

        // Update task to remove archived status
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET is_archived = 0 
            WHERE task_id = ? AND project_id = ?
        ");
        $stmt->execute([$data['task_id'], $project_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Task not found or already returned');
        }

        // Get task details for notification
        $task_stmt = $pdo->prepare("
            SELECT t.task_name, GROUP_CONCAT(ta.user_id) as assignee_ids
            FROM tasks t
            LEFT JOIN task_assignees ta ON t.task_id = ta.task_id
            WHERE t.task_id = ?
            GROUP BY t.task_id
        ");
        $task_stmt->execute([$data['task_id']]);
        $task_info = $task_stmt->fetch(PDO::FETCH_ASSOC);

        // Send notifications to assignees about the returned task
        if ($task_info && $task_info['assignee_ids']) {
            $assignees = explode(',', $task_info['assignee_ids']);
            $notification_stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    recipient_id,
                    type,
                    reference_id,
                    title,
                    message,
                    is_read,
                    created_at
                ) VALUES (?, ?, 'task_returned', ?, ?, ?, FALSE, NOW())
            ");

            $title = "Task Returned";
            $message = "Task '{$task_info['task_name']}' has been returned to active tasks.";

            foreach ($assignees as $assignee_id) {
                if ($assignee_id != $_SESSION['user_id']) {
                    $notification_stmt->execute([
                        $_SESSION['user_id'],
                        $assignee_id,
                        $data['task_id'],
                        $title,
                        $message
                    ]);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Task returned successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Add this new case for checking category status
if ($action === 'check_category_status') {
    try {
        if (!isset($_GET['task_id'])) {
            throw new Exception('Task ID is required');
        }

        $task_id = $_GET['task_id'];

        // Get the category status for the task
        $stmt = $pdo->prepare("
            SELECT tc.status as category_status
            FROM tasks t
            JOIN task_categories tc ON t.category_id = tc.category_id
            WHERE t.task_id = ?
        ");
        $stmt->execute([$task_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception('Task or category not found');
        }

        echo json_encode([
            'success' => true,
            'is_category_completed' => ($result['category_status'] === 'completed')
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Get task details
if ($action === 'get_task_details') {
    try {
        if (!isset($_GET['task_id'])) {
            throw new Exception('Task ID is required');
        }

        $task_id = $_GET['task_id'];

        // Get task details with category information
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                tc.category_id,
                tc.category_name,
                tc.status as category_status
            FROM tasks t
            LEFT JOIN task_categories tc ON t.category_id = tc.category_id
            WHERE t.task_id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new Exception('Task not found');
        }

        // Get assignees for the task
        $assignee_stmt = $pdo->prepare("
            SELECT 
                u.user_id,
                u.name,
                u.role
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.user_id
            WHERE ta.task_id = ?
        ");
        $assignee_stmt->execute([$task_id]);
        $task['assignees'] = $assignee_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'task' => $task
        ]);

    } catch (Exception $e) {
        error_log("Error getting task details: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'project_members') {
        try {
            $project_id = $_GET['project_id'];
            
            // Get project members from project_assignees
            $stmt = $pdo->prepare("
                SELECT 
                    u.user_id,
                    u.name,
                    u.role
                FROM project_assignees pa
                JOIN users u ON pa.user_id = u.user_id
                WHERE pa.project_id = ?
                ORDER BY u.name ASC
            ");
            
            $stmt->execute([$project_id]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'members' => $members
            ]);
        } catch (PDOException $e) {
            error_log("Error in tasks.php: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database error occurred'
            ]);
        }
        exit;
    }
}

// Handle other actions... 