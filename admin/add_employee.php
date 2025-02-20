<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['name', 'email', 'phone', 'role', 'password'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("$field is required");
        }
    }

    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validate phone number (numbers only)
    if (!preg_match('/^[0-9]+$/', $_POST['phone'])) {
        throw new Exception('Phone number must contain only numbers');
    }

    // Validate role
    $allowed_roles = ['project_manager', 'engineer', 'laborer'];
    if (!in_array($_POST['role'], $allowed_roles)) {
        throw new Exception('Invalid role selected');
    }

    // Validate password length
    if (strlen($_POST['password']) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }

    // Hash password
    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Insert new employee
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name,
            email,
            password,
            role,
            phone,
            status,
            date_created
        ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ");

    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['email']),
        $hashed_password,
        $_POST['role'],
        $_POST['phone']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Employee added successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 