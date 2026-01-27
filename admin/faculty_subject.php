<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);
require_once __DIR__ . '/../partials/flash.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$pageTitle = 'Assign Subjects to Faculty';
$active = 'faculty_subject';

/* -----------------------------
   Helpers
------------------------------*/
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

/* -----------------------------
   Handle POST actions
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'assign') {
      $facultyId = (int)($_POST['faculty_id'] ?? 0);
      $subjectIds = $_POST['subject_ids'] ?? [];

      if ($facultyId <= 0) {
        flash_set('error', 'Select a faculty member.');
        header('Location: faculty_subject.php');
        exit;
      }

      if (!is_array($subjectIds) || count($subjectIds) === 0) {
        flash_set('error', 'Select at least one subject.');
        header('Location: faculty_subject.php?faculty_id=' . $facultyId);
        exit;
      }

      $stmt = $conn->prepare("INSERT IGNORE INTO faculty_subject (faculty_id, subject_id) VALUES (?, ?)");
      foreach ($subjectIds as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) {
          $stmt->bind_param('ii', $facultyId, $sid);
          $stmt->execute();
        }
      }
      $stmt->close();

      flash_set('success', 'Subjects assigned successfully.');
      header('Location: faculty_subject.php?faculty_id=' . $facultyId);
      exit;
    }

    if ($action === 'remove') {
      $facultyId = (int)($_POST['faculty_id'] ?? 0);
      $subjectId = (int)($_POST['subject_id'] ?? 0);

      if ($facultyId <= 0 || $subjectId <= 0) {
        flash_set('error', 'Invalid request.');
        header('Location: faculty_subject.php');
        exit;
      }

      $stmt = $conn->prepare("DELETE FROM faculty_subject WHERE faculty_id = ? AND subject_id = ?");
      $stmt->bind_param('ii', $facultyId, $subjectId);
      $stmt->execute();
      $stmt->close();

      flash_set('success', 'Subject unassigned.');
      header('Location: faculty_subject.php?faculty_id=' . $facultyId);
      exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: faculty_subject.php');
    exit;

  } catch (Throwable $e) {
    flash_set('error', 'Action failed: ' . $e->getMessage());
    header('Location: faculty_subject.php');
    exit;
  }
}

/* -----------------------------
   Filters / Selection
------------------------------*/
$selectedFacultyId = (int)($_GET['faculty_id'] ?? 0);
$filterCourseId = (int)($_GET['course_id'] ?? 0);
$filterSemester = trim((string)($_GET['semester'] ?? ''));

/* -----------------------------
   Load dropdown data
------------------------------*/
$facultyList = [];
$coursesList = [];
$subjectsList = [];
$assignedList = [];

