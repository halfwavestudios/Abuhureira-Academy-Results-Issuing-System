<?php
// error_test.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test database connection
try {
    $conn = new mysqli('localhost', 'abuhurei_wp6328', 'Sonik9200', 'abuhurei_wp6328');
    echo "Database connection: " . ($conn->connect_error ? "Failed" : "Success") . "<br>";
} catch(Exception $e) {
    die("DB Error: " . $e->getMessage());
}

// Test session handling
$_SESSION['test'] = 'working';
echo "Session test: " . ($_SESSION['test'] === 'working' ? "OK" : "Failed") . "<br>";

// Test file permissions
echo "File permissions: ";
echo is_readable(__FILE__) ? "OK" : "Failed (check chmod 644)";
?>