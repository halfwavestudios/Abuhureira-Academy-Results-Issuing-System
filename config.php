<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'abuhurei_wp6328');
define('DB_PASS', 'Sonik9200');
define('DB_NAME', 'abuhurei_wp6328');

// Admin Credentials
define('ADMIN_USER', 'AbuhureiraAdmin');
define('ADMIN_PASS', 'SuperSecurePassword123!');

// Establish connection
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}
?>