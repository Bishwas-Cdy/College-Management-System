<?php
// faculty/dashboard.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['faculty']);

$pageTitle = 'Faculty Dashboard';
$active = 'dashboard';

$userId = (int)($_SESSION['user_id'] ?? 0);

// Get faculty id
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$facultyRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$facultyId = (int)($facultyRow['id'] ?? 0);

// Assigned subjects (count)
$assignedSubjects = 0;
if ($facultyId > 0) {
  $stmt = $conn->prepare("
    SELECT COUNT(DISTINCT subject_id) as cnt FROM faculty_subject 
    WHERE faculty_id = ?
  ");
  $stmt->bind_param('i', $facultyId);
  $stmt->execute();
  $subRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $assignedSubjects = (int)($subRow['cnt'] ?? 0);
}

// Today's attendance sessions marked
$todayAttendance = 0;
if ($facultyId > 0) {
  $today = date('Y-m-d');
  $stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM attendance 
    WHERE created_by_faculty_id = ? AND date = ?
  ");
  $stmt->bind_param('is', $facultyId, $today);
  $stmt->execute();
  $attRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $todayAttendance = (int)($attRow['cnt'] ?? 0);
}

// Marks published (visible to students - from published exams)
$marksEntered = 0;
if ($facultyId > 0) {
  $stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM marks m
    JOIN exams e ON e.id = m.exam_id
    WHERE m.entered_by_faculty_id = ? AND m.marks IS NOT NULL AND e.is_published = 1
  ");
  $stmt->bind_param('i', $facultyId);
  $stmt->execute();
  $markRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $marksEntered = (int)($markRow['cnt'] ?? 0);
}

// Today's routine (timetable for today)
$todayRoutine = [];
if ($facultyId > 0) {
  $today = date('D'); // Sun, Mon, Tue, etc.
  $stmt = $conn->prepare("
    SELECT t.start_time, t.end_time, t.room, s.subject_name, c.course_name
    FROM timetable t
    JOIN subjects s ON s.id = t.subject_id
    JOIN courses c ON c.id = t.course_id
    WHERE t.faculty_id = ? AND t.day_of_week = ?
    ORDER BY t.start_time ASC
  ");
  $stmt->bind_param('is', $facultyId, $today);
  $stmt->execute();
  $todayRoutine = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
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
          <h3 class="fw-bold mb-1">Faculty Dashboard</h3>
          <p class="small-muted mb-0">Mark attendance, enter marks, upload study materials, and message students.</p>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6 col-xl-4">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-journal-bookmark fs-4 text-dark"></i>
                  <span class="fw-semibold">Assigned Subjects</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $assignedSubjects ?></div>
                <div class="small-muted">This semester</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-4">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-calendar-check fs-4 text-primary"></i>
                  <span class="fw-semibold">Today Attendance</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $todayAttendance ?></div>
                <div class="small-muted">Sessions marked</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-4">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-award fs-4 text-success"></i>
                  <span class="fw-semibold">Marks Published</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $marksEntered ?></div>
                <div class="small-muted">Visible to students</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-7">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><i class="bi bi-calendar2-week me-2"></i>Today’s Routine</h5>
              <?php if (!empty($todayRoutine)): ?>
                <div class="small">
                  <ul class="list-unstyled mb-0">
                    <?php foreach ($todayRoutine as $t): 
                      $startTime = htmlspecialchars(substr($t['start_time'], 0, 5));
                      $endTime = htmlspecialchars(substr($t['end_time'], 0, 5));
                      $subject = htmlspecialchars($t['subject_name']);
                      $course = htmlspecialchars($t['course_name']);
                      $room = htmlspecialchars((string)$t['room']);
                    ?>
                      <li class="mb-2 pb-2 border-bottom">
                        <div class="fw-semibold"><?= $startTime ?> - <?= $endTime ?></div>
                        <div class="small text-muted"><?= $subject ?> | <?= $course ?> | Room <?= $room ?></div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php else: ?>
                <div class="small-muted text-center py-3">
                  <p class="mb-0">No classes scheduled for today.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots me-2"></i>Quick Actions</h5>
              <div class="d-grid gap-2">
                <a class="btn btn-outline-dark rounded-3" href="attendance_create.php"><i class="bi bi-calendar-check me-2"></i>Mark Attendance</a>
                <a class="btn btn-outline-dark rounded-3" href="marks_entry.php"><i class="bi bi-award me-2"></i>Enter Marks</a>
                <a class="btn btn-outline-dark rounded-3" href="materials.php"><i class="bi bi-folder2-open me-2"></i>Upload Materials</a>
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
