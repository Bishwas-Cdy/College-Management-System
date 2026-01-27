<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$active = 'fee_report';
$pageTitle = 'Fee Reports';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetch_all(MYSQLI_ASSOC);

$courseId = (int)($_GET['course_id'] ?? 0);
$status = trim((string)($_GET['status'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$where = [];
$params = [];
$types = '';

$sql = "
  SELECT
    i.invoice_no,
    i.amount_due,
    i.status,
    i.created_at,
    s.name AS student_name,
    s.roll_number,
    c.course_name,
    s.semester
  FROM invoices i
  JOIN students s ON s.id = i.student_id
  LEFT JOIN courses c ON c.id = s.course_id
";

if ($courseId > 0) { $where[] = "s.course_id = ?"; $types .= 'i'; $params[] = $courseId; }
if ($status !== '') { $where[] = "i.status = ?"; $types .= 's'; $params[] = $status; }
if ($dateFrom !== '') { $where[] = "DATE(i.created_at) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = "DATE(i.created_at) <= ?"; $types .= 's'; $params[] = $dateTo; }

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY i.created_at DESC";

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

// totals
$total = 0.0;
$paid = 0.0;
$unpaid = 0.0;
foreach ($rows as $r) {
  $amt = (float)$r['amount_due'];
  $total += $amt;
  if ($r['status'] === 'paid') $paid += $amt;
  else $unpaid += $amt;
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
      <div class="text-muted mb-3">Filter invoices and view totals.</div>

      <div class="card border-0 rounded-4 shadow-sm mb-3">
        <div class="card-body p-4">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
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
              <label class="form-label small">Status</label>
              <select class="form-select" name="status">
                <option value="">All</option>
                <option value="unpaid" <?= $status==='unpaid'?'selected':'' ?>>Unpaid</option>
                <option value="paid" <?= $status==='paid'?'selected':'' ?>>Paid</option>
                <option value="overdue" <?= $status==='overdue'?'selected':'' ?>>Overdue</option>
                <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small">From</label>
              <input type="date" class="form-control" name="date_from" value="<?= h($dateFrom) ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label small">To</label>
              <input type="date" class="form-control" name="date_to" value="<?= h($dateTo) ?>">
            </div>

            <div class="col-12 d-grid mt-2">
              <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel me-2"></i>Apply</button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-3">
              <div class="text-muted small">Total</div>
              <div class="fs-5 fw-bold"><?= h((string)$total) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-3">
              <div class="text-muted small">Paid</div>
              <div class="fs-5 fw-bold"><?= h((string)$paid) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-3">
              <div class="text-muted small">Unpaid</div>
              <div class="fs-5 fw-bold"><?= h((string)$unpaid) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
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
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="7" class="text-center text-muted">No records.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td><?= h($r['invoice_no']) ?></td>
                      <td><?= h($r['student_name']) ?> (<?= h($r['roll_number']) ?>)</td>
                      <td><?= h((string)$r['course_name']) ?></td>
                      <td><?= h((string)$r['semester']) ?></td>
                      <td class="text-end"><?= h((string)$r['amount_due']) ?></td>
                      <td><span class="badge <?= $r['status']==='paid'?'bg-success':'bg-secondary' ?>"><?= h($r['status']) ?></span></td>
                      <td><?= h($r['created_at']) ?></td>
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
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
