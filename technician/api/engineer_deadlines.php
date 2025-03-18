<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is an technician
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'technician') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];

    // Get upcoming deadlines (next 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            p.project_name,
            p.service
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        JOIN projects p ON t.project_id = p.project_id
        WHERE ta.user_id = ?
        AND t.status = 'pending'
        AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY t.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['deadlines' => $deadlines]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 