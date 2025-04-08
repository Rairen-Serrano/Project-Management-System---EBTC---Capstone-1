<?php
// Turn off error display - add these at the very top of the file
error_reporting(0);
ini_set('display_errors', 0);

session_start();
error_log('Session data: ' . print_r($_SESSION, true));
error_log('POST data: ' . file_get_contents('php://input'));
require_once '../../dbconnect.php';

// Set JSON content type header before any output
header('Content-Type: application/json');

// Check for session and role
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get and decode the JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null) {
        throw new Exception('Invalid JSON data received');
    }
    
    if (!isset($data['personnel']) || !is_array($data['personnel'])) {
        throw new Exception('Invalid personnel data');
    }

    $available_personnel = [];
    
    foreach ($data['personnel'] as $person) {
        if (!isset($person['id'])) {
            continue;
        }
        
        // Get user's current active projects and role
        $stmt = $pdo->prepare("
            SELECT u.active_projects, u.role 
            FROM users u 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$person['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            continue;
        }

        $max_projects = 0;
        switch ($user['role']) {
            case 'engineer':
                $max_projects = 2;
                break;
            case 'technician':
            case 'worker':
                $max_projects = 1;
                break;
            default:
                $max_projects = 0;
        }

        if ($user['active_projects'] < $max_projects) {
            $available_personnel[] = (int)$person['id'];
        }
    }

    echo json_encode([
        'success' => true,
        'available_personnel' => $available_personnel
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
