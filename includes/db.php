<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'municihelp';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// not working
$cleanup = $conn->prepare("DELETE FROM users WHERE is_verified = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$cleanup->execute();
$cleanup->close();
?>