<?php
// admin/timetable.php (smart dropdown version)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../partials/flash.php';

$active = 'timetable';
$pageTitle = 'Timetable (Class Routine)';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

/* -----------------------------
   Smart create-form state (GET)
------------------------------*/
$formCourseId  = (int)($_GET['fcourse_id'] ?? 0);
$formSemester  = trim((string)($_GET['fsemester'] ?? ''));

/* -----------------------------
   List filters (GET)
------------------------------*/
$filterCourseId = (int)($_GET['course_id'] ?? 0);
$filterSemester = trim((string)($_GET['semester'] ?? ''));

/* -----------------------------
   Handle POST actions
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $courseId  = (int)($_POST['course_id'] ?? 0);
      $semester  = trim((string)($_POST['semester'] ?? ''));
      $subjectId = (int)($_POST['subject_id'] ?? 0);
      $facultyId = (int)($_POST['faculty_id'] ?? 0); // optional
      $day       = trim((string)($_POST['day_of_week'] ?? ''));
      $start     = trim((string)($_POST['start_time'] ?? ''));
      $end       = trim((string)($_POST['end_time'] ?? ''));
      $room      = trim((string)($_POST['room'] ?? ''));

      if ($courseId <= 0 || $semester === '' || $subjectId <= 0 || $day === '' || $start === '' || $end === '') {
        flash_set('error', 'Please fill all required fields (Course, Semester, Subject, Day, Start, End).');
        header('Location: timetable.php?fcourse_id=' . $courseId . '&fsemester=' . urlencode($semester));
        exit;
      }

      // Faculty optional: store NULL if not selected
      $facultyIdSql = ($facultyId > 0) ? $facultyId : null;

      // To safely insert NULL with mysqli, we do conditional SQL
      if ($facultyId > 0) {
        $stmt = $conn->prepare("
          INSERT INTO timetable (course_id, semester, subject_id, faculty_id, day_of_week, start_time, end_time, room)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isiissss', $courseId, $semester, $subjectId, $facultyIdSql, $day, $start, $end, $room);
      } else {
        $stmt = $conn->prepare("
          INSERT INTO timetable (course_id, semester, subject_id, faculty_id, day_of_week, start_time, end_time, room)
          VALUES (?, ?, ?, NULL, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issssss', $courseId, $semester, $subjectId, $day, $start, $end, $room);
      }

      $stmt->execute();
      $stmt->close();

      flash_set('success', 'Timetable entry created.');
      header('Location: timetable.php?fcourse_id=' . $courseId . '&fsemester=' . urlencode($semester));
      exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        flash_set('error', 'Invalid timetable entry.');
        header('Location: timetable.php');
        exit;
      }

      $stmt = $conn->prepare("DELETE FROM timetable WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      flash_set('success', 'Timetable entry deleted.');
      header('Location: timetable.php');
      exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: timetable.php');
    exit;

  } catch (Throwable $e) {
    flash_set('error', 'Action failed: ' . $e->getMessage());
    header('Location: timetable.php');
    exit;
  }
}

/* -----------------------------
   Dropdown data
------------------------------*/
$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetch_all(MYSQLI_ASSOC);
$faculty = $conn->query("SELECT id, name FROM faculty ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

/* Smart subject list for CREATE form (only for chosen course+semester) */
$formSubjects = [];
if ($formCourseId > 0 && $formSemester !== '') {
  $stmt = $conn->prepare("
    SELECT s.id, s.subject_name, s.semester, c.course_name
    FROM subjects s
    JOIN courses c ON c.id = s.course_id
    WHERE s.course_id = ? AND s.semester = ?
    ORDER BY s.subject_name ASC
  ");
  $stmt->bind_param('is', $formCourseId, $formSemester);
  $stmt->execute();
  $formSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

/* -----------------------------
   Timetable list (with filters)
------------------------------*/
$where = [];
$params = [];
$types = '';

$sqlList = "
  SELECT
    t.id,
    t.day_of_week,
    t.start_time,
    t.end_time,
    t.room,
    t.semester,
    c.course_name,
    s.subject_name,
    COALESCE(f.name, '') AS faculty_name
  FROM timetable t
  JOIN courses c ON c.id = t.course_id
  JOIN subjects s ON s.id = t.subject_id
  LEFT JOIN faculty f ON f.id = t.faculty_id
";

if ($filterCourseId > 0) {
  $where[] = "t.course_id = ?";
  $types .= 'i';
  $params[] = $filterCourseId;
}
if ($filterSemester !== '') {
  $where[] = "t.semester = ?";
  $types .= 's';
  $params[] = $filterSemester;
}
if ($where) $sqlList .= " WHERE " . implode(" AND ", $where);

$sqlList .= " ORDER BY FIELD(t.day_of_week,'Sun','Mon','Tue','Wed','Thu','Fri','Sat'), t.start_time ASC";

$list = [];
if ($types !== '') {
  $stmt = $conn->prepare($sqlList);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} else {
  $list = $conn->query($sqlList)->fetch_all(MYSQLI_ASSOC);
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
          <div class="text-muted">Smart form: choose Course + Semester, then Subject list auto-filters.</div>
        </div>
      </div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="row g-3">

        <!-- CREATE -->
        <div class="col-lg-5">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Create Timetable Entry</h5>

              <!-- Step 1: pick course+semester (GET reload to filter subjects) -->
              <form method="GET" class="mb-3">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Course *</label>
                  <select class="form-select" name="fcourse_id" onchange="this.form.submit()" required>
                    <option value="0">-- Select course --</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= $formCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= h($c['course_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-2">
                  <label class="form-label fw-semibold">Semester *</label>
                  <input
                    class="form-control"
                    name="fsemester"
                    value="<?= h($formSemester) ?>"
                    placeholder="Example: A1, A2, I4"
                    onblur="this.form.submit()"
                    required
                  >
                </div>

                <div class="form-text">
                  Select Course and enter Semester, then the Subject dropdown will update.
                </div>
              </form>

              <hr>

              <!-- Step 2: actual create POST -->
              <form method="POST">
                <input type="hidden" name="action" value="create">

                <input type="hidden" name="course_id" value="<?= (int)$formCourseId ?>">
                <input type="hidden" name="semester" value="<?= h($formSemester) ?>">

                <div class="mb-3">
                  <label class="form-label fw-semibold">Subject *</label>

                  <?php if ($formCourseId <= 0 || $formSemester === ''): ?>
                    <div class="alert alert-info mb-0">
                      Choose Course and Semester above to load subjects.
                    </div>
                  <?php else: ?>
                    <?php if (!$formSubjects): ?>
                      <div class="alert alert-warning mb-0">
                        No subjects found for this Course + Semester. Create subjects first.
                      </div>
                    <?php else: ?>
                      <select class="form-select" name="subject_id" required>
                        <option value="0">-- Select subject --</option>
                        <?php foreach ($formSubjects as $s): ?>
                          <option value="<?= (int)$s['id'] ?>">
                            <?= h($s['subject_name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Faculty</label>
                  <select class="form-select" name="faculty_id">
                    <option value="0">-- None --</option>
                    <?php foreach ($faculty as $f): ?>
                      <option value="<?= (int)$f['id'] ?>"><?= h($f['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Day *</label>
                    <select class="form-select" name="day_of_week" required>
                      <option value="Sun">Sun</option>
                      <option value="Mon">Mon</option>
                      <option value="Tue">Tue</option>
                      <option value="Wed">Wed</option>
                      <option value="Thu">Thu</option>
                      <option value="Fri">Fri</option>
                      <option value="Sat">Sat</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Room</label>
                    <input class="form-control" name="room" placeholder="Room 101">
                  </div>
                </div>

                <div class="row g-2 mt-1">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Start *</label>
                    <input type="time" class="form-control" name="start_time" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">End *</label>
                    <input type="time" class="form-control" name="end_time" required>
                  </div>
                </div>

                <div class="d-grid mt-4">
                  <button class="btn btn-primary btn-lg rounded-3" type="submit" <?= (!$formSubjects ? 'disabled' : '') ?>>
                    <i class="bi bi-plus-circle me-2"></i>Add Entry
                  </button>
                </div>

              </form>
            </div>
          </div>
        </div>

        <!-- LIST -->
        <div class="col-lg-7">
          <div class="card border-0 rounded-4 shadow-sm mb-3">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Filter List</h5>
              <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                  <label class="form-label small">Course</label>
                  <select class="form-select" name="course_id">
                    <option value="0">All</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= $filterCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= h($c['course_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small">Semester</label>
                  <input class="form-control" name="semester" value="<?= h($filterSemester) ?>" placeholder="A1">
                </div>
                <div class="col-md-2 d-grid">
                  <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-funnel"></i>
                  </button>
                </div>

                <!-- preserve create-form state when filtering list -->
                <input type="hidden" name="fcourse_id" value="<?= (int)$formCourseId ?>">
                <input type="hidden" name="fsemester" value="<?= h($formSemester) ?>">
              </form>
            </div>
          </div>

          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Timetable Entries</h5>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Day</th>
                      <th>Time</th>
                      <th>Course</th>
                      <th>Sem</th>
                      <th>Subject</th>
                      <th>Faculty</th>
                      <th>Room</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$list): ?>
                      <tr><td colspan="8" class="text-center text-muted">No timetable entries yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($list as $t): ?>
                        <tr>
                          <td><?= h($t['day_of_week']) ?></td>
                          <td><?= h(substr($t['start_time'],0,5)) ?>–<?= h(substr($t['end_time'],0,5)) ?></td>
                          <td><?= h($t['course_name']) ?></td>
                          <td><?= h($t['semester']) ?></td>
                          <td><?= h($t['subject_name']) ?></td>
                          <td><?= h($t['faculty_name']) ?></td>
                          <td><?= h((string)$t['room']) ?></td>
                          <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this entry?');">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit">
                                <i class="bi bi-trash"></i>
                              </button>
                            </form>
                          </td>
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
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