// Faculty dropdown
$res = $conn->query("SELECT f.id, f.name, COALESCE(u.is_active, 0) AS is_active
                     FROM faculty f
                     LEFT JOIN users u ON u.id = f.user_id
                     ORDER BY f.name ASC");
$facultyList = $res->fetch_all(MYSQLI_ASSOC);

// Courses dropdown (for optional subject filtering)
$res = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");
$coursesList = $res->fetch_all(MYSQLI_ASSOC);

// Subjects list (filterable)
$where = [];
$params = [];
$types = '';

$sqlSubjects = "SELECT s.id, s.subject_name, s.semester, c.course_name, s.course_id
                FROM subjects s
                JOIN courses c ON c.id = s.course_id";

if ($filterCourseId > 0) {
  $where[] = "s.course_id = ?";
  $types .= 'i';
  $params[] = $filterCourseId;
}
if ($filterSemester !== '') {
  $where[] = "s.semester = ?";
  $types .= 's';
  $params[] = $filterSemester;
}

if ($where) $sqlSubjects .= " WHERE " . implode(" AND ", $where);
$sqlSubjects .= " ORDER BY c.course_name ASC, s.semester ASC, s.subject_name ASC";

if ($types !== '') {
  $stmt = $conn->prepare($sqlSubjects);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $subjectsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} else {
  $subjectsList = $conn->query($sqlSubjects)->fetch_all(MYSQLI_ASSOC);
}

// Assigned subjects for selected faculty
if ($selectedFacultyId > 0) {
  $stmt = $conn->prepare(
    "SELECT s.id AS subject_id, s.subject_name, s.semester, c.course_name
     FROM faculty_subject fs
     JOIN subjects s ON s.id = fs.subject_id
     JOIN courses c ON c.id = s.course_id
     WHERE fs.faculty_id = ?
     ORDER BY c.course_name ASC, s.semester ASC, s.subject_name ASC"
  );
  $stmt->bind_param('i', $selectedFacultyId);
  $stmt->execute();
  $assignedList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

/* -----------------------------
   Layout
------------------------------*/
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
        <div class="glass rounded-4 p-4 border mb-3">
          <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
          <p class="small-muted mb-0">Assign one or multiple subjects to a faculty member.</p>
        </div>

        <?php include __DIR__ . '/../partials/flash_view.php'; ?>

        <div class="row g-3">
          <!-- Left: Select faculty + assign -->
          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3">1) Select Faculty</h5>

              <form method="GET" class="mb-3">
                <label class="form-label fw-semibold">Faculty</label>
                <select class="form-select" name="faculty_id" onchange="this.form.submit()">
                  <option value="0">-- Select faculty --</option>
                  <?php foreach ($facultyList as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= $selectedFacultyId === (int)$f['id'] ? 'selected' : '' ?>>
                      <?= h($f['name']) ?> <?= ((int)$f['is_active'] === 1) ? '' : '(disabled)' ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <div class="mt-3">
                  <h6 class="fw-semibold mb-2">Optional: Filter subjects</h6>

                  <label class="form-label small">Course</label>
                  <select class="form-select" name="course_id">
                    <option value="0">All courses</option>
                    <?php foreach ($coursesList as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= $filterCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= h($c['course_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <label class="form-label small mt-2">Semester (example: 1, 2, A, B)</label>
                  <input class="form-control" name="semester" value="<?= h($filterSemester) ?>" placeholder="Leave blank for all">

                  <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-outline-primary rounded-3" type="submit">
                      <i class="bi bi-funnel me-2"></i>Apply
                    </button>
                    <a class="btn btn-outline-secondary rounded-3"
                       href="faculty_subject.php?faculty_id=<?= (int)$selectedFacultyId ?>">
                      Reset
                    </a>
                  </div>
                </div>
              </form>

              <hr>

              <h5 class="fw-semibold mb-3">2) Assign Subjects</h5>

              <?php if ($selectedFacultyId <= 0): ?>
                <div class="alert alert-info small mb-0">
                  Select a faculty member first to assign subjects.
                </div>
              <?php else: ?>
                <form method="POST">
                  <input type="hidden" name="action" value="assign">
                  <input type="hidden" name="faculty_id" value="<?= (int)$selectedFacultyId ?>">

                  <label class="form-label fw-semibold">Subjects (multi-select)</label>
                  <select class="form-select" name="subject_ids[]" multiple size="8" required>
                    <?php foreach ($subjectsList as $s): ?>
                      <option value="<?= (int)$s['id'] ?>">
                        <?= h($s['course_name']) ?> | <?= h($s['semester']) ?> | <?= h($s['subject_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <div class="form-text small mt-2">
                    Hold <b>Ctrl</b> (Windows) or <b>Cmd</b> (Mac) to select multiple.
                  </div>

                  <div class="d-grid mt-3">
                    <button class="btn btn-primary rounded-3" type="submit">
                      <i class="bi bi-link-45deg me-2"></i>Assign Selected
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <!-- Right: Assigned list -->
          <div class="col-lg-7">
            <div class="glass rounded-4 p-4 border">
              <h5 class="fw-semibold mb-3">Assigned Subjects</h5>

              <?php if ($selectedFacultyId <= 0): ?>
                <div class="alert alert-secondary small mb-0">
                  Select a faculty member to view assigned subjects.
                </div>
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
                      <?php if (!$assignedList): ?>
                        <tr>
                          <td colspan="4" class="text-center text-muted">No subjects assigned yet.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($assignedList as $a): ?>
                          <tr>
                            <td><?= h($a['course_name']) ?></td>
                            <td><?= h($a['semester']) ?></td>
                            <td><?= h($a['subject_name']) ?></td>
                            <td class="text-end">
                              <form method="POST" class="d-inline" onsubmit="return confirm('Unassign this subject?');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="faculty_id" value="<?= (int)$selectedFacultyId ?>">
                                <input type="hidden" name="subject_id" value="<?= (int)$a['subject_id'] ?>">
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
              <?php endif; ?>

              <div class="text-muted small mt-3">
                Tip: Filter by Course and Semester, then select and assign in bulk.
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
