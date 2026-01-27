<?php

/**
 * Send notification to user
 * @param mysqli $conn Database connection
 * @param int $user_id Recipient user ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool Success status
 */
function send_notification(mysqli $conn, int $user_id, string $message): bool {
  if ($user_id <= 0) return false;
  
  $stmt = $conn->prepare("
    INSERT INTO notifications (user_id, message, created_at)
    VALUES (?, ?, NOW())
  ");
  
  if (!$stmt) return false;
  
  $stmt->bind_param('is', $user_id, $message);
  $result = $stmt->execute();
  $stmt->close();
  
  return $result;
}

/**
 * Send notifications to multiple users
 * @param mysqli $conn Database connection
 * @param array $user_ids Array of recipient user IDs
 * @param string $title Notification title
 * @param string $message Notification message
 * @return int Number of notifications sent
 */
function send_notifications_batch(mysqli $conn, array $user_ids, string $message): int {
  if (empty($user_ids)) return 0;
  
  $count = 0;
  foreach ($user_ids as $uid) {
    if (send_notification($conn, (int)$uid, $message)) {
      $count++;
    }
  }
  return $count;
}

/**
 * Log audit event
 * @param mysqli $conn Database connection
 * @param int $user_id Admin user ID
 * @param string $action Action name (create, update, delete, etc)
 * @param string $model Model/table name (students, faculty, subjects, etc)
 * @param int|string $record_id Record ID affected
 * @param string $details Additional context (optional)
 * @return bool Success status
 */
function log_audit(mysqli $conn, int $user_id, string $action, string $model, $record_id, string $details = ''): bool {
  if ($user_id <= 0) return false;
  
  $stmt = $conn->prepare("
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details, created_at)
    VALUES (?, ?, ?, ?, NULLIF(?, ''), NOW())
  ");
  
  if (!$stmt) return false;
  
  $recordIdStr = (string)$record_id;
  $stmt->bind_param('isiss', $user_id, $action, $model, $recordIdStr, $details);
  $result = $stmt->execute();
  $stmt->close();
  
  return $result;
}
