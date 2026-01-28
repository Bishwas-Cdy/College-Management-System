<?php
// Creates a faculty record AND a login user (role=faculty) with a temporary password.

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../partials/flash.php';

$pageTitle = 'Faculty';
$active = 'faculty';

$success = flash_get('success');
$error = flash_get('error');

function temp_password(int $len = 10): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $out = '';
  for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
  return $out;
}

$edit = null;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($edit_id > 0) {
  $stmt = $conn->prepare("SELECT f.id, f.user_id, f.name, f.email, f.department, f.phone, u.is_active
                          FROM faculty f
                          JOIN users u ON u.id = f.user_id
                          WHERE f.id=? LIMIT 1");
  $stmt->bind_param('i', $edit_id);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'create') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set('error', 'Name and valid email are required.');
      header('Location: faculty.php');
      exit;
    }

    $pass = temp_password();
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare("INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'faculty', 1)");
      $stmt->bind_param('ss', $email, $hash);
      $stmt->execute();
      $user_id = $conn->insert_id;
      $stmt->close();

      $stmt = $conn->prepare("INSERT INTO faculty (user_id, name, email, department, phone) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param('issss', $user_id, $name, $email, $department, $phone);
      $stmt->execute();
      $facultyId = $conn->insert_id;
      $stmt->close();

      $conn->commit();
      flash_set('success', "Faculty created. Temporary password for {$email}: {$pass}");
    } catch (Throwable $e) {
      $conn->rollback();
      flash_set('error', 'Failed to create faculty. Email may already exist.');
    }

    // Log audit (outside main transaction)
    if (isset($facultyId, $name, $email, $department)) {
      $userId = (int)($_SESSION['user_id'] ?? 0);
      try {
        @log_audit($conn, $userId, 'create', 'faculty', $facultyId, "Name: {$name}, Email: {$email}, Dept: {$department}");
      } catch (Throwable $e) {
        // Silently fail if audit table doesn't exist
      }
    }

    header('Location: faculty.php');
    exit;
  }

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($id <= 0 || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set('error', 'Invalid update request.');
      header('Location: faculty.php');
      exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM faculty WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      flash_set('error', 'Faculty not found.');
      header('Location: faculty.php');
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

      $stmt = $conn->prepare("UPDATE faculty SET name=?, email=?, department=?, phone=? WHERE id=?");
      $stmt->bind_param('ssssi', $name, $email, $department, $phone, $id);
      if (!$stmt->execute()) {
        throw new Exception("Failed to update faculty: " . $stmt->error);
      }
      $stmt->close();

      $conn->commit();
      flash_set('success', 'Faculty updated successfully.');
    } catch (Throwable $e) {
      $conn->rollback();
      error_log("Faculty update error: " . $e->getMessage());
      flash_set('error', 'Failed to update faculty: ' . $e->getMessage());
    }

    // Log audit (outside main transaction)
    if (isset($id, $name, $email, $department, $is_active)) {
      $userId = (int)($_SESSION['user_id'] ?? 0);
      try {
        @log_audit($conn, $userId, 'update', 'faculty', $id, "Name: {$name}, Email: {$email}, Dept: {$department}, Status: " . ($is_active ? 'active' : 'inactive'));
      } catch (Throwable $e) {
        // Silently fail if audit table doesn't exist
      }
    }

    header('Location: faculty.php');
    exit;
  }

  if ($action === 'reset_password') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('error', 'Invalid faculty.');
      header('Location: faculty.php');
      exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM faculty WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      flash_set('error', 'Faculty not found.');
      header('Location: faculty.php');
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
    header('Location: faculty.php?edit=' . $id);
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('error', 'Invalid delete request.');
      header('Location: faculty.php');
      exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM faculty WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      flash_set('error', 'Faculty not found.');
      header('Location: faculty.php');
      exit;
    }

    $user_id = (int)$row['user_id'];

    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare("DELETE FROM faculty WHERE id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      // If you already have ON DELETE CASCADE on faculty.user_id, this is still safe (it will just delete 0 rows if already deleted).
      $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $stmt->close();

      $conn->commit();
      flash_set('success', 'Faculty deleted.');
    } catch (Throwable $e) {
      $conn->rollback();
      flash_set('error', 'Cannot delete faculty. It may be linked to timetable/attendance/marks.');
    }

    // Log audit (outside main transaction)
    if (isset($id)) {
      $userId = (int)($_SESSION['user_id'] ?? 0);
      try {
        @log_audit($conn, $userId, 'delete', 'faculty', $id, 'Faculty deleted');
      } catch (Throwable $e) {
        // Silently fail if audit table doesn't exist
      }
    }

    header('Location: faculty.php');
    exit;
  }

  flash_set('error', 'Unknown action.');
  header('Location: faculty.php');
  exit;
}

