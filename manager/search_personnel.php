<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required data is provided
if (!isset($_POST['search_term']) || !isset($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    $search_term = '%' . $_POST['search_term'] . '%';
    $project_id = $_POST['project_id'];

    // Search for personnel not already assigned to the project
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.email, u.role
        FROM users u
        WHERE (u.role NOT IN ('client', 'admin', 'project_manager'))
        AND (u.name LIKE :search_term OR u.email LIKE :search_term)
        AND u.user_id NOT IN (
            SELECT user_id 
            FROM project_personnel 
            WHERE project_id = :project_id
        )
        ORDER BY u.name ASC
        LIMIT 10
    ");

    $stmt->execute([
        ':search_term' => $search_term,
        ':project_id' => $project_id
    ]);

    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'personnel' => $personnel
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 