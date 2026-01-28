<?php
declare(strict_types=1);

// Database configuration
$db_host = 'localhost';
$db_user = 'root';        
$db_pass = '';            
$db_name = '25123796';         

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
  die('Database connection failed: ' . $conn->connect_error);
}

// Set charset
$conn->set_charset('utf8mb4');

// Include helpers for get_system_settings() and other utilities
require_once __DIR__ . '/helpers.php';
