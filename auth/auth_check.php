<?php
// auth/auth_check.php
// Usage: include this at the TOP of any protected page.
// Example:
//   require_once __DIR__ . '/../auth/auth_check.php';
//   require_role(['admin']);

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/**
 * Redirect helper.
 */
function redirect_to_login(): void {
  header('Location: ../auth/login.php');
  exit;
}

/**
 * Require user to be logged in.
 */
function require_login(): void {
  if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    redirect_to_login();
  }
  
  // Validate session token (check if password was reset) and is_active status
  global $conn;
  if (!isset($conn)) {
    return; // Skip validation if db not available
  }
  
  $user_id = (int)$_SESSION['user_id'];
  $session_token = $_SESSION['session_token'] ?? null;
  
  $stmt = $conn->prepare("SELECT session_token, is_active FROM users WHERE id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Check if user is disabled
    if ($row && (int)($row['is_active'] ?? 0) !== 1) {
      session_destroy();
      redirect_to_login();
    }
    
    // If session token doesn't match, password was reset - force logout
    if ($row && $row['session_token'] !== $session_token) {
      session_destroy();
      redirect_to_login();
    }
  }
}

/**
 * Require user role to be in allowed roles.
 *
 * @param array $roles Example: ['admin'] or ['faculty','admin']
 */
function require_role(array $roles): void {
  require_login();

  $role = (string)($_SESSION['role'] ?? '');
  if (!in_array($role, $roles, true)) {
    // Optional: you can send them to a 403 page instead
    redirect_to_login();
  }
}
