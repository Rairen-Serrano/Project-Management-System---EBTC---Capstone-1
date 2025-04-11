<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get project ID from query parameters
$projectId = $_GET['project_id'] ?? null;

if (!$projectId) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID is required']);
    exit;
}

try {
    // Get users who are not assigned to this project
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.email, u.role, u.active_projects
        FROM users u
        WHERE u.role IN ('engineer', 'technician', 'worker')
        AND u.user_id NOT IN (
            SELECT pa.user_id 
            FROM project_assignees pa 
            WHERE pa.project_id = ?
        )
        AND (
            (u.role = 'engineer' AND u.active_projects < 3) OR
            (u.role = 'technician' AND u.active_projects < 2) OR
            (u.role = 'worker' AND u.active_projects < 1)
        )
        ORDER BY 
            CASE u.role 
                WHEN 'engineer' THEN 1
                WHEN 'technician' THEN 2
                WHEN 'worker' THEN 3
            END,
            u.name
    ");
    
    $stmt->execute([$projectId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} 