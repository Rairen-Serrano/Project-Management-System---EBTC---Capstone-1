<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the entire request
error_log('Setup PIN request received');
error_log('POST data: ' . print_r($_POST, true));
error_log('Session data: ' . print_r($_SESSION, true));
error_log('Raw input: ' . file_get_contents('php://input'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        error_log('No user_id in session');
        throw new Exception('User not logged in');
    }

    // Get and decode JSON data
    $input = file_get_contents('php://input');
    error_log('Received input: ' . $input);
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        throw new Exception('Invalid JSON data');
    }

    $pin = $data['pin'] ?? '';
    error_log('Extracted PIN (length): ' . strlen($pin));

    // Validate PIN format
    if (!$pin || strlen($pin) !== 4 || !ctype_digit($pin)) {
        error_log('PIN validation failed - Length: ' . strlen($pin) . ', Is numeric: ' . ctype_digit($pin));
        throw new Exception('Invalid PIN format - must be 4 digits');
    }

    // Log database connection status
    error_log('PDO connection status: ' . ($pdo ? 'Connected' : 'Not connected'));

    // Check current PIN status
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('Current user data: ' . print_r($user, true));

    // Hash the PIN
    $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
    error_log('PIN hashed successfully');

    // Update database
    $stmt = $pdo->prepare("UPDATE users SET pin_code = ? WHERE user_id = ?");
    $result = $stmt->execute([$hashedPin, $_SESSION['user_id']]);
    
    if (!$result) {
        error_log('Database error: ' . print_r($stmt->errorInfo(), true));
        throw new Exception('Database update failed');
    }

    $rowCount = $stmt->rowCount();
    error_log('Rows affected: ' . $rowCount);

    if ($rowCount === 0) {
        throw new Exception('No user record was updated');
    }

    // Success response
    error_log('PIN setup completed successfully');
    echo json_encode([
        'success' => true,
        'message' => 'PIN set successfully',
        'debug' => [
            'user_id' => $_SESSION['user_id'],
            'rows_affected' => $rowCount
        ]
    ]);

} catch (PDOException $e) {
    error_log('PDO Exception: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => true
    ]);
} catch (Exception $e) {
    error_log('General Exception: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error setting PIN: ' . $e->getMessage(),
        'debug' => true
    ]);
}
?> 