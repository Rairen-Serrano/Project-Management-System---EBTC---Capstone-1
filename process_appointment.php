<?php
session_start();
require_once 'dbconnect.php';

// Redirect if not logged in
if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the client ID from session
        $client_id = $_SESSION['user_id'];
        
        // Get selected services
        $services = isset($_POST['services']) ? $_POST['services'] : [];
        
        // Convert services array to string
        $services_string = implode(', ', $services);
        
        // Get date and time
        $date = $_POST['date'];
        $time = $_POST['time'];

        // Prepare and execute the SQL statement
        $stmt = $pdo->prepare("
            INSERT INTO appointments 
            (client_id, service, date, time) 
            VALUES 
            (?, ?, ?, ?)
        ");

        $stmt->execute([
            $client_id,
            $services_string,
            $date,
            $time
        ]);

        // Set success message
        $_SESSION['success_message'] = "Appointment booked successfully! We will confirm your appointment soon.";
        
        // Redirect to client dashboard
        header('Location: client/dashboard.php');
        exit();

    } catch(PDOException $e) {
        // Set error message
        $_SESSION['error_message'] = "Error booking appointment: " . $e->getMessage();
        
        // Redirect back to appointment page
        header('Location: book_appointment.php');
        exit();
    }
} else {
    // If someone tries to access this file directly
    header('Location: index.php');
    exit();
}
?> 