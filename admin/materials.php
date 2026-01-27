<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

$active = 'materials';
$pageTitle = 'All Study Materials';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// Filters
$courseId = (int)($_GET['course_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

// Get courses for filter
$courses_list = $conn->query("
  SELECT id, course_name FROM courses ORDER BY course_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Get subjects for filter
$subjects_list = [];
if ($courseId > 0) {
  $stmt = $conn->prepare("
    SELECT id, subject_name, semester
    FROM subjects
    WHERE course_id = ?
    ORDER BY semester, subject_name
  ");
  $stmt->bind_param('i', $courseId);
  $stmt->execute();
  $subjects_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// Get all materials with filters
$sql = "
  SELECT m.*, s.subject_name, c.course_name, f.name AS faculty_name
  FROM study_materials m
  JOIN subjects s ON s.id = m.subject_id
  JOIN courses c ON c.id = m.course_id
  LEFT JOIN faculty f ON f.id = m.uploaded_by_faculty_id
  WHERE 1=1
";
$types = '';
$params = [];

if ($courseId > 0) {
  $sql .= " AND m.course_id = ?";
  $types .= 'i';
  $params[] = $courseId;
}

if ($subjectId > 0) {
  $sql .= " AND m.subject_id = ?";
  $types .= 'i';
  $params[] = $subjectId;
}

$sql .= " ORDER BY m.created_at DESC";

$materials_list = [];
if ($types === '') {
  $materials_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
} else {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $materials_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        <div class="text-muted mb-3">View all study materials uploaded by faculty.</div>

        <div class="card border-0 rounded-4 shadow-sm mb-3">
          <div class="card-body p-4">
            <form method="GET" class="row g-2 align-items-end">
              <div class="col-md-5">
                <label class="form-label fw-semibold">Course</label>
                <select class="form-select" name="course_id" id="courseSelect" onchange="this.form.submit();">
                  <option value="0">All Courses</option>
                  <?php foreach ($courses_list as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>>
                      <?= h($c['course_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label fw-semibold">Subject</label>
                <select class="form-select" id="subjectSelect" name="subject_id">
                  <option value="0">All</option>
                  <?php foreach ($subjects_list as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>>
                      <?= h($s['semester']) ?> - <?= h($s['subject_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i></button>
              </div>
            </form>
          </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
          <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Materials List</h5>

            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Course | Semester</th>
                    <th>Subject</th>
                    <th>Uploaded by</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$materials_list): ?>
                    <tr><td colspan="6" class="text-center text-muted">No materials found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($materials_list as $m): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h($m['title']) ?></div>
                          <?php if (!empty($m['description'])): ?>
                            <div class="text-muted small"><?= h($m['description']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge bg-light text-dark"><?= h($m['course_name']) ?></span>
                          <span class="badge bg-light text-dark"><?= h($m['semester']) ?></span>
                        </td>
                        <td><?= h($m['subject_name']) ?></td>
                        <td><?= h((string)$m['faculty_name']) ?></td>
                        <td class="text-muted small"><?= h($m['created_at']) ?></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-primary" href="../download_material.php?id=<?= (int)$m['id'] ?>">
                            <i class="bi bi-download"></i>
                          </a>
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
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
