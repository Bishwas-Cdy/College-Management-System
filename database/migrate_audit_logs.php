<?php
require_once __DIR__ . '/../config/db.php';

try {
  // Check if table exists
  $result = $conn->query("SHOW TABLES LIKE 'audit_logs'");
  
  if (!$result || $result->num_rows === 0) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE audit_logs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      action VARCHAR(50) NOT NULL,
      table_name VARCHAR(100) NOT NULL,
      record_id INT NULL,
      details TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
    ) ENGINE=InnoDB";
    
    $conn->query($sql);
    echo "✓ Created audit_logs table<br>";
    
    // Add indexes
    $conn->query("CREATE INDEX idx_audit_user_date ON audit_logs(user_id, created_at)");
    $conn->query("CREATE INDEX idx_audit_table ON audit_logs(table_name, created_at)");
    echo "Created indexes<br>";
  } else {
    echo "audit_logs table already exists<br>";
  }
  
  echo "Migration completed successfully!";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage();
}
