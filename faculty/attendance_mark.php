<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['faculty']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'attendance';
$pageTitle = 'Attendance - Mark';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// faculty id
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$facultyRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$facultyId = (int)($facultyRow['id'] ?? 0);

$attendanceId = (int)($_GET['id'] ?? 0);
if ($attendanceId <= 0) {
  flash_set('error', 'Missing attendance session.');
  header('Location: attendance_create.php');
  exit;
}

// Load attendance session + verify created_by or subject assigned (we use subject assigned check)
$stmt = $conn->prepare("
  SELECT a.id, a.subject_id, a.course_id, a.semester, a.date,
         s.subject_name, c.course_name
  FROM attendance a
  JOIN subjects s ON s.id = a.subject_id
  JOIN courses c ON c.id = a.course_id
  WHERE a.id = ?
  LIMIT 1
");
$stmt->bind_param('i', $attendanceId);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
  flash_set('error', 'Attendance session not found.');
  header('Location: attendance_create.php');
  exit;
}

// Ensure faculty is assigned to this subject
$stmt = $conn->prepare("SELECT 1 FROM faculty_subject WHERE faculty_id = ? AND subject_id = ? LIMIT 1");
$stmt->bind_param('ii', $facultyId, $session['subject_id']);
$stmt->execute();
$ok = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ok) {
  flash_set('error', 'You are not assigned to this subject.');
  header('Location: attendance_create.php');
  exit;
}

// Get enrolled students for this subject
$stmt = $conn->prepare("
  SELECT s.id AS student_id, s.name, s.roll_number
  FROM enrollments e
  JOIN students s ON s.id = e.student_id
  WHERE e.subject_id = ?
  ORDER BY s.roll_number ASC, s.name ASC
");
$subjectId = (int)$session['subject_id'];
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Existing marks for this session
$stmt = $conn->prepare("SELECT student_id, status FROM attendance_details WHERE attendance_id = ?");
$stmt->bind_param('i', $attendanceId);
$stmt->execute();
$existingRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$existing = [];
foreach ($existingRows as $r) $existing[(int)$r['student_id']] = $r['status'];

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $statuses = $_POST['status'] ?? [];

  // Default all to absent, then set present for checked
  $conn->begin_transaction();
  try {
    // Insert/update each student status
    $stmt = $conn->prepare("
      INSERT INTO attendance_details (attendance_id, student_id, status)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE status = VALUES(status)
    ");

    foreach ($students as $st) {
      $sid = (int)$st['student_id'];
      $val = $statuses[$sid] ?? 'absent';
      $val = ($val === 'present') ? 'present' : 'absent';

      $stmt->bind_param('iis', $attendanceId, $sid, $val);
      $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
    flash_set('success', 'Attendance saved.');
    header('Location: attendance_mark.php?id=' . $attendanceId);
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    flash_set('error', 'Save failed: ' . $e->getMessage());
    header('Location: attendance_mark.php?id=' . $attendanceId);
    exit;
  }
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
        <?= h($session['course_name']) ?> | <?= h($session['semester']) ?> | <?= h($session['subject_name']) ?> | <?= h($session['date']) ?>
      </div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">

          <?php if (!$students): ?>
            <div class="alert alert-warning mb-0">
              No students enrolled in this subject yet. Ask admin to enroll students first.
            </div>
          <?php else: ?>
            <form method="POST">
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Roll</th>
                      <th>Name</th>
                      <th class="text-end">Present</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($students as $st): 
                      $sid = (int)$st['student_id'];
                      $isPresent = (($existing[$sid] ?? 'absent') === 'present');
                    ?>
                      <tr>
                        <td><?= h($st['roll_number']) ?></td>
                        <td><?= h($st['name']) ?></td>
                        <td class="text-end">
                          <input type="hidden" name="status[<?= $sid ?>]" value="absent">
                          <div class="form-check form-switch d-inline-flex justify-content-end">
                            <input class="form-check-input" type="checkbox"
                                   name="status[<?= $sid ?>]" value="present"
                                   <?= $isPresent ? 'checked' : '' ?>>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="d-flex gap-2 justify-content-end">
                <a class="btn btn-outline-secondary" href="attendance_create.php">Back</a>
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-save me-2"></i>Save Attendance
                </button>
              </div>
            </form>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
