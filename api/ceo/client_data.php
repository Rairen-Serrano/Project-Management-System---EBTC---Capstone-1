<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is a CEO
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'ceo') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Fetch clients with their project counts
    $query = "
        SELECT 
            u.user_id,
            u.name,
            u.email,
            u.phone,
            u.department,
            u.job_title,
            u.date_created,
            COUNT(DISTINCT p.project_id) as project_count,
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    p.service, '|',
                    p.status, '|',
                    p.start_date
                ) SEPARATOR '||'
            ) as projects
        FROM users u
        LEFT JOIN projects p ON u.user_id = p.client_id
        WHERE u.role = 'client'
        GROUP BY u.user_id, u.name, u.email, u.phone, u.department, u.job_title, u.date_created
        ORDER BY u.name
    ";
    
    error_log("Executing client query: " . $query);
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($clients) . " clients");
    
    // Process the projects data for each client
    foreach ($clients as &$client) {
        if ($client['projects']) {
            $projects = [];
            $projectItems = explode('||', $client['projects']);
            
            foreach ($projectItems as $item) {
                list($service, $status, $start_date) = explode('|', $item);
                $projects[] = [
                    'service' => $service,
                    'status' => $status,
                    'start_date' => $start_date
                ];
            }
            
            $client['projects'] = $projects;
        } else {
            $client['projects'] = [];
        }
    }
    
    // Return the data in the expected structure
    echo json_encode([
        'success' => true,
        'data' => [
            'clients' => $clients
        ]
    ]);
} catch (PDOException $e) {
    error_log("Database error in client_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in client_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?> 