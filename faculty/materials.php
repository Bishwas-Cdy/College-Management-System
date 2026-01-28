<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['faculty']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'materials';
$pageTitle = 'Study Materials';

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

// assigned subjects
$stmt = $conn->prepare("
  SELECT s.id, s.subject_name, s.course_id, s.semester, c.course_name
  FROM faculty_subject fs
  JOIN subjects s ON s.id = fs.subject_id
  JOIN courses c ON c.id = s.course_id
  WHERE fs.faculty_id = ?
  ORDER BY c.course_name, s.semester, s.subject_name
");
$stmt->bind_param('i', $facultyId);
$stmt->execute();
$subjects_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);

  $stmt = $conn->prepare("SELECT file_path FROM study_materials WHERE id = ? AND uploaded_by_faculty_id = ? LIMIT 1");
  $stmt->bind_param('ii', $id, $facultyId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    flash_set('error', 'Not found or not allowed.');
    header('Location: materials.php');
    exit;
  }

  // delete db row first
  $stmt = $conn->prepare("DELETE FROM study_materials WHERE id = ? AND uploaded_by_faculty_id = ?");
  $stmt->bind_param('ii', $id, $facultyId);
  $stmt->execute();
  $stmt->close();

  // delete file
  $full = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . $row['file_path'];
  if ($full && is_file($full)) @unlink($full);

  flash_set('success', 'Material deleted.');
  header('Location: materials.php');
  exit;
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
  $subjectId = (int)($_POST['subject_id'] ?? 0);
  $title = trim((string)($_POST['title'] ?? ''));
  $description = trim((string)($_POST['description'] ?? ''));

  if ($subjectId <= 0 || $title === '') {
    flash_set('error', 'Subject and title are required.');
    header('Location: materials.php');
    exit;
  }

  // verify subject assigned
  $stmt = $conn->prepare("SELECT s.course_id, s.semester FROM subjects s JOIN faculty_subject fs ON fs.subject_id=s.id WHERE s.id=? AND fs.faculty_id=? LIMIT 1");
  $stmt->bind_param('ii', $subjectId, $facultyId);
  $stmt->execute();
  $sub = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$sub) {
    flash_set('error', 'Not allowed: subject not assigned.');
    header('Location: materials.php');
    exit;
  }

  if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    flash_set('error', 'File upload failed.');
    header('Location: materials.php');
    exit;
  }

  $file = $_FILES['file'];

  // basic validation
  $maxBytes = 10 * 1024 * 1024; // 10MB
  if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
    flash_set('error', 'File size must be <= 10MB.');
    header('Location: materials.php');
    exit;
  }

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if ($ext !== 'pdf') {
    flash_set('error', 'Only PDF files allowed.');
    header('Location: materials.php');
    exit;
  }

  // mime check (best-effort)
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  if ($mime !== 'application/pdf') {
    flash_set('error', 'Invalid file type.');
    header('Location: materials.php');
    exit;
  }

  $uploadDir = realpath(__DIR__ . '/../uploads/materials');
  if (!$uploadDir) {
    flash_set('error', 'Upload folder missing: /uploads/materials');
    header('Location: materials.php');
    exit;
  }

  $safeName = bin2hex(random_bytes(16)) . '.pdf';
  $dest = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    flash_set('error', 'Could not save file.');
    header('Location: materials.php');
    exit;
  }

  $relativePath = 'uploads/materials/' . $safeName;

  $courseId = (int)$sub['course_id'];
  $semester = (string)$sub['semester'];
  $fileType = 'pdf';

  $stmt = $conn->prepare("
    INSERT INTO study_materials (subject_id, course_id, semester, uploaded_by_faculty_id, title, description, file_path, file_type)
    VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?)
  ");
  $stmt->bind_param('iisissss', $subjectId, $courseId, $semester, $facultyId, $title, $description, $relativePath, $fileType);
  $stmt->execute();
  $stmt->close();

  // Send notifications to enrolled students
  $stmtEnrolled = $conn->prepare("
    SELECT DISTINCT u.id
    FROM enrollments e
    JOIN students s ON s.id = e.student_id
    JOIN users u ON u.id = s.user_id
    WHERE e.subject_id = ?
  ");
  $stmtEnrolled->bind_param('i', $subjectId);
  $stmtEnrolled->execute();
  $enrolled = $stmtEnrolled->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtEnrolled->close();

  if (!empty($enrolled)) {
    $enrolledUserIds = array_map(fn($row) => (int)$row['id'], $enrolled);
    $notifMsg = "New material uploaded for " . htmlspecialchars($title);
    send_notifications_batch($conn, $enrolledUserIds, $notifMsg);
  }

  flash_set('success', 'Material uploaded.');
  header('Location: materials.php');
  exit;
}

// list my uploads
$stmt = $conn->prepare("
  SELECT m.*, s.subject_name, c.course_name
  FROM study_materials m
  JOIN subjects s ON s.id = m.subject_id
  JOIN courses c ON c.id = m.course_id
  WHERE m.uploaded_by_faculty_id = ?
  ORDER BY m.created_at DESC
");
$stmt->bind_param('i', $facultyId);
$stmt->execute();
$materials_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
        <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
        <div class="text-muted mb-3">Upload PDFs for your assigned subjects.</div>

        <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Upload PDF</h5>

              <form method="POST" enctype="multipart/form-data" class="row g-2">
                <input type="hidden" name="action" value="upload">

                <div class="col-12">
                  <label class="form-label fw-semibold">Subject *</label>
                  <select class="form-select" name="subject_id" required>
                    <option value="0">-- Select --</option>
                    <?php foreach ($subjects_list as $s): ?>
                      <option value="<?= (int)$s['id'] ?>">
                        <?= h($s['course_name']) ?> | <?= h($s['semester']) ?> | <?= h($s['subject_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Title *</label>
                  <input class="form-control" name="title" placeholder="Unit 1 Notes" required>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Description</label>
                  <textarea class="form-control" name="description" rows="3" placeholder="Optional"></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">PDF File *</label>
                  <input class="form-control" type="file" name="file" accept="application/pdf" required>
                  <div class="form-text">Max 10MB. PDF only.</div>
                </div>

                <div class="col-12 d-grid mt-2">
                  <button class="btn btn-primary" type="submit">
                    <i class="bi bi-upload me-2"></i>Upload
                  </button>
                </div>
              </form>

            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">My Uploads</h5>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Title</th>
                      <th>Subject</th>
                      <th>Uploaded</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$materials_list): ?>
                      <tr><td colspan="4" class="text-center text-muted">No materials yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($materials_list as $m): ?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?= h($m['title']) ?></div>
                            <?php if (!empty($m['description'])): ?>
                              <div class="text-muted small"><?= h($m['description']) ?></div>
                            <?php endif; ?>
                          </td>
                          <td><?= h($m['course_name']) ?> | <?= h($m['semester']) ?><br><span class="text-muted small"><?= h($m['subject_name']) ?></span></td>
                          <td class="text-muted small"><?= h($m['created_at']) ?></td>
                          <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="../download_material.php?id=<?= (int)$m['id'] ?>">
                              <i class="bi bi-download"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this file?');">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
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
