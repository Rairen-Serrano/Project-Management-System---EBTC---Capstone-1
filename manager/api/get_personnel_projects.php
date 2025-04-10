<?php
session_start();
require_once '../../dbconnect.php';

// Set JSON content type header
header('Content-Type: application/json');

// Check for session and role
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get personnel ID from query parameter
    $personnel_id = isset($_GET['personnel_id']) ? (int)$_GET['personnel_id'] : 0;
    
    if ($personnel_id <= 0) {
        throw new Exception('Invalid personnel ID');
    }

    // Debug: Log the personnel ID
    error_log("Fetching projects for personnel ID: " . $personnel_id);

    // Get personnel's projects
    $stmt = $pdo->prepare("
        SELECT 
            p.project_id,
            CONCAT('Project #', p.project_id) as project_name,
            a.service,
            p.status,
            p.start_date,
            p.end_date
        FROM projects p
        JOIN project_assignees pa ON p.project_id = pa.project_id
        JOIN appointments a ON p.appointment_id = a.appointment_id
        WHERE pa.user_id = ?
        AND p.status = 'ongoing'
        ORDER BY p.start_date DESC
    ");
    
    $stmt->execute([$personnel_id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log detailed information
    error_log("Found " . count($projects) . " projects for personnel ID: " . $personnel_id);
    error_log("SQL Query: " . $stmt->queryString);
    error_log("Query parameters: " . json_encode([$personnel_id]));
    error_log("Raw projects data: " . json_encode($projects));
    
    // Check if we have any projects
    if (empty($projects)) {
        error_log("No projects found for personnel ID: " . $personnel_id);
        echo json_encode([
            'success' => true,
            'projects' => [],
            'message' => 'No active projects found for this personnel'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);

} catch (Exception $e) {
    error_log("Error in get_personnel_projects.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 