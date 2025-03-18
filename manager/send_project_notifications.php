<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get POST data
    $project_id = $_POST['project_id'] ?? null;
    $personnel = json_decode($_POST['personnel'] ?? '[]', true);
    $service = $_POST['service'] ?? '';

    if (!$project_id || empty($personnel) || !$service) {
        throw new Exception('Missing required data');
    }

    // Debug log
    error_log("Processing project notification. Project ID: $project_id");

    // Validate all personnel IDs exist in users table
    $personnel_ids = implode(',', array_fill(0, count($personnel), '?'));
    $personnel_check = $pdo->prepare("SELECT user_id, name FROM users WHERE user_id IN ($personnel_ids)");
    $personnel_check->execute($personnel);
    $valid_personnel = $personnel_check->fetchAll(PDO::FETCH_ASSOC);

    // Filter out any invalid personnel IDs
    $valid_personnel_ids = array_column($valid_personnel, 'user_id');
    $personnel = array_intersect($personnel, $valid_personnel_ids);

    if (empty($personnel)) {
        throw new Exception('No valid personnel IDs found from the provided list');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Send notifications to all assigned personnel
    $personnel_notification = $pdo->prepare("
        INSERT INTO notifications (
            user_id, recipient_id, type, reference_id, 
            title, message, is_read, created_at
        ) VALUES (
            ?, ?, 'project', ?, ?, ?, FALSE, NOW()
        )
    ");

    $personnel_title = "New Project Assignment";
    $personnel_message = "You have been assigned to a new project: '{$service}'. Please check your dashboard for project details and tasks.";

    foreach ($personnel as $user_id) {
        $personnel_notification->execute([
            $_SESSION['user_id'],  // project manager who created
            $user_id,             // personnel to notify
            $project_id,
            $personnel_title,
            $personnel_message
        ]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Notifications sent successfully',
        'details' => [
            'project_id' => $project_id,
            'personnel_count' => count($personnel)
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error sending project notifications: " . $e->getMessage());
    error_log("Debug data: " . print_r($_POST, true));
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send notifications: ' . $e->getMessage(),
        'debug' => [
            'project_id' => $project_id ?? null,
            'personnel_count' => count($personnel ?? []),
            'has_service' => !empty($service)
        ]
    ]);
} 