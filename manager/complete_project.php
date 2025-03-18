<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $project_id = $_POST['project_id'] ?? null;

    if (!$project_id) {
        throw new Exception('Project ID is required');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Get project and client information
    $project_query = $pdo->prepare("
        SELECT p.*, a.client_id, a.service, u.name as client_name
        FROM projects p
        JOIN appointments a ON p.appointment_id = a.appointment_id
        JOIN users u ON a.client_id = u.user_id
        WHERE p.project_id = ?
    ");
    $project_query->execute([$project_id]);
    $project = $project_query->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Project not found');
    }

    // Get assigned personnel
    $personnel_query = $pdo->prepare("
        SELECT pa.user_id, u.name
        FROM project_assignees pa
        JOIN users u ON pa.user_id = u.user_id
        WHERE pa.project_id = ?
    ");
    $personnel_query->execute([$project_id]);
    $assigned_personnel = $personnel_query->fetchAll(PDO::FETCH_ASSOC);

    // Update project status
    $update_stmt = $pdo->prepare("
        UPDATE projects 
        SET status = 'completed', 
            completed_at = NOW() 
        WHERE project_id = ?
    ");
    $update_stmt->execute([$project_id]);

    // Prepare notification statement
    $notification_stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id, recipient_id, type, reference_id, 
            title, message, is_read, created_at
        ) VALUES (
            ?, ?, 'project', ?, ?, ?, FALSE, NOW()
        )
    ");

    // Send notification to client
    $client_title = "Project Completed";
    $client_message = "Your project '{$project['service']}' has been marked as completed. Thank you for choosing our services!";
    
    $notification_stmt->execute([
        $_SESSION['user_id'],      // project manager who completed
        $project['client_id'],     // client to notify
        $project_id,
        $client_title,
        $client_message
    ]);

    // Send notifications to all assigned personnel
    $personnel_title = "Project Completion Notice";
    $personnel_message = "The project '{$project['service']}' has been marked as completed. Thank you for your contribution!";

    foreach ($assigned_personnel as $person) {
        $notification_stmt->execute([
            $_SESSION['user_id'],  // project manager who completed
            $person['user_id'],    // personnel to notify
            $project_id,
            $personnel_title,
            $personnel_message
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Project marked as completed and notifications sent successfully',
        'details' => [
            'project_id' => $project_id,
            'client_notified' => true,
            'personnel_notified' => count($assigned_personnel)
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error completing project: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to complete project: ' . $e->getMessage()
    ]);
}
?> 