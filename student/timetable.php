<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['student']);

$active = 'timetable';
$pageTitle = 'My Timetable';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// Get student profile course+semester
$stmt = $conn->prepare("SELECT course_id, semester FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$courseId = (int)($student['course_id'] ?? 0);
$semester = (string)($student['semester'] ?? '');

$list = [];
$courseName = '';

if ($courseId > 0) {
  $stmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $courseId);
  $stmt->execute();
  $courseRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $courseName = (string)($courseRow['course_name'] ?? '');
}

if ($courseId > 0 && trim($semester) !== '') {
  $stmt = $conn->prepare("
    SELECT
      t.day_of_week, t.start_time, t.end_time, t.room,
      s.subject_name,
      COALESCE(f.name,'') AS faculty_name
    FROM timetable t
    JOIN subjects s ON s.id = t.subject_id
    LEFT JOIN faculty f ON f.id = t.faculty_id
    WHERE t.course_id = ? AND t.semester = ?
    ORDER BY FIELD(t.day_of_week,'Sun','Mon','Tue','Wed','Thu','Fri','Sat'), t.start_time ASC
  ");
  $stmt->bind_param('is', $courseId, $semester);
  $stmt->execute();
  $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
      <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
      <div class="text-muted mb-3">
        Course: <?= h($courseName) ?> | Semester: <?= h((string)$semester) ?>
      </div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Day</th>
                  <th>Time</th>
                  <th>Subject</th>
                  <th>Faculty</th>
                  <th>Room</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$list): ?>
                  <tr><td colspan="5" class="text-center text-muted">No timetable entries yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($list as $t): ?>
                    <tr>
                      <td><?= h($t['day_of_week']) ?></td>
                      <td><?= h(substr($t['start_time'],0,5)) ?>–<?= h(substr($t['end_time'],0,5)) ?></td>
                      <td><?= h($t['subject_name']) ?></td>
                      <td><?= h($t['faculty_name']) ?></td>
                      <td><?= h((string)$t['room']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
