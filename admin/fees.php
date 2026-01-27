<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'fees';
$pageTitle = 'Fee Structures';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'save') {
      $courseId = (int)($_POST['course_id'] ?? 0);
      $semester = trim((string)($_POST['semester'] ?? ''));
      $amount = trim((string)($_POST['amount'] ?? ''));

      if ($courseId <= 0 || $semester === '' || $amount === '' || !is_numeric($amount)) {
        flash_set('error', 'Course, semester and numeric amount are required.');
        header('Location: fees.php');
        exit;
      }

      if (!preg_match('/^[A-Z]\d+$/', $semester)) {
        flash_set('error', 'Semester must be format like A1, B2, S5 (letter + number).');
        header('Location: fees.php');
        exit;
      }

      $amt = (float)$amount;

      // Upsert (unique course_id, semester)
      $stmt = $conn->prepare("
        INSERT INTO fees (course_id, semester, amount)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount)
      ");
      $stmt->bind_param('isd', $courseId, $semester, $amt);
      $stmt->execute();
      $feeId = $conn->insert_id ?: $courseId; // For audit, use either new ID or course reference
      $stmt->close();

      // Log audit
      $userId = (int)($_SESSION['user_id'] ?? 0);
      @log_audit($conn, $userId, 'create_or_update', 'fees', $feeId, "Course: {$courseId}, Semester: {$semester}, Amount: {$amt}");

      flash_set('success', 'Fee structure saved.');
      header('Location: fees.php');
      exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $stmt = $conn->prepare("DELETE FROM fees WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      // Log audit
      $userId = (int)($_SESSION['user_id'] ?? 0);
      @log_audit($conn, $userId, 'delete', 'fees', $id, 'Fee structure deleted');

      flash_set('success', 'Fee structure deleted.');
      header('Location: fees.php');
      exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: fees.php');
    exit;

  } catch (Throwable $e) {
    flash_set('error', 'Action failed: ' . $e->getMessage());
    header('Location: fees.php');
    exit;
  }
}

$fees_list = $conn->query("
  SELECT f.*, c.course_name
  FROM fees f
  JOIN courses c ON c.id = f.course_id
  ORDER BY c.course_name ASC, f.semester ASC
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
      <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
      <div class="text-muted mb-3">Set fee amount per course and semester.</div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Add / Update</h5>

              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="save">

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
                  <label class="form-label fw-semibold">Amount *</label>
                  <input class="form-control" name="amount" placeholder="e.g. 45000" inputmode="decimal" required>
                </div>

                <div class="col-12 d-grid mt-2">
                  <button class="btn btn-primary" type="submit">
                    <i class="bi bi-save me-2"></i>Save Fee
                  </button>
                </div>
              </form>

            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Fee Structures</h5>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Course</th>
                      <th>Sem</th>
                      <th class="text-end">Amount</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$fees_list): ?>
                      <tr><td colspan="4" class="text-center text-muted">No fee structures yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($fees_list as $f): ?>
                        <tr>
                          <td><?= h($f['course_name']) ?></td>
                          <td><?= h($f['semester']) ?></td>
                          <td class="text-end"><?= h((string)$f['amount']) ?></td>
                          <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete fee structure?');">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
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
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
