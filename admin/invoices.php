<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'invoices';
$pageTitle = 'Invoices';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetch_all(MYSQLI_ASSOC);
$students_list = $conn->query("
  SELECT s.id, s.name, s.roll_number, s.semester, c.course_name
  FROM students s
  LEFT JOIN courses c ON c.id = s.course_id
  ORDER BY s.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

function make_invoice_no(mysqli $conn): string {
  $prefix = 'INV-' . date('Ymd') . '-';
  // find count for today to generate next number
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM invoices WHERE invoice_no LIKE CONCAT(?, '%')");
  $stmt->bind_param('s', $prefix);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $n = (int)($row['c'] ?? 0) + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'generate_one') {
      $studentId = (int)($_POST['student_id'] ?? 0);
      $dueDate = trim((string)($_POST['due_date'] ?? ''));

      if ($studentId <= 0) {
        flash_set('error', 'Select a student.');
        header('Location: invoices.php');
        exit;
      }

      // student -> course + semester
      $stmt = $conn->prepare("SELECT course_id, semester FROM students WHERE id = ? LIMIT 1");
      $stmt->bind_param('i', $studentId);
      $stmt->execute();
      $st = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      $courseId = (int)($st['course_id'] ?? 0);
      $semester = (string)($st['semester'] ?? '');

      if ($courseId <= 0 || trim($semester) === '') {
        flash_set('error', 'Student must have course and semester set.');
        header('Location: invoices.php');
        exit;
      }

      // fee_id
      $stmt = $conn->prepare("SELECT id, amount FROM fees WHERE course_id = ? AND semester = ? LIMIT 1");
      $stmt->bind_param('is', $courseId, $semester);
      $stmt->execute();
      $fee = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      $feeId = (int)($fee['id'] ?? 0);
      $amount = (float)($fee['amount'] ?? 0);

      if ($feeId <= 0) {
        flash_set('error', 'No fee structure found for this course+semester.');
        header('Location: invoices.php');
        exit;
      }

      // Check if unpaid invoice already exists for this student
      $stmt = $conn->prepare("SELECT id FROM invoices WHERE student_id = ? AND fee_id = ? AND status = 'unpaid' LIMIT 1");
      $stmt->bind_param('ii', $studentId, $feeId);
      $stmt->execute();
      $existing = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if ($existing) {
        flash_set('error', 'Unpaid invoice already exists for this student. Please collect payment first.');
        header('Location: invoices.php');
        exit;
      }

      $invoiceNo = make_invoice_no($conn);

      $stmt = $conn->prepare("
        INSERT INTO invoices (invoice_no, student_id, fee_id, amount_due, due_date, status)
        VALUES (?, ?, ?, ?, NULLIF(?, ''), 'unpaid')
      ");
      $stmt->bind_param('siids', $invoiceNo, $studentId, $feeId, $amount, $dueDate);
      $stmt->execute();
      $stmt->close();

      // Send notification to student
      $stmtStudent = $conn->prepare("SELECT user_id FROM students WHERE id = ? LIMIT 1");
      $stmtStudent->bind_param('i', $studentId);
      $stmtStudent->execute();
      $studentRow = $stmtStudent->get_result()->fetch_assoc();
      $stmtStudent->close();

      if ($studentRow) {
        $userId = (int)$studentRow['user_id'];
        $amountForMsg = ($amount > 0) ? (int)$amount : '0';
        try {
          @send_notification($conn, $userId, "Invoice {$invoiceNo} for USD {$amountForMsg} has been generated. Due by: " . ($dueDate ?: 'N/A'));
        } catch (Throwable $e) {
          // Silently fail if notifications table doesn't exist
        }
      }

      flash_set('success', 'Invoice generated.');
      header('Location: invoices.php');
      exit;
    }

    if ($action === 'generate_bulk') {
      $courseId = (int)($_POST['course_id'] ?? 0);
      $semester = trim((string)($_POST['semester'] ?? ''));
      $dueDate = trim((string)($_POST['due_date'] ?? ''));

      if ($courseId <= 0 || $semester === '') {
        flash_set('error', 'Course and semester are required.');
        header('Location: invoices.php');
        exit;
      }

      // fee structure
      $stmt = $conn->prepare("SELECT id, amount FROM fees WHERE course_id = ? AND semester = ? LIMIT 1");
      $stmt->bind_param('is', $courseId, $semester);
      $stmt->execute();
      $fee = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      $feeId = (int)($fee['id'] ?? 0);
      $amount = (float)($fee['amount'] ?? 0);

      if ($feeId <= 0) {
        flash_set('error', 'No fee structure found for this course+semester.');
        header('Location: invoices.php');
        exit;
      }

      // students in that course+semester
      $stmt = $conn->prepare("SELECT id FROM students WHERE course_id = ? AND semester = ?");
      $stmt->bind_param('is', $courseId, $semester);
      $stmt->execute();
      $studentIds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      if (!$studentIds) {
        flash_set('error', 'No students found for that course+semester.');
        header('Location: invoices.php');
        exit;
      }

      $conn->begin_transaction();
      try {
        $stmtIns = $conn->prepare("
          INSERT INTO invoices (invoice_no, student_id, fee_id, amount_due, due_date, status)
          VALUES (?, ?, ?, ?, NULLIF(?, ''), 'unpaid')
        ");

        foreach ($studentIds as $row) {
          $studentId = (int)$row['id'];
          $invoiceNo = make_invoice_no($conn);
          $stmtIns->bind_param('siids', $invoiceNo, $studentId, $feeId, $amount, $dueDate);
          $stmtIns->execute();
        }

        $stmtIns->close();
        $conn->commit();

        flash_set('success', 'Bulk invoices generated: ' . count($studentIds));
        header('Location: invoices.php');
        exit;

      } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
      }
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      flash_set('success', 'Invoice deleted.');
      header('Location: invoices.php');
      exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: invoices.php');
    exit;

  } catch (Throwable $e) {
    flash_set('error', 'Action failed: ' . $e->getMessage());
    header('Location: invoices.php');
    exit;
  }
}

// Search and pagination
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// list invoices with pagination
$countSql = "SELECT COUNT(*) as cnt FROM invoices i
             JOIN students s ON s.id = i.student_id
             WHERE 1=1";
if (!empty($search)) {
  $countSql .= " AND (s.name LIKE ? OR s.roll_number LIKE ?)";
  $stmt = $conn->prepare($countSql);
  $searchWild = "%$search%";
  $stmt->bind_param('ss', $searchWild, $searchWild);
} else {
  $stmt = $conn->prepare($countSql);
}
$stmt->execute();
$totalCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$sql = "SELECT i.*, s.name AS student_name, s.roll_number, c.course_name, s.semester, f.amount
        FROM invoices i
        JOIN students s ON s.id = i.student_id
        LEFT JOIN courses c ON c.id = s.course_id
        JOIN fees f ON f.id = i.fee_id
        WHERE 1=1";
if (!empty($search)) {
  $sql .= " AND (s.name LIKE ? OR s.roll_number LIKE ?)";
}
$sql .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
  $searchWild = "%$search%";
  $stmt->bind_param('ssii', $searchWild, $searchWild, $perPage, $offset);
} else {
  $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$invoice_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = ceil($totalCount / $perPage);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
      <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
      <div class="text-muted mb-3">Generate invoices for students.</div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Generate for One Student</h5>
              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="generate_one">

                <div class="col-12">
                  <label class="form-label fw-semibold">Student *</label>
                  <select class="form-select" name="student_id" required>
                    <option value="0">-- Select --</option>
                    <?php foreach ($students_list as $s): ?>
                      <option value="<?= (int)$s['id'] ?>">
                        <?= h($s['name']) ?> (<?= h($s['roll_number']) ?>) | <?= h((string)$s['course_name']) ?> | <?= h((string)$s['semester']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Due date</label>
                  <input type="date" class="form-control" name="due_date">
                </div>

                <div class="col-12 d-grid mt-2">
                  <button class="btn btn-primary" type="submit"><i class="bi bi-receipt me-2"></i>Generate</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Bulk Generate (Course + Semester)</h5>
              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="generate_bulk">

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Course *</label>
                  <select class="form-select" name="course_id" required>
                    <option value="0">-- Select --</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?= (int)$c['id'] ?>"><?= h($c['course_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Semester *</label>
                  <input class="form-control" name="semester" placeholder="A1 / I4" required>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Due date</label>
                  <input type="date" class="form-control" name="due_date">
                </div>

                <div class="col-12 d-grid mt-2">
                  <button class="btn btn-outline-primary" type="submit"><i class="bi bi-collection me-2"></i>Generate Bulk</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 rounded-4 shadow-sm mt-3">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">All Invoices (Total: <?= $totalCount ?>)</h5>

          <!-- Search Box -->
          <div class="mb-3">
            <form method="GET" class="d-flex gap-2">
              <input type="text" name="q" class="form-control" placeholder="Search by student name or roll number..." value="<?= htmlspecialchars($search) ?>">
              <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
              <?php if (!empty($search)): ?>
                <a href="invoices.php" class="btn btn-outline-secondary">Clear</a>
              <?php endif; ?>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Invoice</th>
                  <th>Student</th>
                  <th>Course</th>
                  <th>Sem</th>
                  <th class="text-end">Amount</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$invoice_rows): ?>
                  <tr><td colspan="7" class="text-center text-muted">No invoices yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($invoice_rows as $r): ?>
                    <tr>
                      <td><?= h($r['invoice_no']) ?></td>
                      <td><?= h($r['student_name']) ?> (<?= h($r['roll_number']) ?>)</td>
                      <td><?= h((string)$r['course_name']) ?></td>
                      <td><?= h((string)$r['semester']) ?></td>
                      <td class="text-end"><?= h((string)$r['amount_due']) ?></td>
                      <td>
                        <span class="badge <?= $r['status']==='paid' ? 'bg-success' : 'bg-secondary' ?>">
                          <?= h($r['status']) ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete invoice?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
            <nav class="mt-3" aria-label="Page navigation">
              <ul class="pagination justify-content-center">
                <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="invoices.php?q=<?= urlencode($search) ?>&page=1">First</a>
                </li>
                <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="invoices.php?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Prev</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="invoices.php?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="invoices.php?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next</a>
                </li>
                <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="invoices.php?q=<?= urlencode($search) ?>&page=<?= $totalPages ?>">Last</a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
