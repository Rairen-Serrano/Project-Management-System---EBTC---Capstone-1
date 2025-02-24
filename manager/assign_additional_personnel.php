<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required data is provided
if (!isset($_POST['project_id']) || !isset($_POST['personnel'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    $project_id = $_POST['project_id'];
    $personnel = json_decode($_POST['personnel'], true);

    if (!is_array($personnel) || empty($personnel)) {
        throw new Exception('Invalid personnel data');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Check if project exists
    $stmt = $pdo->prepare("SELECT project_id FROM projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Project not found');
    }

    // Prepare insert statement for project personnel
    $stmt = $pdo->prepare("
        INSERT INTO project_personnel (project_id, user_id) 
        SELECT :project_id, :user_id 
        WHERE NOT EXISTS (
            SELECT 1 
            FROM project_personnel 
            WHERE project_id = :project_id 
            AND user_id = :user_id
        )
    ");

    // Insert each personnel
    foreach ($personnel as $user_id) {
        $stmt->execute([
            ':project_id' => $project_id,
            ':user_id' => $user_id
        ]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Personnel assigned successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 