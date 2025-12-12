<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "q";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

// Set timezone to Philippine Time (UTC+8)
$conn->query("SET time_zone = '+08:00'");

// Also set PHP timezone
date_default_timezone_set('Asia/Manila');
?>

