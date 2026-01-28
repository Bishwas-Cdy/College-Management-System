<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../partials/flash.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$active = 'enrollments';
$pageTitle = 'Enrollments (Student ↔ Subjects)';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$selectedStudentId = (int)($_GET['student_id'] ?? 0);

try {
  // Students dropdown (show course+semester)
  $sqlStudents = "
    SELECT
      s.id,
      s.name,
      s.roll_number,
      s.semester,
      s.course_id,
      COALESCE(c.course_name,'') AS course_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    ORDER BY s.name ASC
  ";
  $studentsList = $conn->query($sqlStudents)->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
  $studentsList = [];
}

$studentInfo = null;
$enrolledList = [];
$enrolledTotal = 0;
$enrolledPage = 1;
$enrolledPerPage = 10;

if ($selectedStudentId > 0) {
  // Load selected student info
  $stmt = $conn->prepare("
    SELECT
      s.id,
      s.name,
      s.roll_number,
      s.semester,
      s.course_id,
      COALESCE(c.course_name,'') AS course_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    WHERE s.id = ?
    LIMIT 1
  ");
  $stmt->bind_param('i', $selectedStudentId);
  $stmt->execute();
  $studentInfo = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // Get pagination params for enrollments
  $enrolledPage = max(1, (int)($_GET['enroll_page'] ?? 1));
  $enrolledOffset = ($enrolledPage - 1) * $enrolledPerPage;

  // Count total enrollments
  $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM enrollments WHERE student_id = ?");
  $stmt->bind_param('i', $selectedStudentId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $enrolledTotal = (int)($row['cnt'] ?? 0);
  $stmt->close();

  // Load current enrollments with pagination
  $stmt = $conn->prepare("
    SELECT
      e.id AS enrollment_id,
      subj.id AS subject_id,
      subj.subject_name,
      subj.semester,
      c.course_name
    FROM enrollments e
    JOIN subjects subj ON subj.id = e.subject_id
    JOIN courses c ON c.id = subj.course_id
    WHERE e.student_id = ?
    ORDER BY c.course_name ASC, subj.semester ASC, subj.subject_name ASC
    LIMIT ? OFFSET ?
  ");
  $stmt->bind_param('iii', $selectedStudentId, $enrolledPerPage, $enrolledOffset);
  $stmt->execute();
  $enrolledList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $studentId = (int)($_POST['student_id'] ?? 0);

  if ($studentId <= 0) {
    flash_set('error', 'Select a student first.');
    header('Location: enrollments.php');
    exit;
  }

  try {
    if ($action === 'auto_enroll') {
      // Get student's course+semester
      $stmt = $conn->prepare("SELECT course_id, semester FROM students WHERE id = ? LIMIT 1");
      $stmt->bind_param('i', $studentId);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      $courseId = (int)($row['course_id'] ?? 0);
      $semester = (string)($row['semester'] ?? '');

      if ($courseId <= 0 || trim($semester) === '') {
        flash_set('error', 'Student must have Course and Semester set before auto-enroll.');
        header('Location: enrollments.php?student_id=' . $studentId);
        exit;
      }

      // Find subjects matching course+semester and insert enrollments
      $stmt = $conn->prepare("SELECT id FROM subjects WHERE course_id = ? AND semester = ?");
      $stmt->bind_param('is', $courseId, $semester);
      $stmt->execute();
      $subjectRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      if (!$subjectRows) {
        flash_set('error', 'No subjects found for this student course+semester. Create subjects first.');
        header('Location: enrollments.php?student_id=' . $studentId);
        exit;
      }

      $ins = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)");
      foreach ($subjectRows as $sr) {
        $sid = (int)$sr['id'];
        $ins->bind_param('ii', $studentId, $sid);
        $ins->execute();
      }
      $ins->close();

      flash_set('success', 'Auto-enrollment completed.');
      header('Location: enrollments.php?student_id=' . $studentId);
      exit;
    }

    if ($action === 'remove') {
      $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
      if ($enrollmentId <= 0) {
        flash_set('error', 'Invalid enrollment.');
        header('Location: enrollments.php?student_id=' . $studentId);
        exit;
      }

      $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ? AND student_id = ?");
      $stmt->bind_param('ii', $enrollmentId, $studentId);
      $stmt->execute();
      $stmt->close();

      flash_set('success', 'Enrollment removed.');
      header('Location: enrollments.php?student_id=' . $studentId);
      exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: enrollments.php?student_id=' . $studentId);
    exit;

  } catch (Throwable $e) {
    flash_set('error', 'Action failed: ' . $e->getMessage());
    header('Location: enrollments.php?student_id=' . $studentId);
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

      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
          <div class="text-muted">Auto-enroll students into subjects based on Course and Semester.</div>
        </div>
      </div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="row g-3">

        <div class="col-lg-5">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">1) Select Student</h5>

              <form method="GET" class="mb-3">
                <label class="form-label fw-semibold">Student</label>
                <select class="form-select" name="student_id" onchange="this.form.submit()">
                  <option value="0">-- Select student --</option>
                  <?php foreach ($studentsList as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $selectedStudentId === (int)$s['id'] ? 'selected' : '' ?>>
                      <?= h($s['name']) ?> (<?= h($s['roll_number']) ?>) — <?= h($s['course_name']) ?> / <?= h((string)$s['semester']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>

              <hr>

              <h5 class="fw-semibold mb-3">2) Auto-enroll</h5>

              <?php if (!$studentInfo): ?>
                <div class="alert alert-info mb-0">Select a student first.</div>
              <?php else: ?>
                <div class="border rounded-4 p-3 mb-3">
                  <div class="fw-semibold"><?= h($studentInfo['name']) ?></div>
                  <div class="text-muted small">Roll: <?= h($studentInfo['roll_number']) ?></div>
                  <div class="text-muted small">Course: <?= h($studentInfo['course_name']) ?></div>
                  <div class="text-muted small">Semester: <?= h((string)$studentInfo['semester']) ?></div>
                </div>

                <form method="POST" onsubmit="return confirm('Auto-enroll this student into all subjects for their course+semester?');">
                  <input type="hidden" name="action" value="auto_enroll">
                  <input type="hidden" name="student_id" value="<?= (int)$selectedStudentId ?>">
                  <div class="d-grid">
                    <button class="btn btn-primary btn-lg rounded-3" type="submit">
                      <i class="bi bi-person-plus me-2"></i>Auto-enroll Now
                    </button>
                  </div>
                </form>

                <!-- <div class="text-muted small mt-3">
                  Requires: subjects exist for the student’s course+semester.
                </div> -->
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Enrolled Subjects <?php if ($studentInfo): ?>(Total: <?= $enrolledTotal ?>)<?php endif; ?></h5>

              <?php if (!$studentInfo): ?>
                <div class="alert alert-secondary mb-0">Select a student to view enrollments.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table align-middle">
                    <thead>
                      <tr>
                        <th>Course</th>
                        <th>Semester</th>
                        <th>Subject</th>
                        <th class="text-end">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!$enrolledList): ?>
                        <tr>
                          <td colspan="4" class="text-center text-muted">No enrollments yet.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($enrolledList as $e): ?>
                          <tr>
                            <td><?= h($e['course_name']) ?></td>
                            <td><?= h($e['semester']) ?></td>
                            <td><?= h($e['subject_name']) ?></td>
                            <td class="text-end">
                              <form method="POST" class="d-inline" onsubmit="return confirm('Remove this enrollment?');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="student_id" value="<?= (int)$selectedStudentId ?>">
                                <input type="hidden" name="enrollment_id" value="<?= (int)$e['enrollment_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                  <i class="bi bi-x-circle me-1"></i>Remove
                                </button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination for enrollments -->
                <?php if ($enrolledTotal > $enrolledPerPage): ?>
                  <?php $enrolledPages = ceil($enrolledTotal / $enrolledPerPage); ?>
                  <nav class="mt-3" aria-label="Enrollments pagination">
                    <ul class="pagination justify-content-center" style="font-size: 0.875rem;">
                      <li class="page-item <?= $enrolledPage === 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="enrollments.php?student_id=<?= (int)$selectedStudentId ?>&enroll_page=1">First</a>
                      </li>
                      <li class="page-item <?= $enrolledPage === 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="enrollments.php?student_id=<?= (int)$selectedStudentId ?>&enroll_page=<?= $enrolledPage - 1 ?>">Prev</a>
                      </li>
                      <?php for ($i = 1; $i <= $enrolledPages; $i++): ?>
                        <li class="page-item <?= $i === $enrolledPage ? 'active' : '' ?>">
                          <a class="page-link" href="enrollments.php?student_id=<?= (int)$selectedStudentId ?>&enroll_page=<?= $i ?>"><?= $i ?></a>
                        </li>
                      <?php endfor; ?>
                      <li class="page-item <?= $enrolledPage === $enrolledPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="enrollments.php?student_id=<?= (int)$selectedStudentId ?>&enroll_page=<?= $enrolledPage + 1 ?>">Next</a>
                      </li>
                      <li class="page-item <?= $enrolledPage === $enrolledPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="enrollments.php?student_id=<?= (int)$selectedStudentId ?>&enroll_page=<?= $enrolledPages ?>">Last</a>
                      </li>
                    </ul>
                  </nav>
                <?php endif; ?>
              <?php endif; ?>

              <!-- <div class="text-muted small mt-3">
                Next: Timetable and Attendance will use these enrollments.
              </div> -->
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
