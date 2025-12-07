<?php
// Database connection settings for XAMPP
$servername = "localhost"; 

// Default username for MySQL/MariaDB in XAMPP
$username = "root";       

// Default password for MySQL/MariaDB in XAMPP (usually empty)
$password = "";           // ***CHECK THIS! If you set a password for MySQL, put it here.***

// ***CRITICAL: This must exactly match your database name!***
$dbname = "pos_gadgets";  

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection and output an error if it fails
if ($conn->connect_error) {
    die("Connection failed: Please check your db_config.php file. Error: " . $conn->connect_error);
}
?>