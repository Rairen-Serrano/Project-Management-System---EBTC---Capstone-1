<?php
$host = 'localhost';
$dbname = 'pms-ebtc';
$dbuser = 'root';  // replace with your database username
$dbpass = '';      // replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Set default PIN code for clients
$stmt = $pdo->prepare("UPDATE users SET pin_code = '1234' WHERE role = 'client' AND (pin_code IS NULL OR pin_code = '')");
$stmt->execute();

?> 