<?php
// student/dashboard.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['student']);

$pageTitle = 'Student Dashboard';
$active = 'dashboard';

$userId = (int)($_SESSION['user_id'] ?? 0);

// Get student info
$stmt = $conn->prepare("SELECT course_id, semester FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$studentRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$courseId = (int)($studentRow['course_id'] ?? 0);
$semester = (string)($studentRow['semester'] ?? '');
$studentId = 0;

if ($courseId > 0) {
  $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $sidRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $studentId = (int)($sidRow['id'] ?? 0);
}

// Today's routine (timetable for today)
$today = date('D');
$todayRoutine = 0;
if ($courseId > 0 && $semester !== '') {
  $stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM timetable 
    WHERE course_id = ? AND semester = ? AND day_of_week = ?
  ");
  $stmt->bind_param('iss', $courseId, $semester, $today);
  $stmt->execute();
  $routineRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $todayRoutine = (int)($routineRow['cnt'] ?? 0);
}

// Attendance percentage
$attendancePercent = 0;
if ($studentId > 0) {
  $stmt = $conn->prepare("
    SELECT 
      SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
      COUNT(*) as total
    FROM attendance_details
    WHERE student_id = ?
  ");
  $stmt->bind_param('i', $studentId);
  $stmt->execute();
  $attRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $totalAtt = (int)($attRow['total'] ?? 0);
  if ($totalAtt > 0) {
    $presentAtt = (int)($attRow['present'] ?? 0);
    $attendancePercent = round(($presentAtt / $totalAtt) * 100, 1);
  }
}

// Pending invoices
$pendingInvoices = 0;
if ($studentId > 0) {
  $stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM invoices 
    WHERE student_id = ? AND status = 'unpaid'
  ");
  $stmt->bind_param('i', $studentId);
  $stmt->execute();
  $invRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $pendingInvoices = (int)($invRow['cnt'] ?? 0);
}

include(__DIR__ . '/../partials/header.php');
include(__DIR__ . '/../partials/app_navbar.php');
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">

      <div class="col-lg-3">
        <?php include(__DIR__ . '/../partials/app_sidebar.php'); ?>
      </div>

      <div class="col-lg-9">
        <div class="glass rounded-4 p-4 border mb-3">
          <h3 class="fw-bold mb-1">Student Dashboard</h3>
          <p class="small-muted mb-0">View routine, attendance, marks, results, invoices, and study materials.</p>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6 col-xl-4">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-calendar2-week fs-4 text-info"></i>
                  <span class="fw-semibold">Today Routine</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $todayRoutine ?></div>
                <div class="small-muted">Classes</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-4">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-calendar-check fs-4 text-primary"></i>
                  <span class="fw-semibold">Attendance</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $attendancePercent ?>%</div>
                <div class="small-muted">Overall</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-4">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-receipt fs-4 text-warning"></i>
                  <span class="fw-semibold">Invoices</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $pendingInvoices ?></div>
                <div class="small-muted">Pending</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-7">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><i class="bi bi-folder2-open me-2"></i>Latest Study Materials</h5>
              <div class="small-muted">Hook this to study_materials table filtered by course and semester.</div>
              <ul class="mt-3 small-muted mb-0 ps-3">
                <li>DBMS Notes - Week 3 (PDF)</li>
                <li>DSA Assignment 1 (PDF)</li>
              </ul>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
              <div class="d-grid gap-2">
                <a class="btn btn-outline-dark rounded-3" href="timetable.php"><i class="bi bi-calendar2-week me-2"></i>View Timetable</a>
                <a class="btn btn-outline-dark rounded-3" href="attendance.php"><i class="bi bi-calendar-check me-2"></i>View Attendance</a>
                <a class="btn btn-outline-dark rounded-3" href="results.php"><i class="bi bi-award me-2"></i>View Results</a>
                <a class="btn btn-outline-dark rounded-3" href="invoices.php"><i class="bi bi-receipt me-2"></i>View Invoices</a>
                <a class="btn btn-outline-dark rounded-3" href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include(__DIR__ . '/../partials/footer.php'); ?>
