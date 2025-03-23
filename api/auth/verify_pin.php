<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['admin', 'project_manager', 'technician', 'engineer', 'worker', 'client'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$pin = $data['pin'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    // Check if account is locked and get last attempt time
    $stmt = $pdo->prepare("SELECT pin_attempts, locked_until, last_attempt_time FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if we should reset attempts (if last attempt was more than 3 minutes ago)
    $now = time();
    $lastAttemptTime = strtotime($user['last_attempt_time'] ?? '');
    if ($lastAttemptTime && ($now - $lastAttemptTime > 180) && $user['pin_attempts'] < 3) {
        // Reset attempts if less than 3 attempts and more than 3 minutes passed
        $stmt = $pdo->prepare("UPDATE users SET pin_attempts = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user['pin_attempts'] = 0;
    }

    // Check if account is locked
    if ($user['locked_until'] !== null) {
        $locked_until = strtotime($user['locked_until']);
        
        if ($now < $locked_until) {
            $remaining_seconds = $locked_until - $now;
            echo json_encode([
                'success' => false,
                'message' => 'Account is locked',
                'lockout_duration' => $remaining_seconds
            ]);
            exit;
        }
    }

    // Verify PIN
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stored_pin = $stmt->fetch(PDO::FETCH_ASSOC)['pin_code'];

    if ($pin === $stored_pin || password_verify($pin, $stored_pin)) {
        // Reset attempts on successful login
        $stmt = $pdo->prepare("UPDATE users SET pin_attempts = 0, locked_until = NULL, last_attempt_time = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $_SESSION['pin_verified'] = true;
        unset($_SESSION['needs_pin_verification']);
        echo json_encode(['success' => true]);
    } else {
        // Update last attempt time and increment attempts
        $new_attempts = ($user['pin_attempts'] ?? 0) + 1;
        
        // Determine lockout duration based on number of attempts
        $lockout_duration = null;
        $message = 'Invalid PIN. Please try again.';
        $remaining_seconds = 0;
        
        switch ($new_attempts) {
            case 3:
                // Lock for 3 minutes
                $lockout_duration = date('Y-m-d H:i:s', strtotime('+3 minutes'));
                $message = 'Account locked for 3 minutes due to too many failed attempts.';
                $remaining_seconds = 180; // 3 minutes
                break;
            case 4:
                // Lock for 5 minutes
                $lockout_duration = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                $message = 'Account locked for 5 minutes due to too many failed attempts.';
                $remaining_seconds = 300; // 5 minutes
                break;
            case 5:
                // Lock for 10 minutes
                $lockout_duration = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $message = 'Account locked for 10 minutes due to too many failed attempts.';
                $remaining_seconds = 600; // 10 minutes
                break;
        }

        // Update attempts, lockout time, and last attempt time
        $stmt = $pdo->prepare("UPDATE users SET pin_attempts = ?, locked_until = ?, last_attempt_time = NOW() WHERE user_id = ?");
        $stmt->execute([$new_attempts, $lockout_duration, $user_id]);

        echo json_encode([
            'success' => false,
            'message' => $message,
            'lockout_duration' => $lockout_duration ? $remaining_seconds : null
        ]);
    }

} catch (Exception $e) {
    error_log('PIN verification error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?> 