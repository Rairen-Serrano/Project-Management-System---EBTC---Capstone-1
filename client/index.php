<?php
session_start();

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}

// Redirect to appointments page
header('Location: appointments.php');
exit;
?> 