<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost($data);
        break;
    case 'PUT':
        handlePut($data);
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet() {
    global $pdo;
    
    $action = $_GET['action'] ?? '';
    $project_id = $_GET['project_id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID is required']);
        return;
    }

    try {
        switch ($action) {
            case 'categories':
                // Get task categories
                $stmt = $pdo->prepare("
                    SELECT * FROM task_categories 
                    WHERE project_id = ?
                    ORDER BY category_name
                ");
                $stmt->execute([$project_id]);
                echo json_encode(['categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            case 'tasks':
                // Get tasks with category and assignee details
                $stmt = $pdo->prepare("
                    SELECT 
                        t.*,
                        tc.category_name,
                        GROUP_CONCAT(DISTINCT CONCAT(u.name, '|', u.email) SEPARATOR '||') as assignees
                    FROM tasks t
                    LEFT JOIN task_categories tc ON t.category_id = tc.category_id
                    LEFT JOIN task_assignees ta ON t.task_id = ta.task_id
                    LEFT JOIN users u ON ta.user_id = u.user_id
                    WHERE t.project_id = ?
                    GROUP BY t.task_id
                    ORDER BY t.due_date
                ");
                $stmt->execute([$project_id]);
                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Process assignees
                foreach ($tasks as &$task) {
                    $assigneesList = [];
                    if (!empty($task['assignees'])) {
                        $assignees = explode('||', $task['assignees']);
                        foreach ($assignees as $assignee) {
                            list($name, $email) = explode('|', $assignee);
                            $assigneesList[] = [
                                'name' => $name,
                                'email' => $email
                            ];
                        }
                    }
                    $task['assignees'] = $assigneesList;
                }
                
                echo json_encode(['tasks' => $tasks]);
                break;

            case 'progress':
                // Get project progress statistics
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_tasks,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                    FROM tasks
                    WHERE project_id = ?
                ");
                $stmt->execute([$project_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $progress = [
                    'total_tasks' => (int)$stats['total_tasks'],
                    'completed_tasks' => (int)$stats['completed_tasks'],
                    'pending_tasks' => (int)$stats['total_tasks'] - (int)$stats['completed_tasks'],
                    'progress_percentage' => $stats['total_tasks'] > 0 
                        ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) 
                        : 0
                ];
                
                echo json_encode(['progress' => $progress]);
                break;

            case 'timeline':
                // Get tasks for timeline view
                $stmt = $pdo->prepare("
                    SELECT 
                        t.*,
                        tc.category_name,
                        u.name as assignee_name
                    FROM tasks t
                    LEFT JOIN task_categories tc ON t.category_id = tc.category_id
                    LEFT JOIN users u ON t.assignee_id = u.user_id
                    WHERE t.project_id = ?
                    ORDER BY t.due_date
                ");
                $stmt->execute([$project_id]);
                echo json_encode(['timeline' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            case 'personnel':
                // Get task ID if provided
                $task_id = $_GET['task_id'] ?? null;
                
                // Base query to get project personnel
                $query = "
                    SELECT DISTINCT u.user_id, u.name, u.role
                    FROM project_personnel pp
                    JOIN users u ON pp.user_id = u.user_id
                    WHERE pp.project_id = ?
                    AND u.role NOT IN ('client', 'admin', 'project_manager')
                ";
                
                // If task ID is provided, exclude personnel already assigned to the task
                if ($task_id) {
                    $query .= " AND u.user_id NOT IN (
                        SELECT user_id FROM task_assignees WHERE task_id = ?
                    )";
                }
                
                $query .= " ORDER BY u.name ASC";
                
                $stmt = $pdo->prepare($query);
                if ($task_id) {
                    $stmt->execute([$project_id, $task_id]);
                } else {
                    $stmt->execute([$project_id]);
                }
                
                echo json_encode(['personnel' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePost($data) {
    global $pdo;
    
    $action = $_GET['action'] ?? '';
    $project_id = $_GET['project_id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID is required']);
        return;
    }

    try {
        switch ($action) {
            case 'category':
                // Validate required fields
                if (empty($data['category_name'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Category name is required']);
                    return;
                }

                // Add new category
                $stmt = $pdo->prepare("
                    INSERT INTO task_categories (project_id, category_name, description)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $project_id,
                    $data['category_name'],
                    $data['description'] ?? null
                ]);
                
                echo json_encode([
                    'success' => true,
                    'category_id' => $pdo->lastInsertId()
                ]);
                break;

            case 'task':
                // Validate required fields
                if (empty($data['task_name']) || empty($data['category_id']) || 
                    !isset($data['assignees']) || !is_array($data['assignees']) || empty($data['assignees']) || 
                    empty($data['due_date'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    return;
                }

                // Validate that all assignees are project personnel
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM project_personnel
                    WHERE project_id = ? AND user_id IN (" . implode(',', array_fill(0, count($data['assignees']), '?')) . ")
                ");
                $params = array_merge([$project_id], $data['assignees']);
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] !== count($data['assignees'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid assignees. Only project personnel can be assigned to tasks.']);
                    return;
                }

                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Add new task
                    $stmt = $pdo->prepare("
                        INSERT INTO tasks (
                            project_id, category_id, task_name, description,
                            due_date, status, created_at
                        )
                        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $project_id,
                        $data['category_id'],
                        $data['task_name'],
                        $data['description'] ?? null,
                        $data['due_date']
                    ]);
                    
                    $taskId = $pdo->lastInsertId();

                    // Add task assignees
                    $stmt = $pdo->prepare("
                        INSERT INTO task_assignees (task_id, user_id)
                        VALUES (?, ?)
                    ");

                    foreach ($data['assignees'] as $assigneeId) {
                        $stmt->execute([$taskId, $assigneeId]);
                    }

                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'task_id' => $taskId
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;

            case 'add_assignee':
                if (empty($data['task_id']) || empty($data['user_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task ID and User ID are required']);
                    return;
                }

                // Verify task belongs to project
                $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
                $stmt->execute([$data['task_id']]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$task || $task['project_id'] != $project_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid task']);
                    return;
                }

                // Verify user is project personnel
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM project_personnel 
                    WHERE project_id = ? AND user_id = ?
                ");
                $stmt->execute([$project_id, $data['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] === 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid assignee. Only project personnel can be assigned to tasks.']);
                    return;
                }

                // Add assignee
                $stmt = $pdo->prepare("
                    INSERT INTO task_assignees (task_id, user_id)
                    SELECT ?, ?
                    WHERE NOT EXISTS (
                        SELECT 1 FROM task_assignees 
                        WHERE task_id = ? AND user_id = ?
                    )
                ");
                $stmt->execute([
                    $data['task_id'], 
                    $data['user_id'],
                    $data['task_id'],
                    $data['user_id']
                ]);

                echo json_encode(['success' => true]);
                break;

            case 'remove_assignee':
                if (empty($data['task_id']) || empty($data['user_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task ID and User ID are required']);
                    return;
                }

                // Verify task belongs to project
                $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
                $stmt->execute([$data['task_id']]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$task || $task['project_id'] != $project_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid task']);
                    return;
                }

                // Remove assignee
                $stmt = $pdo->prepare("
                    DELETE FROM task_assignees 
                    WHERE task_id = ? AND user_id = ?
                ");
                $stmt->execute([$data['task_id'], $data['user_id']]);

                echo json_encode(['success' => true]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePut($data) {
    global $pdo;
    
    $action = $_GET['action'] ?? '';
    $project_id = $_GET['project_id'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if (!$project_id || !$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and item ID are required']);
        return;
    }

    try {
        switch ($action) {
            case 'task':
                // Update task
                $updateFields = [];
                $params = [$project_id, $id];
                
                if (isset($data['task_name'])) {
                    $updateFields[] = "task_name = ?";
                    $params[] = $data['task_name'];
                }
                if (isset($data['description'])) {
                    $updateFields[] = "description = ?";
                    $params[] = $data['description'];
                }
                if (isset($data['category_id'])) {
                    $updateFields[] = "category_id = ?";
                    $params[] = $data['category_id'];
                }
                if (isset($data['assignee_id'])) {
                    $updateFields[] = "assignee_id = ?";
                    $params[] = $data['assignee_id'];
                }
                if (isset($data['due_date'])) {
                    $updateFields[] = "due_date = ?";
                    $params[] = $data['due_date'];
                }
                if (isset($data['status'])) {
                    $updateFields[] = "status = ?";
                    $params[] = $data['status'];
                }

                if (empty($updateFields)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No fields to update']);
                    return;
                }

                $sql = "UPDATE tasks SET " . implode(", ", $updateFields) . 
                       " WHERE project_id = ? AND task_id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['success' => true]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete() {
    global $pdo;
    
    $action = $_GET['action'] ?? '';
    $project_id = $_GET['project_id'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if (!$project_id || !$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and Task/Category ID are required']);
        return;
    }

    try {
        switch ($action) {
            case 'task':
                // Verify task belongs to project
                $stmt = $pdo->prepare("SELECT task_id FROM tasks WHERE project_id = ? AND task_id = ?");
                $stmt->execute([$project_id, $id]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task not found']);
                    return;
                }

                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Delete task assignees first
                    $stmt = $pdo->prepare("DELETE FROM task_assignees WHERE task_id = ?");
                    $stmt->execute([$id]);

                    // Delete task
                    $stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ?");
                    $stmt->execute([$id]);

                    $pdo->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 