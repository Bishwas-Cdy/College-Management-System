<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['student']);

$active = 'attendance';
$pageTitle = 'My Attendance';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// student id
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$studentRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$studentId = (int)($studentRow['id'] ?? 0);

$list = [];

if ($studentId > 0) {
  // per subject totals + presents
  $stmt = $conn->prepare("
    SELECT
      subj.subject_name,
      c.course_name,
      subj.semester,
      COUNT(ad.id) AS total_classes,
      SUM(CASE WHEN ad.status='present' THEN 1 ELSE 0 END) AS present_classes
    FROM attendance_details ad
    JOIN attendance a ON a.id = ad.attendance_id
    JOIN subjects subj ON subj.id = a.subject_id
    JOIN courses c ON c.id = a.course_id
    WHERE ad.student_id = ?
    GROUP BY a.subject_id
    ORDER BY c.course_name ASC, subj.semester ASC, subj.subject_name ASC
  ");
  $stmt->bind_param('i', $studentId);
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
      <div class="text-muted mb-3">Attendance summary per subject.</div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Semester</th>
                  <th>Subject</th>
                  <th class="text-end">Present</th>
                  <th class="text-end">Total</th>
                  <th class="text-end">Percent</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$list): ?>
                  <tr><td colspan="6" class="text-center text-muted">No attendance records yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($list as $r):
                    $total = (int)$r['total_classes'];
                    $present = (int)$r['present_classes'];
                    $pct = $total > 0 ? round(($present / $total) * 100, 2) : 0;
                  ?>
                    <tr>
                      <td><?= h($r['course_name']) ?></td>
                      <td><?= h($r['semester']) ?></td>
                      <td><?= h($r['subject_name']) ?></td>
                      <td class="text-end"><?= $present ?></td>
                      <td class="text-end"><?= $total ?></td>
                      <td class="text-end"><?= h((string)$pct) ?>%</td>
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
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
