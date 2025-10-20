<?php
// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "mobile_shop";
$port = 3306; // Default MySQL port

// Check if connection already exists
if (!isset($conn) || $conn->connect_error) {
    try {
        // Create new connection with error handling
        $conn = new mysqli($host, $user, $pass, $dbname, $port);
        
        // Check connection
        if ($conn->connect_error) {
            // Try alternative connection methods
            if ($conn->connect_error == "Only one usage of each socket address (protocol/network address/port) is normally permitted") {
                // Connection already exists, try to reuse
                $conn = null;
                $conn = mysqli_connect($host, $user, $pass, $dbname, $port);
                
                if (!$conn) {
                    die("Connection failed: Unable to connect to database. Please check if MySQL is running in XAMPP.");
                }
            } else {
                die("Connection failed: " . $conn->connect_error);
            }
        }
        
        // Set charset to avoid encoding issues
        $conn->set_charset("utf8");
        
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}
?>
