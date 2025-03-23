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

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client' || !isset($_GET['project_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

try {
    // Get project details
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            GROUP_CONCAT(DISTINCT CONCAT(u.name, '|', u.role, '|', u.email, '|', u.phone) SEPARATOR '||') as assigned_personnel
        FROM projects p
        LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
        LEFT JOIN users u ON pa.user_id = u.user_id
        WHERE p.project_id = ? AND p.client_id = ?
        GROUP BY p.project_id
    ");
    $stmt->execute([$_GET['project_id'], $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        http_response_code(404);
        exit('Project not found');
    }

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
            'completed_tasks' => count($completedTasks),
            'total_tasks' => count($tasks)
        ],
        'personnel' => $personnel,
        'categories' => $categories,
        'tasks' => $tasks
    ];

    // Send response
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
} 