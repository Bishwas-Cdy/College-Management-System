<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/auth_check.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Not found'); }

$stmt = $conn->prepare("
  SELECT m.*, s.course_id AS subj_course_id, s.semester AS subj_semester
  FROM study_materials m
  JOIN subjects s ON s.id = m.subject_id
  WHERE m.id = ?
  LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$m) { http_response_code(404); exit('Not found'); }

$allowed = false;

if ($role === 'student') {
  $stmt = $conn->prepare("SELECT course_id, semester FROM students WHERE user_id = ? LIMIT 1");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $st = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($st && (int)$st['course_id'] === (int)$m['course_id'] && (string)$st['semester'] === (string)$m['semester']) {
    $allowed = true;
  }
}

if ($role === 'faculty') {
  $stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ? LIMIT 1");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $f = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $facultyId = (int)($f['id'] ?? 0);
  if ($facultyId > 0 && (int)$m['uploaded_by_faculty_id'] === $facultyId) {
    $allowed = true;
  }
}

if ($role === 'admin') {
  $allowed = true;
}

if (!$allowed) { 
  http_response_code(403);
  header('Location: ../index.php?error=forbidden');
  exit;
}

$base = realpath(__DIR__);
$path = $base . DIRECTORY_SEPARATOR . $m['file_path'];
$real = realpath($path);

if (!$real || !is_file($real) || strpos($real, $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'materials') !== 0) {
  http_response_code(404);
  header('Location: ../index.php?error=notfound');
  exit;
}

$downloadName = preg_replace('/[^a-zA-Z0-9_\-\. ]+/', '', ($m['title'] ?? 'material')) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');

readfile($real);
exit;
