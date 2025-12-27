<?php
// Database connection optimized and verified
$servername = "localhost";  // Default for XAMPP
$username = "root";         // Default MySQL user
$password = "";             // Default empty password
$dbname = "vi";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}       
?>
        