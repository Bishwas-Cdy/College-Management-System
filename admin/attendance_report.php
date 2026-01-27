<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$active = 'attendance_report';
$pageTitle = 'Attendance Reports';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("
  SELECT s.id, s.subject_name, s.semester, c.course_name
  FROM subjects s
  JOIN courses c ON c.id = s.course_id
  ORDER BY c.course_name ASC, s.semester ASC, s.subject_name ASC
")->fetch_all(MYSQLI_ASSOC);

$courseId = (int)($_GET['course_id'] ?? 0);
$semester = trim((string)($_GET['semester'] ?? ''));
$subjectId = (int)($_GET['subject_id'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$where = [];
$params = [];
$types = '';

$sql = "
  SELECT
    a.date,
    c.course_name,
    a.semester,
    s.subject_name,
    COUNT(ad.id) AS total_marked,
    SUM(CASE WHEN ad.status='present' THEN 1 ELSE 0 END) AS present_count,
    SUM(CASE WHEN ad.status='absent' THEN 1 ELSE 0 END) AS absent_count
  FROM attendance a
  JOIN courses c ON c.id = a.course_id
  JOIN subjects s ON s.id = a.subject_id
  LEFT JOIN attendance_details ad ON ad.attendance_id = a.id
";

if ($courseId > 0) { $where[] = "a.course_id = ?"; $types .= 'i'; $params[] = $courseId; }
if ($semester !== '') { $where[] = "a.semester = ?"; $types .= 's'; $params[] = $semester; }
if ($subjectId > 0) { $where[] = "a.subject_id = ?"; $types .= 'i'; $params[] = $subjectId; }
if ($dateFrom !== '') { $where[] = "a.date >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = "a.date <= ?"; $types .= 's'; $params[] = $dateTo; }

if ($where) $sql .= " WHERE " . implode(" AND ", $where);

$sql .= " GROUP BY a.id ORDER BY a.date DESC";

$rows = [];
if ($types !== '') {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} else {
  $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
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
      <div class="text-muted mb-3">Filter sessions and see present/absent totals.</div>

      <div class="card border-0 rounded-4 shadow-sm mb-3">
        <div class="card-body p-4">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
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
            <div class="col-md-2">
              <label class="form-label small">Semester</label>
              <input class="form-control" name="semester" value="<?= h($semester) ?>" placeholder="A1">
            </div>
            <div class="col-md-3">
              <label class="form-label small">Subject</label>
              <select class="form-select" name="subject_id">
                <option value="0">All</option>
                <?php foreach ($subjects as $s): ?>
                  <option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= h($s['course_name']) ?> | <?= h($s['semester']) ?> | <?= h($s['subject_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small">From</label>
              <input type="date" class="form-control" name="date_from" value="<?= h($dateFrom) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label small">To</label>
              <input type="date" class="form-control" name="date_to" value="<?= h($dateTo) ?>">
            </div>
            <div class="col-md-12 d-grid mt-2">
              <button class="btn btn-outline-primary" type="submit">
                <i class="bi bi-funnel me-2"></i>Apply Filters
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Course</th>
                  <th>Sem</th>
                  <th>Subject</th>
                  <th class="text-end">Marked</th>
                  <th class="text-end">Present</th>
                  <th class="text-end">Absent</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="7" class="text-center text-muted">No sessions found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td><?= h($r['date']) ?></td>
                      <td><?= h($r['course_name']) ?></td>
                      <td><?= h($r['semester']) ?></td>
                      <td><?= h($r['subject_name']) ?></td>
                      <td class="text-end"><?= (int)$r['total_marked'] ?></td>
                      <td class="text-end"><?= (int)$r['present_count'] ?></td>
                      <td class="text-end"><?= (int)$r['absent_count'] ?></td>
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
