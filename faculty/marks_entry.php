<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['faculty']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'marks';
$pageTitle = 'Marks Entry';

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

// Assigned subjects (and their course/semester)
$stmt = $conn->prepare("
  SELECT s.id, s.subject_name, s.course_id, s.semester, c.course_name
  FROM faculty_subject fs
  JOIN subjects s ON s.id = fs.subject_id
  JOIN courses c ON c.id = s.course_id
  WHERE fs.faculty_id = ?
  ORDER BY c.course_name ASC, s.semester ASC, s.subject_name ASC
");
$stmt->bind_param('i', $facultyId);
$stmt->execute();
$subjects_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Exams (published only) matching any of faculty assigned course/semester pairs
// We filter by joining on subjects_list course/semester
$exams_list = [];
if ($subjects_list) {
  // Build unique pairs
  $pairs = [];
  foreach ($subjects_list as $s) {
    $pairs[$s['course_id'] . '|' . $s['semester']] = ['course_id' => (int)$s['course_id'], 'semester' => (string)$s['semester']];
  }

  // Build dynamic WHERE (safe via prepared)
  $whereParts = [];
  $types = '';
  $params = [];
  foreach ($pairs as $p) {
    $whereParts[] = "(e.course_id = ? AND e.semester = ?)";
    $types .= 'is';
    $params[] = $p['course_id'];
    $params[] = $p['semester'];
  }

  $sql = "
    SELECT e.id, e.exam_name, e.course_id, e.semester, e.exam_date, c.course_name
    FROM exams e
    JOIN courses c ON c.id = e.course_id
    WHERE e.is_published = 1 AND (" . implode(" OR ", $whereParts) . ")
    ORDER BY e.created_at DESC
  ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $exams_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

$examId = (int)($_GET['exam_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

$selectedExam = null;
$selectedSubject = null;

// Load selected exam
if ($examId > 0) {
  $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ? AND is_published = 1 LIMIT 1");
  $stmt->bind_param('i', $examId);
  $stmt->execute();
  $selectedExam = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// Load selected subject (must be assigned)
if ($subjectId > 0) {
  $stmt = $conn->prepare("
    SELECT s.*, c.course_name
    FROM subjects s
    JOIN courses c ON c.id = s.course_id
    JOIN faculty_subject fs ON fs.subject_id = s.id
    WHERE s.id = ? AND fs.faculty_id = ?
    LIMIT 1
  ");
  $stmt->bind_param('ii', $subjectId, $facultyId);
  $stmt->execute();
  $selectedSubject = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$students_list = [];
$existingMarks = [];

if ($selectedExam && $selectedSubject) {
  // Enrolled students for subject
  $stmt = $conn->prepare("
    SELECT st.id AS student_id, st.name, st.roll_number
    FROM enrollments e
    JOIN students st ON st.id = e.student_id
    WHERE e.subject_id = ?
    ORDER BY st.roll_number ASC, st.name ASC
  ");
  $stmt->bind_param('i', $subjectId);
  $stmt->execute();
  $students_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Existing marks for exam+subject
  $stmt = $conn->prepare("
    SELECT student_id, marks
    FROM marks
    WHERE exam_id = ? AND subject_id = ?
  ");
  $stmt->bind_param('ii', $examId, $subjectId);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  foreach ($rows as $r) $existingMarks[(int)$r['student_id']] = $r['marks'];
}

// Save marks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $examId = (int)($_POST['exam_id'] ?? 0);
  $subjectId = (int)($_POST['subject_id'] ?? 0);
  $marksInput = $_POST['marks'] ?? [];

  if ($examId <= 0 || $subjectId <= 0) {
    flash_set('error', 'Select exam and subject first.');
    header('Location: marks_entry.php');
    exit;
  }

  // confirm faculty assigned to subject again
  $stmt = $conn->prepare("SELECT 1 FROM faculty_subject WHERE faculty_id = ? AND subject_id = ? LIMIT 1");
  $stmt->bind_param('ii', $facultyId, $subjectId);
  $stmt->execute();
  $ok = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$ok) {
    flash_set('error', 'Not allowed.');
    header('Location: marks_entry.php');
    exit;
  }

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare("
      INSERT INTO marks (exam_id, student_id, subject_id, marks, entered_by_faculty_id)
      VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE marks = VALUES(marks), entered_by_faculty_id = VALUES(entered_by_faculty_id)
    ");

    foreach ($marksInput as $studentIdStr => $val) {
      $studentId = (int)$studentIdStr;
      $m = trim((string)$val);
      $m = ($m === '') ? null : (int)$m;

      // Validate marks range (0-100)
      if ($m !== null && ($m < 0 || $m > 100)) {
        $conn->rollback();
        flash_set('error', "Invalid marks for student ID $studentId. Marks must be 0-100.");
        header('Location: marks_entry.php?exam_id=' . $examId . '&subject_id=' . $subjectId);
        exit;
      }

      // bind null safely: use conditional query
      if ($m === null) {
        $stmtNull = $conn->prepare("
          INSERT INTO marks (exam_id, student_id, subject_id, marks, entered_by_faculty_id)
          VALUES (?, ?, ?, NULL, ?)
          ON DUPLICATE KEY UPDATE marks = NULL, entered_by_faculty_id = VALUES(entered_by_faculty_id)
        ");
        $stmtNull->bind_param('iiii', $examId, $studentId, $subjectId, $facultyId);
        $stmtNull->execute();
        $stmtNull->close();
      } else {
        $stmt->bind_param('iiiii', $examId, $studentId, $subjectId, $m, $facultyId);
        $stmt->execute();
      }
    }

    $stmt->close();
    $conn->commit();

    // Send notifications to students in this exam+subject
    $stmtNotif = $conn->prepare("
      SELECT DISTINCT e.user_id
      FROM enrollments e
      WHERE e.subject_id = ? AND e.course_id = (SELECT course_id FROM exams WHERE id = ? LIMIT 1)
    ");
    $stmtNotif->bind_param('ii', $subjectId, $examId);
    $stmtNotif->execute();
    $notifRows = $stmtNotif->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtNotif->close();

    if (!empty($notifRows)) {
      $notifUserIds = array_map(fn($row) => (int)$row['user_id'], $notifRows);
      send_notifications_batch($conn, $notifUserIds, 'Your exam marks have been published. Check your dashboard.');
    }

    flash_set('success', 'Marks saved.');
    header('Location: marks_entry.php?exam_id=' . $examId . '&subject_id=' . $subjectId);
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    flash_set('error', 'Save failed: ' . $e->getMessage());
    header('Location: marks_entry.php?exam_id=' . $examId . '&subject_id=' . $subjectId);
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
      <div class="text-muted mb-3">Select exam and subject, then enter marks for enrolled students.</div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="card border-0 rounded-4 shadow-sm mb-3">
        <div class="card-body p-4">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Exam (Published)</label>
              <select class="form-select" name="exam_id" required>
                <option value="0">-- Select exam --</option>
                <?php foreach ($exams_list as $e): ?>
                  <option value="<?= (int)$e['id'] ?>" <?= $examId === (int)$e['id'] ? 'selected' : '' ?>>
                    <?= h($e['course_name']) ?> | <?= h($e['semester']) ?> | <?= h($e['exam_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Subject (Assigned)</label>
              <select class="form-select" name="subject_id" required>
                <option value="0">-- Select subject --</option>
                <?php foreach ($subjects_list as $s): ?>
                  <option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= h($s['course_name']) ?> | <?= h($s['semester']) ?> | <?= h($s['subject_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 d-grid mt-2">
              <button class="btn btn-outline-primary" type="submit">
                <i class="bi bi-search me-2"></i>Load Students
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <?php if (!$selectedExam || !$selectedSubject): ?>
            <div class="alert alert-info mb-0">Select exam and subject to enter marks.</div>
          <?php else: ?>
            <div class="mb-2 text-muted">
              Exam: <strong><?= h($selectedExam['exam_name']) ?></strong>
              | Subject: <strong><?= h($selectedSubject['subject_name']) ?></strong>
            </div>

            <?php if (!$students_list): ?>
              <div class="alert alert-warning mb-0">No students enrolled for this subject yet.</div>
            <?php else: ?>
              <form method="POST">
                <input type="hidden" name="exam_id" value="<?= (int)$examId ?>">
                <input type="hidden" name="subject_id" value="<?= (int)$subjectId ?>">

                <div class="table-responsive">
                  <table class="table align-middle">
                    <thead>
                      <tr>
                        <th>Roll</th>
                        <th>Name</th>
                        <th class="text-end" style="width:160px;">Marks</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($students_list as $st):
                        $sid = (int)$st['student_id'];
                        $val = $existingMarks[$sid] ?? '';
                      ?>
                        <tr>
                          <td><?= h($st['roll_number']) ?></td>
                          <td><?= h($st['name']) ?></td>
                          <td class="text-end">
                            <input
                              class="form-control text-end"
                              name="marks[<?= $sid ?>]"
                              value="<?= h((string)$val) ?>"
                              placeholder="e.g. 75"
                              inputmode="numeric"
                            >
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <div class="d-flex justify-content-end gap-2">
                  <button class="btn btn-primary" type="submit">
                    <i class="bi bi-save me-2"></i>Save Marks
                  </button>
                </div>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
