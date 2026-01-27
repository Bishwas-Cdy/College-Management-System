<?php
// Creates a student record AND a login user (role=student) with a temporary password.

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../partials/flash.php';

$pageTitle = 'Students';
$active = 'students';

$success = flash_get('success');
$error = flash_get('error');

function temp_password(int $len = 10): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $out = '';
  for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
  return $out;
}

$courses = [];
$res = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");
if ($res) $courses = $res->fetch_all(MYSQLI_ASSOC);

$edit = null;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($edit_id > 0) {
  $stmt = $conn->prepare("SELECT s.id, s.user_id, s.name, s.roll_number, s.course_id, s.semester, s.email, s.phone, u.is_active
                          FROM students s
                          JOIN users u ON u.id = s.user_id
                          WHERE s.id=? LIMIT 1");
  $stmt->bind_param('i', $edit_id);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'create') {
    $name = trim((string)($_POST['name'] ?? ''));
    $roll = trim((string)($_POST['roll_number'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $semester = trim((string)($_POST['semester'] ?? ''));

    // SERVER-SIDE VALIDATION
    $errors = [];
    if (empty($name) || strlen($name) > 100) $errors[] = 'Name: required, max 100 chars.';
    if (empty($roll) || strlen($roll) > 50) $errors[] = 'Roll number: required, max 50 chars.';
    if (empty($email) || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email: required, valid, max 100 chars.';
    if (strlen($phone) > 20) $errors[] = 'Phone: max 20 chars.';
    if ($course_id <= 0) $errors[] = 'Course: required.';
    if (empty($semester) || strlen($semester) > 10) $errors[] = 'Semester: required, max 10 chars.';

    if (!empty($errors)) {
      flash_set('error', implode(' | ', $errors));
      header('Location: students.php');
      exit;
    }

    $pass = temp_password();
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare("INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'student', 1)");
      $stmt->bind_param('ss', $email, $hash);
      $stmt->execute();
      $user_id = $conn->insert_id;
      $stmt->close();

      $stmt = $conn->prepare("INSERT INTO students (user_id, name, roll_number, course_id, semester, email, phone)
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param('ississs', $user_id, $name, $roll, $course_id, $semester, $email, $phone);
      if (!$stmt->execute()) {
        throw new Exception("INSERT failed: " . $stmt->error);
      }
      $studentId = $conn->insert_id;
      $stmt->close();

      $conn->commit();
      flash_set('success', "Student created. Temporary password for {$email}: {$pass}");
    } catch (Throwable $e) {
      $conn->rollback();
      flash_set('error', 'Failed to create student. Email/roll may already exist.');
    }

    // Log audit (outside main transaction)
    if (isset($studentId, $roll, $email)) {
      $userId = (int)($_SESSION['user_id'] ?? 0);
      try {
        @log_audit($conn, $userId, 'create', 'students', $studentId, "Roll: {$roll}, Email: {$email}");
      } catch (Throwable $e) {
        // Silently fail if audit table doesn't exist
      }
    }

    header('Location: students.php');
    exit;
  }

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $roll = trim((string)($_POST['roll_number'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $semester = trim((string)($_POST['semester'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // SERVER-SIDE VALIDATION
    $errors = [];
    if ($id <= 0) $errors[] = 'Invalid student.';
    if (empty($name) || strlen($name) > 100) $errors[] = 'Name: required, max 100 chars.';
    if (empty($roll) || strlen($roll) > 50) $errors[] = 'Roll number: required, max 50 chars.';
    if (empty($email) || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email: required, valid, max 100 chars.';
    if (strlen($phone) > 20) $errors[] = 'Phone: max 20 chars.';
    if ($course_id <= 0) $errors[] = 'Course: required.';
    if (empty($semester) || strlen($semester) > 10) $errors[] = 'Semester: required, max 10 chars.';

    if (!empty($errors)) {
      flash_set('error', implode(' | ', $errors));
      header('Location: students.php');
      exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM students WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      flash_set('error', 'Student not found.');
      header('Location: students.php');
      exit;
    }

    $user_id = (int)$row['user_id'];

    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare("UPDATE users SET email=?, is_active=? WHERE id=?");
      $stmt->bind_param('sii', $email, $is_active, $user_id);
      if (!$stmt->execute()) {
        throw new Exception("Failed to update user email: " . $stmt->error);
      }
      $stmt->close();

      $stmt = $conn->prepare("UPDATE students SET name=?, roll_number=?, course_id=?, semester=?, email=?, phone=? WHERE id=?");
      $stmt->bind_param('ssisssi', $name, $roll, $course_id, $semester, $email, $phone, $id);
      if (!$stmt->execute()) {
        throw new Exception("Failed to update student: " . $stmt->error);
      }
      $stmt->close();

      $conn->commit();
      flash_set('success', 'Student updated successfully.');
    } catch (Throwable $e) {
      $conn->rollback();
      error_log("Student update error: " . $e->getMessage());
      flash_set('error', 'Failed to update student: ' . $e->getMessage());
    }

    // Log audit (outside main transaction to not interfere with update)
    if (isset($id, $name, $roll, $email, $is_active)) {
      $userId = (int)($_SESSION['user_id'] ?? 0);
      try {
        @log_audit($conn, $userId, 'update', 'students', $id, "Roll: {$roll}, Email: {$email}, Status: " . ($is_active ? 'active' : 'inactive'));
      } catch (Throwable $e) {
        // Silently fail if audit table doesn't exist
      }
    }

    header('Location: students.php');
    exit;
  }

  if ($action === 'reset_password') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('error', 'Invalid student.');
      header('Location: students.php');
      exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      flash_set('error', 'Student not found.');
      header('Location: students.php');
      exit;
    }

    $user_id = (int)$row['user_id'];
    $tempPass = temp_password();
    $hash = password_hash($tempPass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ?, session_token = UUID() WHERE id = ?");
    $stmt->bind_param('si', $hash, $user_id);
    $stmt->execute();
    $stmt->close();

    flash_set('success', "Password reset. New temporary password: {$tempPass}");
    header('Location: students.php?edit=' . $id);
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('error', 'Invalid delete request.');
      header('Location: students.php');
      exit;
    }

    $stmt = $conn->prepare("SELECT user_id, photo FROM students WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      flash_set('error', 'Student not found.');
      header('Location: students.php');
      exit;
    }

    $user_id = (int)$row['user_id'];
    $photo = (string)($row['photo'] ?? '');

    $conn->begin_transaction();
    try {
      // Delete photo file if exists
      if (!empty($photo)) {
        $photoPath = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . $photo;
        if ($photoPath && is_file($photoPath)) {
          @unlink($photoPath);
        }
      }

      $stmt = $conn->prepare("DELETE FROM students WHERE id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      // If you already have ON DELETE CASCADE on students.user_id, this is still safe.
      $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $stmt->close();

      $conn->commit();
      flash_set('success', 'Student deleted.');
    } catch (Throwable $e) {
      $conn->rollback();
      flash_set('error', 'Cannot delete student. It may be linked to attendance/marks/invoices.');
    }

    // Log audit (outside main transaction)
    if (isset($id)) {
      $userId = (int)($_SESSION['user_id'] ?? 0);
      try {
        @log_audit($conn, $userId, 'delete', 'students', $id, 'Student deleted');
      } catch (Throwable $e) {
        // Silently fail if audit table doesn't exist
      }
    }

    header('Location: students.php');
    exit;
  }

  flash_set('error', 'Unknown action.');
  header('Location: students.php');
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Search and pagination
$search = trim((string)($_GET['q'] ?? ''));
$courseFilter = (int)($_GET['course_id'] ?? 0);
$showInactive = (int)($_GET['show_inactive'] ?? 0); // 0 = active only, 1 = show all
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$student_list = [];
$totalCount = 0;
$debug_error = '';
try {
  // Count total (with search, course filter, and active status)
  $countSql = "SELECT COUNT(*) as cnt FROM students s
               LEFT JOIN courses c ON c.id = s.course_id
               LEFT JOIN users u ON u.id = s.user_id
               WHERE 1=1";
  if (!$showInactive) {
    $countSql .= " AND COALESCE(u.is_active, 0) = 1";
  }
  if (!empty($search)) {
    $countSql .= " AND (s.name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ?)";
  }
  if ($courseFilter > 0) {
    $countSql .= " AND s.course_id = ?";
  }
  $stmt = $conn->prepare($countSql);
  $paramTypes = '';
  $paramVals = [];
  if (!empty($search)) {
    $searchWild = "%$search%";
    $paramVals[] = $searchWild;
    $paramVals[] = $searchWild;
    $paramVals[] = $searchWild;
    $paramTypes .= 'sss';
  }
  if ($courseFilter > 0) {
    $paramVals[] = $courseFilter;
    $paramTypes .= 'i';
  }
  if ($paramTypes) {
    $stmt->bind_param($paramTypes, ...$paramVals);
  }
  $stmt->execute();
  $totalCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
  $stmt->close();

  // Fetch students (with search, course filter, active status, and pagination)
  $sql = "SELECT s.id, s.name, s.roll_number, s.semester, s.email, 
                 COALESCE(c.course_name, '') AS course_name, 
                 COALESCE(u.is_active, 0) AS is_active
          FROM students s
          LEFT JOIN courses c ON c.id = s.course_id
          LEFT JOIN users u ON u.id = s.user_id
          WHERE 1=1";
  if (!$showInactive) {
    $sql .= " AND COALESCE(u.is_active, 0) = 1";
  }
  if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ?)";
  }
  if ($courseFilter > 0) {
    $sql .= " AND s.course_id = ?";
  }
  $sql .= " ORDER BY s.id DESC LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($sql);
  $paramTypes = '';
  $paramVals = [];
  if (!empty($search)) {
    $searchWild = "%$search%";
    $paramVals[] = $searchWild;
    $paramVals[] = $searchWild;
    $paramVals[] = $searchWild;
    $paramTypes .= 'sss';
  }
  if ($courseFilter > 0) {
    $paramVals[] = $courseFilter;
    $paramTypes .= 'i';
  }
  $paramVals[] = $perPage;
  $paramVals[] = $offset;
  $paramTypes .= 'ii';
  $stmt->bind_param($paramTypes, ...$paramVals);
  $stmt->execute();
  $student_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
  $stmt->close();
} catch (Throwable $e) {
  $student_list = [];
  $debug_error = 'Student list query failed: ' . $e->getMessage();
}

$totalPages = ceil($totalCount / $perPage);
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
        <div class="glass rounded-4 p-4 border mb-3">
          <h3 class="fw-bold mb-1">Students</h3>
          <p class="small-muted mb-0">Create and manage student accounts.</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row g-3">
          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><?= $edit ? 'Edit Student' : 'Add Student' ?></h5>

              <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Name</label>
                  <input class="form-control" name="name" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Roll Number</label>
                  <input class="form-control" name="roll_number" required value="<?= htmlspecialchars($edit['roll_number'] ?? '') ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Email (Login)</label>
                  <input class="form-control" name="email" type="email" required value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Phone</label>
                  <input class="form-control" name="phone" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>" autocomplete="tel">
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
                  <input class="form-control" type="text" name="semester" placeholder="e.g., A1, B2" required value="<?= htmlspecialchars((string)($edit['semester'] ?? '')) ?>">
                </div>

                <?php if ($edit): ?>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= ((int)($edit['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Account Active</label>
                  </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                  <button class="btn btn-primary btn-lg rounded-3" type="submit">
                    <i class="bi bi-save me-2"></i><?= $edit ? 'Update Student' : 'Create Student' ?>
                  </button>
                  <?php if ($edit): ?>
                    <a class="btn btn-outline-dark rounded-3" href="students.php">Cancel</a>
                  <?php endif; ?>
                </div>
              </form>

              <?php if ($edit): ?>
                <form method="POST" class="mt-3" onsubmit="return confirm('Reset password for this student? They will receive a new temporary password.');">
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
                  <button class="btn btn-warning rounded-3 w-100" type="submit">
                    <i class="bi bi-key-fill me-2"></i>Reset Password
                  </button>
                </form>
              <?php endif; ?>

              <?php if (!$edit): ?>
                <div class="small text-muted mt-3">On create, a temporary password is generated and shown once.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="glass rounded-4 p-4 border">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-semibold mb-0">All Students</h5>
                <span class="badge bg-secondary"><?= $totalCount ?> total</span>
              </div>

              <!-- Search Box -->
              <form method="GET" class="mb-3">
                <div class="row g-2">
                  <div class="col-md-5">
                    <input class="form-control" type="text" name="q" placeholder="Search name, roll, email..." value="<?= htmlspecialchars($search) ?>">
                  </div>
                  <div class="col-md-3">
                    <select class="form-select" name="course_id" onchange="this.form.submit()">
                      <option value="0">All Courses</option>
                      <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($c['course_name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <div class="form-check mt-2">
                      <input class="form-check-input" type="checkbox" name="show_inactive" id="showInactive" value="1" <?= $showInactive ? 'checked' : '' ?> onchange="this.form.submit()">
                      <label class="form-check-label small" for="showInactive">Show Disabled</label>
                    </div>
                  </div>
                  <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    <?php if (!empty($search) || $courseFilter > 0 || $showInactive): ?>
                      <a class="btn btn-outline-secondary btn-sm" href="students.php">Clear</a>
                    <?php endif; ?>
                  </div>
                </div>
              </form>              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Roll</th>
                      <th>Course</th>
                      <th>Sem</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$student_list): ?>
                      <tr><td colspan="7" class="text-center text-muted">No students yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($student_list as $s): ?>
                        <tr>
                          <td><?= (int)($s['id'] ?? 0) ?></td>
                          <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
                          <td><?= htmlspecialchars($s['roll_number'] ?? '') ?></td>
                          <td><?= htmlspecialchars($s['course_name'] ?? '') ?></td>
                          <td><?= htmlspecialchars($s['semester'] ?? '') ?></td>
                          <td>
                            <?= ((int)($s['is_active'] ?? 0) === 1) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Disabled</span>' ?>
                          </td>
                          <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="students.php?edit=<?= (int)($s['id'] ?? 0) ?>"><i class="bi bi-pencil"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student?');">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int)($s['id'] ?? 0) ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                  <ul class="pagination pagination-sm">
                    <?php if ($page > 1): ?>
                      <li class="page-item"><a class="page-link" href="?q=<?= urlencode($search) ?>&course_id=<?= $courseFilter ?>&show_inactive=<?= $showInactive ?>&page=1">First</a></li>
                      <li class="page-item"><a class="page-link" href="?q=<?= urlencode($search) ?>&course_id=<?= $courseFilter ?>&show_inactive=<?= $showInactive ?>&page=<?= $page - 1 ?>">Prev</a></li>
                    <?php endif; ?>
                    
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                      <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?q=<?= urlencode($search) ?>&course_id=<?= $courseFilter ?>&show_inactive=<?= $showInactive ?>&page=<?= $p ?>"><?= $p ?></a>
                      </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                      <li class="page-item"><a class="page-link" href="?q=<?= urlencode($search) ?>&course_id=<?= $courseFilter ?>&show_inactive=<?= $showInactive ?>&page=<?= $page + 1 ?>">Next</a></li>
                      <li class="page-item"><a class="page-link" href="?q=<?= urlencode($search) ?>&course_id=<?= $courseFilter ?>&show_inactive=<?= $showInactive ?>&page=<?= $totalPages ?>">Last</a></li>
                    <?php endif; ?>
                  </ul>
                </nav>
              <?php endif; ?>

            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
