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

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    die(json_encode(['error' => 'Unauthorized access']));
}

if (!isset($_GET['project_id'])) {
    die(json_encode(['error' => 'Project ID is required']));
}

try {
    // Update the query to include contract_file and budget_file
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            GROUP_CONCAT(DISTINCT CONCAT(u.name, '|', u.role, '|', u.email, '|', u.phone) SEPARATOR '||') as assigned_personnel,
            (
                SELECT COUNT(*) 
                FROM tasks t 
                WHERE t.project_id = p.project_id AND t.status = 'completed'
            ) as completed_tasks,
            (
                SELECT COUNT(*) 
                FROM tasks t 
                WHERE t.project_id = p.project_id
            ) as total_tasks
        FROM projects p
        LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
        LEFT JOIN users u ON pa.user_id = u.user_id
        WHERE p.project_id = ? AND p.client_id = ?
        GROUP BY p.project_id
    ");

    $stmt->execute([$_GET['project_id'], $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        die(json_encode(['error' => 'Project not found']));
    }

    // Debug: Log the project data
    error_log('Project data: ' . print_r($project, true));

    // Get project categories
    $categoryStmt = $pdo->prepare("
        SELECT 
            category_id,
            category_name,
            description,
            status
        FROM task_categories
        WHERE project_id = ?
        ORDER BY category_id ASC
    ");
    $categoryStmt->execute([$_GET['project_id']]);
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get project tasks with category and assignee information
    $taskStmt = $pdo->prepare("
        SELECT 
            t.*,
            tc.category_name as category,
            tc.category_id,
            GROUP_CONCAT(DISTINCT u.name) as assigned_to
        FROM tasks t
        LEFT JOIN task_categories tc ON t.category_id = tc.category_id
        LEFT JOIN task_assignees ta ON t.task_id = ta.task_id
        LEFT JOIN users u ON ta.user_id = u.user_id
        WHERE t.project_id = ?
        GROUP BY t.task_id
        ORDER BY tc.category_id ASC, t.due_date ASC
    ");
    $taskStmt->execute([$_GET['project_id']]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format personnel data
    $personnel = [];
    if ($project['assigned_personnel']) {
        foreach (explode('||', $project['assigned_personnel']) as $person) {
            list($name, $role, $email, $phone) = explode('|', $person);
            $personnel[] = [
                'name' => $name,
                'role' => $role,
                'email' => $email,
                'phone' => $phone
            ];
        }
    }

    // Calculate task statistics
    $completedTasks = array_filter($tasks, function($task) {
        return $task['status'] === 'completed';
    });

    // Prepare response
    $response = [
        'project' => [
            'project_id' => $project['project_id'],
            'service' => $project['service'],
            'start_date' => $project['start_date'],
            'end_date' => $project['end_date'],
            'notes' => $project['notes'],
            'status' => $project['status'],
            'quotation_file' => $project['quotation_file'],
            'contract_file' => $project['contract_file'],
            'budget_file' => $project['budget_file'],
            'completed_tasks' => $project['completed_tasks'],
            'total_tasks' => $project['total_tasks']
        ],
        'personnel' => $personnel,
        'categories' => $categories,
        'tasks' => $tasks
    ];

    // Send response
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    error_log('Error in get_project_details.php: ' . $e->getMessage());
    die(json_encode(['error' => 'An error occurred while fetching project details']));
} 