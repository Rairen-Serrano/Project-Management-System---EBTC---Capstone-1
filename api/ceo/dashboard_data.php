<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'ceo') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $data = [
        'success' => true,
        'data' => []
    ];

    // Get total projects with error checking
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
    if ($stmt === false) {
        throw new Exception("Error executing projects query: " . print_r($pdo->errorInfo(), true));
    }
    $data['data']['total_projects'] = $stmt->fetchColumn();
    error_log("Total projects: " . $data['data']['total_projects']);

    // Get total employees with error checking
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role NOT IN ('client', 'admin')");
    if ($stmt === false) {
        throw new Exception("Error executing employees query: " . print_r($pdo->errorInfo(), true));
    }
    $data['data']['total_employees'] = $stmt->fetchColumn();
    error_log("Total employees: " . $data['data']['total_employees']);

    // Get active projects with error checking
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status = 'ongoing'");
    if ($stmt === false) {
        throw new Exception("Error executing active projects query: " . print_r($pdo->errorInfo(), true));
    }
    $data['data']['active_projects'] = $stmt->fetchColumn();
    error_log("Active projects: " . $data['data']['active_projects']);

    // Get total clients with error checking
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'client'");
    if ($stmt === false) {
        throw new Exception("Error executing clients query: " . print_r($pdo->errorInfo(), true));
    }
    $data['data']['total_clients'] = $stmt->fetchColumn();
    error_log("Total clients: " . $data['data']['total_clients']);

    // Get recent projects with more details
    $stmt = $pdo->query("
        SELECT 
            p.project_id,
            p.service,
            p.end_date,
            u.name as client_name,
            p.status,
            GROUP_CONCAT(DISTINCT CONCAT(u2.name, ':', u2.role) SEPARATOR '|') as team_members,
            COALESCE(
                (SELECT COUNT(*) 
                FROM task_categories tc 
                WHERE tc.project_id = p.project_id AND tc.status = 'completed') * 100.0 / 
                NULLIF((SELECT COUNT(*) 
                FROM task_categories tc2 
                WHERE tc2.project_id = p.project_id), 0),
            0) as progress
        FROM projects p
        JOIN users u ON p.client_id = u.user_id
        LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
        LEFT JOIN users u2 ON pa.user_id = u2.user_id
        GROUP BY p.project_id, p.service, p.end_date, u.name, p.status
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $data['data']['recent_projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get employee status with current projects
    $stmt = $pdo->query("
        SELECT 
            u.user_id,
            u.name,
            u.role,
            u.status as user_status,
            COUNT(DISTINCT pa.project_id) as active_projects,
            GROUP_CONCAT(
                DISTINCT CONCAT(p.service, ' (', p.status, ')')
                ORDER BY p.end_date ASC
                SEPARATOR '|'
            ) as project_details
        FROM users u
        LEFT JOIN project_assignees pa ON u.user_id = pa.user_id
        LEFT JOIN projects p ON pa.project_id = p.project_id AND p.status = 'ongoing'
        WHERE u.role NOT IN ('client', 'admin', 'ceo')
        GROUP BY u.user_id, u.name, u.role, u.status
        ORDER BY u.role, u.name
    ");

    if ($stmt === false) {
        throw new Exception("Error executing employee status query: " . print_r($pdo->errorInfo(), true));
    }

    $data['data']['employee_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Employee status loaded: " . count($data['data']['employee_status']));

    // Get recent activities with more context
    $stmt = $pdo->query("
        SELECT 
            p.service as project_name,
            tc.category_name,
            tc.status,
            tc.created_at as activity_time,
            u.name as actor_name,
            u.role as actor_role,
            'task_update' as activity_type
        FROM task_categories tc
        JOIN projects p ON tc.project_id = p.project_id
        JOIN users u ON p.client_id = u.user_id
        WHERE tc.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 
            p.service as project_name,
            NULL as category_name,
            p.status,
            p.updated_at as activity_time,
            u.name as actor_name,
            u.role as actor_role,
            'project_update' as activity_type
        FROM projects p
        JOIN users u ON p.client_id = u.user_id
        WHERE p.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY activity_time DESC
        LIMIT 10
    ");
    
    if ($stmt === false) {
        throw new Exception("Error executing recent activities query: " . print_r($pdo->errorInfo(), true));
    }
    
    $data['data']['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Recent activities loaded: " . count($data['data']['recent_activities']));

    // Log the final data array
    error_log("Final data array: " . print_r($data, true));

    echo json_encode($data);

} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard data: ' . $e->getMessage(),
        'details' => $pdo->errorInfo()
    ]);
    exit;
}
?>
