<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing project ID']);
    exit;
}

try {
    $project_id = $_GET['project_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify that the user is part of the project
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE t.project_id = ? AND ta.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$project_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to view team members in this project']);
        exit;
    }
    
    // Get team members
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            u.user_id,
            u.name,
            u.role,
            (
                SELECT COUNT(*)
                FROM tasks t2
                JOIN task_assignees ta2 ON t2.task_id = ta2.task_id
                WHERE t2.project_id = ?
                AND ta2.user_id = u.user_id
                AND t2.status = 'completed'
            ) as completed_tasks,
            (
                SELECT COUNT(*)
                FROM tasks t2
                JOIN task_assignees ta2 ON t2.task_id = ta2.task_id
                WHERE t2.project_id = ?
                AND ta2.user_id = u.user_id
            ) as total_tasks
        FROM users u
        JOIN task_assignees ta ON u.user_id = ta.user_id
        JOIN tasks t ON ta.task_id = t.task_id
        WHERE t.project_id = ?
        ORDER BY u.name ASC
    ");
    
    $stmt->execute([$project_id, $project_id, $project_id]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate completion rate for each member
    foreach ($team_members as &$member) {
        $member['completion_rate'] = $member['total_tasks'] > 0 
            ? round(($member['completed_tasks'] / $member['total_tasks']) * 100) 
            : 0;
    }
    
    echo json_encode([
        'success' => true,
        'team_members' => $team_members
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 