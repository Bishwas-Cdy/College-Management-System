<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['faculty']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'attendance';
$pageTitle = 'Attendance - Create Session';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// faculty id
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$facultyRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$facultyId = (int)($facultyRow['id'] ?? 0);

if ($facultyId <= 0) {
  flash_set('error', 'Faculty profile not found.');
  header('Location: dashboard.php');
  exit;
}

// Subjects assigned to this faculty
$stmt = $conn->prepare("
  SELECT s.id, s.subject_name, s.semester, s.course_id, c.course_name
  FROM faculty_subject fs
  JOIN subjects s ON s.id = fs.subject_id
  JOIN courses c ON c.id = s.course_id
  WHERE fs.faculty_id = ?
  ORDER BY c.course_name ASC, s.semester ASC, s.subject_name ASC
");
$stmt->bind_param('i', $facultyId);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $subjectId = (int)($_POST['subject_id'] ?? 0);
  $date = trim((string)($_POST['date'] ?? ''));

  if ($subjectId <= 0 || $date === '') {
    flash_set('error', 'Select subject and date.');
    header('Location: attendance_create.php');
    exit;
  }

  // Get course + semester from subject
  $stmt = $conn->prepare("SELECT course_id, semester FROM subjects WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $subjectId);
  $stmt->execute();
  $sub = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $courseId = (int)($sub['course_id'] ?? 0);
  $semester = (string)($sub['semester'] ?? '');

  if ($courseId <= 0 || trim($semester) === '') {
    flash_set('error', 'Subject invalid.');
    header('Location: attendance_create.php');
    exit;
  }

  try {
    // unique(subject_id, course_id, semester, date) prevents duplicates
    $stmt = $conn->prepare("
      INSERT INTO attendance (subject_id, course_id, semester, date, created_by_faculty_id)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iissi', $subjectId, $courseId, $semester, $date, $facultyId);
    $stmt->execute();
    $attendanceId = $stmt->insert_id;
    $stmt->close();

    flash_set('success', 'Attendance session created. Now mark attendance.');
    header('Location: attendance_mark.php?id=' . $attendanceId);
    exit;

  } catch (Throwable $e) {
    // duplicate session
    flash_set('error', 'Session already exists for this subject and date.');
    header('Location: attendance_create.php');
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
      <div class="text-muted mb-3">Create a session, then mark students.</div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <form method="POST" class="row g-3">
            <div class="col-md-7">
              <label class="form-label fw-semibold">Subject *</label>
              <select class="form-select" name="subject_id" required>
                <option value="0">-- Select subject --</option>
                <?php foreach ($subjects as $s): ?>
                  <option value="<?= (int)$s['id'] ?>">
                    <?= h($s['course_name']) ?> | <?= h($s['semester']) ?> | <?= h($s['subject_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Date *</label>
              <input type="date" class="form-control" name="date" required>
            </div>
            <div class="col-md-2 d-grid align-items-end">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-plus-circle me-2"></i>Create
              </button>
            </div>
          </form>

          <div class="text-muted small mt-3">
            Note: you can create only one session per subject per date.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
