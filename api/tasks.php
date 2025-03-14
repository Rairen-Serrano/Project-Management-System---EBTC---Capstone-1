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
        $stmt = $pdo->prepare("
            SELECT 
                category_id,
                category_name,
                status,
                created_at
            FROM task_categories 
            WHERE project_id = ?
            ORDER BY created_at ASC
        ");
        
        $stmt->execute([$project_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
    } catch (PDOException $e) {
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
        
        // First, check if all tasks in this category are completed
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as incomplete_tasks 
            FROM tasks 
            WHERE category_id = ? 
            AND status != 'completed'
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
        echo json_encode(['success' => false, 'message' => 'Project ID is required']);
        exit;
    }

    $project_id = $_GET['project_id'];
    
    try {
        // Get all users assigned to the project
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                u.user_id,
                u.name,
                u.role
            FROM users u
            INNER JOIN project_assignees pa ON u.user_id = pa.user_id
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
        error_log("Error in project_members endpoint: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load project members'
        ]);
    }
    exit;
}

// ... rest of your existing API code ... 