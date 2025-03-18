<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

try {
    // Get form data
    $services = $_POST['services'] ?? [];
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    // Validate data
    if (empty($services) || empty($date) || empty($time)) {
        throw new Exception('Please fill in all required fields');
    }

    // Format services array into a string with HTML line breaks instead of \n
    $service_list = implode("<br>", $services);

    // Start transaction
    $pdo->beginTransaction();

    // Insert appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (client_id, service, date, time, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $service_list,
        $date,
        $time
    ]);

    // Get the newly created appointment ID
    $appointment_id = $pdo->lastInsertId();

    // Get admin user IDs
    $admin_query = $pdo->prepare("SELECT user_id FROM users WHERE role = 'admin'");
    $admin_query->execute();
    $admin_ids = $admin_query->fetchAll(PDO::FETCH_COLUMN);

    // Send notification to all admins
    if (!empty($admin_ids)) {
        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, recipient_id, type, reference_id, title, message, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, FALSE, NOW())
        ");

        $title = "New Appointment Request";
        $service_list = implode(", ", $services);
        $message = "Client " . $_SESSION['name'] . " has requested an appointment for " . 
                  date('M d, Y', strtotime($date)) . " at " . 
                  date('h:i A', strtotime($time)) . ". Services requested: " . $service_list;

        foreach ($admin_ids as $admin_id) {
            try {
                $notification_stmt->execute([
                    $_SESSION['user_id'],  // user_id (client who sent the notification)
                    $admin_id,             // recipient_id (admin who receives the notification)
                    'system',              // type
                    $appointment_id,       // reference_id
                    $title,                // title
                    $message               // message
                ]);
            } catch (PDOException $e) {
                // Log the error for debugging
                error_log("Failed to insert notification for admin ID $admin_id: " . $e->getMessage());
                error_log("SQL State: " . $e->getCode());
                error_log("Message content: " . $message);
                throw $e; // Re-throw to trigger rollback
            }
        }
    }

    // Commit transaction
    $pdo->commit();

    // Redirect with success message
    $_SESSION['success_message'] = 'Appointment booked successfully! We will review your request and get back to you soon.';
    header('Location: client/dashboard.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction if error occurs
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error
    error_log("Appointment booking error: " . $e->getMessage());
    
    // Redirect with error message
    $_SESSION['error_message'] = 'Failed to book appointment: ' . $e->getMessage();
    header('Location: book_appointment.php');
    exit;
}
?> 