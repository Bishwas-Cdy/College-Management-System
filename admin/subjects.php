<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../partials/flash.php';

$pageTitle = 'Subjects';
$active = 'subjects';

$success = flash_get('success');
$error = flash_get('error');

$courses = [];
$res = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");
if ($res) $courses = $res->fetch_all(MYSQLI_ASSOC);

$edit = null;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($edit_id > 0) {
  $stmt = $conn->prepare("SELECT id, subject_name, course_id, semester FROM subjects WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $edit_id);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'create' || $action === 'update') {
    $subject_name = trim((string)($_POST['subject_name'] ?? ''));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $semester = trim((string)($_POST['semester'] ?? ''));

    if ($subject_name === '' || $course_id <= 0 || $semester === '') {
      flash_set('error', 'Subject name, course, and semester are required.');
      header('Location: subjects.php');
      exit;
    }

    if (!preg_match('/^[A-Z]\d+$/', $semester)) {
      flash_set('error', 'Semester must be format like A1, B2, S5 (letter + number).');
      header('Location: subjects.php');
      exit;
    }

    if ($action === 'create') {
      try {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, course_id, semester) VALUES (?, ?, ?)");
        if (!$stmt) {
          throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param('sis', $subject_name, $course_id, $semester);

        if ($stmt->execute()) {
          $subjectId = $conn->insert_id;
          $userId = (int)($_SESSION['user_id'] ?? 0);
          @log_audit($conn, $userId, 'create', 'subjects', $subjectId, "Name: {$subject_name}, Semester: {$semester}");
          flash_set('success', 'Subject created.');
        }
        else throw new Exception("Execute failed: " . $stmt->error);

        $stmt->close();
      } catch (Throwable $e) {
        flash_set('error', 'Error creating subject: ' . $e->getMessage());
      }
      header('Location: subjects.php');
      exit;
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        flash_set('error', 'Invalid update request.');
        header('Location: subjects.php');
        exit;
      }

      try {
        $stmt = $conn->prepare("UPDATE subjects SET subject_name=?, course_id=?, semester=? WHERE id=?");
        if (!$stmt) {
          throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param('sisi', $subject_name, $course_id, $semester, $id);

        if ($stmt->execute()) {
          $userId = (int)($_SESSION['user_id'] ?? 0);
          try {
            @log_audit($conn, $userId, 'update', 'subjects', $id, "Name: {$subject_name}, Semester: {$semester}");
          } catch (Throwable $e) {
            // Audit logging failed, but update succeeded
          }
          flash_set('success', 'Subject updated.');
        }
        else throw new Exception("Execute failed: " . $stmt->error);

        $stmt->close();
      } catch (Throwable $e) {
        flash_set('error', 'Error updating subject: ' . $e->getMessage());
      }
      header('Location: subjects.php');
      exit;
    }
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('error', 'Invalid delete request.');
      header('Location: subjects.php');
      exit;
    }

    try {
      $stmt = $conn->prepare("DELETE FROM subjects WHERE id=?");
      if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
      }
      $stmt->bind_param('i', $id);

      if ($stmt->execute()) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
          @log_audit($conn, $userId, 'delete', 'subjects', $id, 'Subject deleted');
        } catch (Throwable $e) {
          // Audit logging failed, but deletion succeeded
        }
        flash_set('success', 'Subject deleted.');
      }
      else throw new Exception("Execute failed: " . $stmt->error);

      $stmt->close();
    } catch (Throwable $e) {
      flash_set('error', 'Error deleting subject: ' . $e->getMessage());
    }
    header('Location: subjects.php');
    exit;
  }

  flash_set('error', 'Unknown action.');
  header('Location: subjects.php');
  exit;
}

$subjects = [];
$sql = "SELECT s.id, s.subject_name, s.semester, c.course_name
        FROM subjects s
        JOIN courses c ON c.id = s.course_id
        ORDER BY s.id DESC";
$res = $conn->query($sql);
if ($res) $subjects = $res->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
        <div class="glass rounded-4 p-4 border mb-3">
          <h3 class="fw-bold mb-1">Subjects</h3>
          <p class="small-muted mb-0">Manage subjects per course and semester.</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row g-3">
          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><?= $edit ? 'Edit Subject' : 'Add Subject' ?></h5>

              <form method="POST">
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Subject Name</label>
                  <input class="form-control" name="subject_name" required value="<?= htmlspecialchars($edit['subject_name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Course</label>
                  <select class="form-select" name="course_id" required>
                    <option value="">Select course</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= (isset($edit['course_id']) && (int)$edit['course_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['course_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Semester</label>
                  <input class="form-control" type="text" name="semester" placeholder="e.g., 1, 2, A, B" required value="<?= htmlspecialchars((string)($edit['semester'] ?? '')) ?>">
                </div>

                <div class="d-flex gap-2">
                  <button class="btn btn-primary rounded-3" type="submit">
                    <i class="bi bi-save me-2"></i><?= $edit ? 'Update' : 'Create' ?>
                  </button>
                  <?php if ($edit): ?><a class="btn btn-outline-dark rounded-3" href="subjects.php">Cancel</a><?php endif; ?>
                </div>
              </form>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="glass rounded-4 p-4 border">
              <h5 class="fw-semibold mb-3">All Subjects</h5>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Subject</th>
                      <th>Course</th>
                      <th>Semester</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($subjects as $s): ?>
                      <tr>
                        <td><?= (int)$s['id'] ?></td>
                        <td><?= htmlspecialchars($s['subject_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($s['course_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($s['semester'] ?? '') ?></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-primary" href="subjects.php?edit=<?= (int)$s['id'] ?>"><i class="bi bi-pencil"></i></a>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this subject?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$subjects): ?><tr><td colspan="5" class="text-center text-muted">No subjects yet.</td></tr><?php endif; ?>
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
