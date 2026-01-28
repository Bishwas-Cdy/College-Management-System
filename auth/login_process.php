<?php
declare(strict_types=1);

session_start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php');
  exit;
}

// Basic input
$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

// Validate
if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: login.php?error=1');
  exit;
}

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
  // Fail fast if db.php isn't providing a mysqli connection named $conn
  header('Location: login.php?error=1');
  exit;
}

// Prevent SQL injection with prepared statements
$sql = "SELECT id, email, password, role, is_active, session_token
        FROM users
        WHERE email = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  header('Location: login.php?error=1');
  exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();

$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;

$stmt->close();

if (!$user) {
  header('Location: login.php?error=1');
  exit;
}

// Optional: block disabled accounts
if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
  header('Location: login.php?error=1');
  exit;
}

// Verify password hash
if (!password_verify($password, (string)$user['password'])) {
  header('Location: login.php?error=1');
  exit;
}

// Mitigate session fixation
session_regenerate_id(true);

// Fetch user's name from students or faculty table
$user_name = $email; // fallback to email
if ($user['role'] === 'student') {
  $name_sql = "SELECT name FROM students WHERE user_id = ? LIMIT 1";
  $name_stmt = $conn->prepare($name_sql);
  if ($name_stmt) {
    $name_stmt->bind_param('i', $user['id']);
    $name_stmt->execute();
    $name_result = $name_stmt->get_result();
    $name_row = $name_result->fetch_assoc();
    if ($name_row && !empty($name_row['name'])) {
      $user_name = $name_row['name'];
    }
    $name_stmt->close();
  }
} elseif ($user['role'] === 'faculty') {
  $name_sql = "SELECT name FROM faculty WHERE user_id = ? LIMIT 1";
  $name_stmt = $conn->prepare($name_sql);
  if ($name_stmt) {
    $name_stmt->bind_param('i', $user['id']);
    $name_stmt->execute();
    $name_result = $name_stmt->get_result();
    $name_row = $name_result->fetch_assoc();
    if ($name_row && !empty($name_row['name'])) {
      $user_name = $name_row['name'];
    }
    $name_stmt->close();
  }
}

// Save session
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['email'] = (string)$user['email'];
$_SESSION['name'] = $user_name;
$_SESSION['role'] = (string)$user['role'];
$_SESSION['session_token'] = (string)$user['session_token'];

// Role-based redirect
switch ($_SESSION['role']) {
  case 'admin':
    header('Location: ../admin/dashboard.php');
    exit;

  case 'faculty':
    header('Location: ../faculty/dashboard.php');
    exit;

  case 'student':
    header('Location: ../student/dashboard.php');
    exit;

  default:
    // Unknown role = reject
    session_unset();
    session_destroy();
    header('Location: login.php?error=1');
    exit;
}
