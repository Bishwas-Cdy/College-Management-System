<?php
require_once __DIR__ . '/../config/db.php';

try {
  // Check if column exists
  $result = $conn->query("SHOW COLUMNS FROM users LIKE 'session_token'");
  
  if ($result && $result->num_rows === 0) {
    // Column doesn't exist, add it
    $conn->query("ALTER TABLE users ADD COLUMN session_token VARCHAR(36) NULL");
    echo "Added session_token column to users table<br>";
    
    // Populate existing users with UUIDs
    $conn->query("UPDATE users SET session_token = UUID() WHERE session_token IS NULL");
    echo "Populated session_token for existing users<br>";
  } else {
    echo "session_token column already exists<br>";
  }
  
  echo "Migration completed successfully!";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage();
}
