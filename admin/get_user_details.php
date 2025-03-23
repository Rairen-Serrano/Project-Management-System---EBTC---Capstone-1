<?php
session_start();
require_once '../dbconnect.php';

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
error_log('get_user_details.php started');

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    error_log('Unauthorized access attempt in get_user_details.php');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    error_log('No ID provided in get_user_details.php');
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    error_log('Fetching details for user ID: ' . $_GET['id']);
    
    // Get user details - removed the archived condition to allow viewing archived users
    $stmt = $pdo->prepare("
        SELECT 
            user_id,
            name,
            email,
            phone,
            date_created
        FROM users 
        WHERE user_id = ? AND role = 'client'
    ");
    
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log('Query result: ' . print_r($user, true));

    if (!$user) {
        error_log('No user found with ID: ' . $_GET['id']);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Format date
    $user['date_created'] = $user['date_created'] ? 
        date('M d, Y', strtotime($user['date_created'])) : 'N/A';

    $response = [
        'success' => true,
        'user' => $user
    ];

    error_log('Sending response: ' . print_r($response, true));
    echo json_encode($response);

} catch (PDOException $e) {
    error_log('Database error in get_user_details.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('General error in get_user_details.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?> 