<?php
session_start();
require_once '../dbconnect.php';

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
error_log('get_employee_details.php started');

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    error_log('Unauthorized access attempt in get_employee_details.php');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    error_log('No ID provided in get_employee_details.php');
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit;
}

try {
    error_log('Fetching details for employee ID: ' . $_GET['id']);
    
    // Get employee details - removed last_login from SELECT
    $stmt = $pdo->prepare("
        SELECT 
            user_id,
            name,
            email,
            phone,
            role,
            date_created
        FROM users 
        WHERE user_id = ?
    ");
    
    $stmt->execute([$_GET['id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log('Query result: ' . print_r($employee, true));

    if (!$employee) {
        error_log('No employee found with ID: ' . $_GET['id']);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    // Format date
    $employee['date_created'] = $employee['date_created'] ? 
        date('M d, Y', strtotime($employee['date_created'])) : 'N/A';

    $response = [
        'success' => true,
        'employee' => $employee
    ];

    error_log('Sending response: ' . print_r($response, true));
    echo json_encode($response);

} catch (PDOException $e) {
    error_log('Database error in get_employee_details.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('General error in get_employee_details.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?> 