// Search and pagination
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$faculty_list = [];
$totalCount = 0;
try {
  // Count total (with search)
  $countSql = "SELECT COUNT(*) as cnt FROM faculty f
               LEFT JOIN users u ON u.id = f.user_id
               WHERE 1=1";
  if (!empty($search)) {
    $countSql .= " AND (f.name LIKE ? OR f.email LIKE ? OR f.department LIKE ?)";
    $stmt = $conn->prepare($countSql);
    $searchWild = "%$search%";
    $stmt->bind_param('sss', $searchWild, $searchWild, $searchWild);
  } else {
    $stmt = $conn->prepare($countSql);
  }
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $totalCount = (int)($row['cnt'] ?? 0);
  $stmt->close();

  // Fetch faculty (with search and pagination)
  $sql = "SELECT f.id, f.name, f.email, f.department, f.phone, COALESCE(u.is_active, 0) AS is_active
          FROM faculty f
          LEFT JOIN users u ON u.id = f.user_id
          WHERE 1=1";
  if (!empty($search)) {
    $sql .= " AND (f.name LIKE ? OR f.email LIKE ? OR f.department LIKE ?)";
  }
  $sql .= " ORDER BY f.id DESC LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($sql);
  if (!empty($search)) {
    $searchWild = "%$search%";
    $stmt->bind_param('sssii', $searchWild, $searchWild, $searchWild, $perPage, $offset);
  } else {
    $stmt->bind_param('ii', $perPage, $offset);
  }
  $stmt->execute();
  $faculty_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} catch (Throwable $e) {
  $faculty_list = [];
  $totalCount = 0;
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
          <h3 class="fw-bold mb-1">Faculty</h3>
          <p class="small-muted mb-0">Create and manage faculty accounts (includes login).</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row g-3">
          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><?= $edit ? 'Edit Faculty' : 'Add Faculty' ?></h5>

              <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Name</label>
                  <input class="form-control" name="name" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Email (Login)</label>
                  <input class="form-control" name="email" type="email" required value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Department</label>
                  <input class="form-control" name="department" value="<?= htmlspecialchars($edit['department'] ?? '') ?>" autocomplete="organization">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Phone</label>
                  <input class="form-control" name="phone" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>">
                </div>

                <?php if ($edit): ?>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= ((int)($edit['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Account Active</label>
                  </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                  <button class="btn btn-primary btn-lg rounded-3" type="submit">
                    <i class="bi bi-save me-2"></i><?= $edit ? 'Update Faculty' : 'Create Faculty' ?>
                  </button>
                  <?php if ($edit): ?>
                    <a class="btn btn-outline-dark rounded-3" href="faculty.php">Cancel</a>
                  <?php endif; ?>
                </div>
              </form>

              <?php if ($edit): ?>
                <form method="POST" class="mt-3" onsubmit="return confirm('Reset password for this faculty? They will receive a new temporary password.');">
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
              <h5 class="fw-semibold mb-3">All Faculty (Total: <?= $totalCount ?>)</h5>

              <!-- Search Box -->
              <div class="mb-3">
                <form method="GET" class="d-flex gap-2">
                  <input type="text" name="q" class="form-control" placeholder="Search by name, email, or department..." value="<?= htmlspecialchars($search) ?>">
                  <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
                  <?php if (!empty($search)): ?>
                    <a href="faculty.php" class="btn btn-outline-secondary">Clear</a>
                  <?php endif; ?>
                </form>
              </div>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Department</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($faculty_list as $f): ?>
                      <tr>
                        <td><?= (int)($f['id'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($f['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($f['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($f['department'] ?? '') ?></td>
                        <td>
                          <?= ((int)($f['is_active'] ?? 0) === 1) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Disabled</span>' ?>
                        </td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-primary" href="faculty.php?edit=<?= (int)($f['id'] ?? 0) ?>"><i class="bi bi-pencil"></i></a>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this faculty?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)($f['id'] ?? 0) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$faculty_list): ?><tr><td colspan="6" class="text-center text-muted">No faculty yet.</td></tr><?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="Page navigation">
                  <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                      <a class="page-link" href="faculty.php?q=<?= urlencode($search) ?>&page=1">First</a>
                    </li>
                    <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                      <a class="page-link" href="faculty.php?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="faculty.php?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
                      <a class="page-link" href="faculty.php?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next</a>
                    </li>
                    <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
                      <a class="page-link" href="faculty.php?q=<?= urlencode($search) ?>&page=<?= $totalPages ?>">Last</a>
                    </li>
                  </ul>
                </nav>
              <?php endif; ?>
            </div>            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
