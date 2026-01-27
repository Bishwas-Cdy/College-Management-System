<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['student']);

$active = 'materials';
$pageTitle = 'Study Materials';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// student profile
$stmt = $conn->prepare("SELECT id, course_id, semester FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$st = $stmt->get_result()->fetch_assoc();
$stmt->close();

$studentId = (int)($st['id'] ?? 0);
$courseId = (int)($st['course_id'] ?? 0);
$semester = (string)($st['semester'] ?? '');

$subjectId = (int)($_GET['subject_id'] ?? 0);

$subjects_list = [];
$materials_list = [];

if ($courseId > 0 && trim($semester) !== '') {
  $stmt = $conn->prepare("
    SELECT id, subject_name
    FROM subjects
    WHERE course_id = ? AND semester = ?
    ORDER BY subject_name ASC
  ");
  $stmt->bind_param('is', $courseId, $semester);
  $stmt->execute();
  $subjects_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $sql = "
    SELECT m.*, s.subject_name, f.name AS faculty_name
    FROM study_materials m
    JOIN subjects s ON s.id = m.subject_id
    LEFT JOIN faculty f ON f.id = m.uploaded_by_faculty_id
    WHERE m.course_id = ? AND m.semester = ?
  ";
  $types = 'is';
  $params = [$courseId, $semester];

  if ($subjectId > 0) {
    $sql .= " AND m.subject_id = ?";
    $types .= 'i';
    $params[] = $subjectId;
  }

  $sql .= " ORDER BY m.created_at DESC";

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
        <div class="text-muted mb-3">Download PDFs uploaded by faculty.</div>

      <div class="card border-0 rounded-4 shadow-sm mb-3">
        <div class="card-body p-4">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-10">
              <label class="form-label fw-semibold">Filter by Subject</label>
              <select class="form-select" name="subject_id">
                <option value="0">All</option>
                <?php foreach ($subjects_list as $s): ?>
                  <option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= h($s['subject_name']) ?>
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
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Subject</th>
                  <th>Uploaded by</th>
                  <th>Uploaded</th>
                  <th class="text-end">Download</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$materials_list): ?>
                  <tr><td colspan="5" class="text-center text-muted">No materials available.</td></tr>
                <?php else: ?>
                  <?php foreach ($materials_list as $m): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h($m['title']) ?></div>
                        <?php if (!empty($m['description'])): ?>
                          <div class="text-muted small"><?= h($m['description']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><?= h($m['subject_name']) ?></td>
                      <td><?= h((string)$m['faculty_name']) ?></td>
                      <td class="text-muted small"><?= h($m['created_at']) ?></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-primary" href="../download_material.php?id=<?= (int)$m['id'] ?>">
                          <i class="bi bi-download me-1"></i>Download
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
