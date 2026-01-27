<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['faculty']);

$active = 'timetable';
$pageTitle = 'My Timetable';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// Find faculty profile id
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$facultyRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$facultyId = (int)($facultyRow['id'] ?? 0);
$list = [];

if ($facultyId > 0) {
  $stmt = $conn->prepare("
    SELECT
      t.day_of_week, t.start_time, t.end_time, t.room, t.semester,
      c.course_name, s.subject_name
    FROM timetable t
    JOIN courses c ON c.id = t.course_id
    JOIN subjects s ON s.id = t.subject_id
    WHERE t.faculty_id = ?
    ORDER BY FIELD(t.day_of_week,'Sun','Mon','Tue','Wed','Thu','Fri','Sat'), t.start_time ASC
  ");
  $stmt->bind_param('i', $facultyId);
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
      <div class="text-muted mb-3">Only shows entries assigned to you.</div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Day</th>
                  <th>Time</th>
                  <th>Course</th>
                  <th>Sem</th>
                  <th>Subject</th>
                  <th>Room</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$list): ?>
                  <tr><td colspan="6" class="text-center text-muted">No timetable entries assigned yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($list as $t): ?>
                    <tr>
                      <td><?= h($t['day_of_week']) ?></td>
                      <td><?= h(substr($t['start_time'],0,5)) ?>–<?= h(substr($t['end_time'],0,5)) ?></td>
                      <td><?= h($t['course_name']) ?></td>
                      <td><?= h($t['semester']) ?></td>
                      <td><?= h($t['subject_name']) ?></td>
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
