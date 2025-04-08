<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

// Function to send notification to admin
function sendNotification($pdo, $userId, $title, $message, $type, $referenceId, $recipientId) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, reference_id, recipient_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $title, $message, $type, $referenceId, $recipientId]);
    } catch (Exception $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return false;
    }
}

// Get admin user ID
function getAdminUserId($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ? $admin['user_id'] : null;
    } catch (Exception $e) {
        error_log("Error getting admin user ID: " . $e->getMessage());
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify reCAPTCHA first
        $recaptcha_token = $_POST['recaptcha_token'] ?? '';
        if (empty($recaptcha_token)) {
            throw new Exception('reCAPTCHA verification failed');
        }

        // Verify the token with Google
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_secret = '6LcVU_wqAAAAAKp4DJS_cEa4RecUQ8M4ECERbXPy';
        
        $recaptcha_response = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_token);
        $recaptcha_data = json_decode($recaptcha_response);

        // Check if verification was successful and score is acceptable
        if (!$recaptcha_data->success || $recaptcha_data->score < 0.5) {
            throw new Exception('reCAPTCHA verification failed. Please try again.');
        }

        // Debug line to see what's being submitted
        error_log("Received POST data: " . print_r($_POST, true));

        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $services = isset($_POST['service']) ? (array)$_POST['service'] : [];
        $appointment_type = $_POST['appointment_type'] ?? '';

        // Validate inputs
        if (empty($date)) {
            throw new Exception('Please select a date');
        }
        if (empty($time)) {
            throw new Exception('Please select a time');
        }
        if (empty($services)) {
            throw new Exception('Please select at least one service');
        }

        // Check if the time slot is already booked
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE date = ? 
            AND time = ? 
            AND status != 'cancelled'
        ");
        $stmt->execute([$date, $time]);
        $exists = $stmt->fetchColumn();

        if ($exists > 0) {
            $_SESSION['error_message'] = 'This time slot is already booked. Please select another time.';
            header('Location: book_appointment.php');
            exit;
        }

        // Convert service array to string if it's an array
        $servicesString = is_array($services) ? implode(', ', array_map(function($service) {
            return trim($service, '[]"'); // Remove brackets and quotes
        }, $services)) : $services;

        // If time slot is available, proceed with booking
        $sql = "INSERT INTO appointments (
            client_id,
            service, 
            date, 
            time,
            appointment_type,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            $servicesString, // Store as plain string instead of JSON
            $date,
            $time,
            $appointment_type
        ]);

        $appointmentId = $pdo->lastInsertId();
        
        // Get admin user ID
        $adminUserId = getAdminUserId($pdo);
        
        if ($adminUserId) {
            // Create notification for admin
            $notificationTitle = "New Appointment Request";
            $notificationMessage = "A new appointment has been requested by " . $_SESSION['name'] . 
                                " for " . $servicesString . 
                                " on " . date('M d, Y', strtotime($date)) . 
                                " at " . date('h:i A', strtotime($time));
            
            sendNotification(
                $pdo,
                $_SESSION['user_id'], // sender (client)
                $notificationTitle,
                $notificationMessage,
                'system',
                $appointmentId,
                $adminUserId // recipient (admin)
            );
        }

        $_SESSION['success_message'] = 'Appointment booked successfully!';
        header('Location: client/dashboard.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: book_appointment.php');
        exit;
    }
}

header('Location: book_appointment.php');
exit;
?> 