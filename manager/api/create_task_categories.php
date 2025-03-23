<?php
session_start();
require_once '../../dbconnect.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['project_id']) || !isset($data['categories'])) {
        throw new Exception('Missing required data');
    }

    $project_id = $data['project_id'];
    $categories = $data['categories'];

    // Start transaction
    $pdo->beginTransaction();

    // Prepare statement for category insertion
    $stmt = $pdo->prepare("
        INSERT INTO task_categories (
            project_id,
            category_name,
            description,
            status,
            created_at
        ) VALUES (?, ?, ?, 'in progress', NOW())
    ");

    // Insert each category
    foreach ($categories as $category) {
        $stmt->execute([
            $project_id,
            $category['name'],
            $category['description']
        ]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Task categories created successfully',
        'project_id' => $project_id
    ]);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error creating task categories: ' . $e->getMessage()
    ]);
}
?> 