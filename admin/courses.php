<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../partials/flash.php';

$pageTitle = 'Courses';
$active = 'courses';

$success = flash_get('success');
$error = flash_get('error');

$edit = null;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($edit_id > 0) {
  $stmt = $conn->prepare("SELECT id, course_name, duration FROM courses WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $edit_id);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'create') {
    $name = trim((string)($_POST['course_name'] ?? ''));
    $duration = (int)($_POST['duration'] ?? 0);

    if ($name === '' || $duration <= 0) {
      flash_set('error', 'Course name and duration are required.');
      header('Location: courses.php');
      exit;
    }

    $stmt = $conn->prepare("INSERT INTO courses (course_name, duration) VALUES (?, ?)");
    $stmt->bind_param('si', $name, $duration);

    if ($stmt->execute()) flash_set('success', 'Course created.');
    else flash_set('error', 'Failed to create course. Course name might already exist.');

    $stmt->close();
    header('Location: courses.php');
    exit;
  }

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['course_name'] ?? ''));
    $duration = (int)($_POST['duration'] ?? 0);

    if ($id <= 0 || $name === '' || $duration <= 0) {
      flash_set('error', 'Invalid update request.');
      header('Location: courses.php');
      exit;
    }

    $stmt = $conn->prepare("UPDATE courses SET course_name=?, duration=? WHERE id=?");
    $stmt->bind_param('sii', $name, $duration, $id);

    if ($stmt->execute()) flash_set('success', 'Course updated.');
    else flash_set('error', 'Failed to update course.');

    $stmt->close();
    header('Location: courses.php');
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('error', 'Invalid delete request.');
      header('Location: courses.php');
      exit;
    }

    $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) flash_set('success', 'Course deleted.');
    else flash_set('error', 'Cannot delete course. It may be linked to subjects/students/timetable.');

    $stmt->close();
    header('Location: courses.php');
    exit;
  }

  flash_set('error', 'Unknown action.');
  header('Location: courses.php');
  exit;
}

$courses = [];
$res = $conn->query("SELECT id, course_name, duration, created_at FROM courses ORDER BY id DESC");
if ($res) $courses = $res->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
        <div class="glass rounded-4 p-4 border mb-3">
          <h3 class="fw-bold mb-1">Courses</h3>
          <p class="small-muted mb-0">Create and manage courses.</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row g-3">
          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><?= $edit ? 'Edit Course' : 'Add Course' ?></h5>

              <form method="POST">
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Course Name</label>
                  <input class="form-control" name="course_name" required value="<?= htmlspecialchars($edit['course_name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Duration (years)</label>
                  <input class="form-control" type="number" min="1" name="duration" required value="<?= htmlspecialchars((string)($edit['duration'] ?? '')) ?>">
                </div>

                <div class="d-flex gap-2">
                  <button class="btn btn-primary rounded-3" type="submit">
                    <i class="bi bi-save me-2"></i><?= $edit ? 'Update' : 'Create' ?>
                  </button>
                  <?php if ($edit): ?><a class="btn btn-outline-dark rounded-3" href="courses.php">Cancel</a><?php endif; ?>
                </div>
              </form>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="glass rounded-4 p-4 border">
              <h5 class="fw-semibold mb-3">All Courses</h5>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Duration</th>
                      <th>Created</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($courses as $c): ?>
                      <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><?= htmlspecialchars($c['course_name']) ?></td>
                        <td><?= (int)$c['duration'] ?> yrs</td>
                        <td class="small text-muted"><?= htmlspecialchars((string)$c['created_at']) ?></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-primary" href="courses.php?edit=<?= (int)$c['id'] ?>"><i class="bi bi-pencil"></i></a>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this course?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$courses): ?><tr><td colspan="5" class="text-center text-muted">No courses yet.</td></tr><?php endif; ?>
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
