<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$pin = $data['pin'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || !$pin) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pin, $user['pin_code'])) {
        $_SESSION['pin_verified'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
    }
} catch (PDOException $e) {
    error_log('PIN verification error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error verifying PIN']);
}
?> 