<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
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
        $servicesString = is_array($services) ? implode(', ', $services) : $services;

        // If time slot is available, proceed with booking
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                client_id, 
                date, 
                time, 
                service, 
                status, 
                created_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $date,
            $time,
            $servicesString,
        ]);

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