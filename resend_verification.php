<?php
session_start();
require_once 'dbconnect.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';

    if (empty($email)) {
        throw new Exception('Email address is required');
    }

    // Get user details
    $stmt = $pdo->prepare("SELECT user_id, email FROM users WHERE email = ? AND status = 'pending'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate new verification token
        $verification_token = bin2hex(random_bytes(32));
        
        // Update verification token
        $stmt = $pdo->prepare("
            UPDATE email_verifications 
            SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            WHERE user_id = ?
        ");
        $stmt->execute([$verification_token, $user['user_id']]);

        // Send new verification email
        if (sendVerificationEmail($email, $verification_token)) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to send verification email');
        }
    } else {
        throw new Exception('Invalid email or account already verified');
    }
} catch (Exception $e) {
    error_log('Resend verification error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 