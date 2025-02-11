<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false]);
    exit;
}

if (!isset($_POST['pin'])) {
    echo json_encode(['success' => false]);
    exit;
}

$pin = $_POST['pin'];

// Get the admin's PIN from the database
$stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Try both plain text and hashed verification
if ($user && ($pin === $user['pin_code'] || password_verify($pin, $user['pin_code']))) {
    $_SESSION['pin_verified'] = true;
    unset($_SESSION['needs_pin_verification']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
} 