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

// Set default hashed PIN code for clients
$default_pin = '1234';
$hashed_default_pin = password_hash($default_pin, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET pin_code = ? WHERE role = 'client' AND (pin_code IS NULL OR pin_code = '')");
$stmt->execute([$hashed_default_pin]);

// Set default profile photo for clients who don't have one
$default_image_path = 'images/default_profile.jpg';
if (file_exists($default_image_path)) {
    $default_photo = file_get_contents($default_image_path);
    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE role = 'client' AND profile_photo IS NULL");
    $stmt->execute([$default_photo]);
} 