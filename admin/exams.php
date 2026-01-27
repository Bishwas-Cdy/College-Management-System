<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'exams';
$pageTitle = 'Exams';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetch_all(MYSQLI_ASSOC);

/* CREATE / TOGGLE / DELETE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $examName = trim((string)($_POST['exam_name'] ?? ''));
      $courseId = (int)($_POST['course_id'] ?? 0);
      $semester = trim((string)($_POST['semester'] ?? ''));
      $examDate = trim((string)($_POST['exam_date'] ?? ''));

      if ($examName === '' || $courseId <= 0 || $semester === '') {
        flash_set('error', 'Exam name, course, and semester are required.');
        header('Location: exams.php');
        exit;
      }

      $stmt = $conn->prepare("
        INSERT INTO exams (exam_name, course_id, semester, exam_date, is_published)
        VALUES (?, ?, ?, NULLIF(?, ''), 0)
      ");
      $stmt->bind_param('siss', $examName, $courseId, $semester, $examDate);
      $stmt->execute();
      $stmt->close();

      flash_set('success', 'Exam created.');
      header('Location: exams.php');
      exit;
    }

    if ($action === 'toggle') {
      $id = (int)($_POST['id'] ?? 0);
      $to = (int)($_POST['to'] ?? 0); // 0/1
      $stmt = $conn->prepare("UPDATE exams SET is_published = ? WHERE id = ?");
      $stmt->bind_param('ii', $to, $id);
      $stmt->execute();
      $stmt->close();

      flash_set('success', $to ? 'Exam published.' : 'Exam unpublished.');
      header('Location: exams.php');
      exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      flash_set('success', 'Exam deleted.');
      header('Location: exams.php');
      exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: exams.php');
    exit;

  } catch (Throwable $e) {
    flash_set('error', 'Action failed: ' . $e->getMessage());
    header('Location: exams.php');
    exit;
  }
}

/* LIST + FILTER */
$courseId = (int)($_GET['course_id'] ?? 0);
$semester = trim((string)($_GET['semester'] ?? ''));

$where = [];
$params = [];
$types = '';

$sql = "
  SELECT e.*, c.course_name
  FROM exams e
  JOIN courses c ON c.id = e.course_id
";
if ($courseId > 0) { $where[] = "e.course_id = ?"; $types .= 'i'; $params[] = $courseId; }
if ($semester !== '') { $where[] = "e.semester = ?"; $types .= 's'; $params[] = $semester; }
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY e.created_at DESC";

$exams_list = [];
if ($types !== '') {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $exams_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} else {
  $exams_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
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
      <div class="text-muted mb-3">Create exams and publish when ready.</div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Create Exam</h5>

              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="create">

                <div class="col-12">
                  <label class="form-label fw-semibold">Exam name *</label>
                  <input class="form-control" name="exam_name" placeholder="Midterm / Final" required>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Course *</label>
                  <select class="form-select" name="course_id" required>
                    <option value="0">-- Select --</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?= (int)$c['id'] ?>"><?= h($c['course_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Semester *</label>
                  <input class="form-control" name="semester" placeholder="A1 / I4" required>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Exam date</label>
                  <input type="date" class="form-control" name="exam_date">
                </div>

                <div class="col-12 d-grid mt-2">
                  <button class="btn btn-primary" type="submit">
                    <i class="bi bi-plus-circle me-2"></i>Create
                  </button>
                </div>
              </form>

            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 rounded-4 shadow-sm mb-3">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Filter</h5>
              <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                  <label class="form-label small">Course</label>
                  <select class="form-select" name="course_id">
                    <option value="0">All</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= h($c['course_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small">Semester</label>
                  <input class="form-control" name="semester" value="<?= h($semester) ?>" placeholder="A1">
                </div>
                <div class="col-md-2 d-grid">
                  <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i></button>
                </div>
              </form>
            </div>
          </div>

          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Exams</h5>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Course</th>
                      <th>Sem</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$exams_list): ?>
                      <tr><td colspan="6" class="text-center text-muted">No exams yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($exams_list as $e): ?>
                        <tr>
                          <td><?= h($e['exam_name']) ?></td>
                          <td><?= h($e['course_name']) ?></td>
                          <td><?= h($e['semester']) ?></td>
                          <td>
                            <?= h((string)$e['exam_date']) ?>
                            <?php if (empty($e['exam_date'])): ?>
                              <span class="badge bg-warning text-dark" title="Date not set">⚠️</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ((int)$e['is_published'] === 1): ?>
                              <span class="badge bg-success">Published</span>
                            <?php else: ?>
                              <span class="badge bg-secondary">Draft</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="action" value="toggle">
                              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                              <input type="hidden" name="to" value="<?= (int)$e['is_published'] ? 0 : 1 ?>">
                              <button class="btn btn-sm btn-outline-primary" type="submit">
                                <?= (int)$e['is_published'] ? 'Unpublish' : 'Publish' ?>
                              </button>
                            </form>

                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete exam? Marks will be deleted too.');">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
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
