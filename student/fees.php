<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['student']);

$active = 'fees';
$pageTitle = 'My Fees';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// student id
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$studentRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$studentId = (int)($studentRow['id'] ?? 0);

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $invoiceId = (int)($_POST['invoice_id'] ?? 0);

  if ($invoiceId <= 0) {
    header('Location: fees.php');
    exit;
  }

  try {
    // verify this invoice belongs to student and get details
    $stmt = $conn->prepare("SELECT id, student_id, fee_id FROM invoices WHERE id = ? AND student_id = ? LIMIT 1");
    $stmt->bind_param('ii', $invoiceId, $studentId);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv) {
      header('Location: fees.php');
      exit;
    }

    // insert payment record
    $paymentDate = date('Y-m-d');
    $stmtPay = $conn->prepare("INSERT INTO payments (invoice_id, student_id, fee_id, status, payment_date) VALUES (?, ?, ?, 'paid', ?)");
    $stmtPay->bind_param('iiis', $invoiceId, $inv['student_id'], $inv['fee_id'], $paymentDate);
    $stmtPay->execute();
    $stmtPay->close();

    // update invoice status to paid
    $stmtInv = $conn->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
    $stmtInv->bind_param('i', $invoiceId);
    $stmtInv->execute();
    $stmtInv->close();

    header('Location: fees.php');
    exit;

  } catch (Throwable $e) {
    error_log('Payment error: ' . $e->getMessage());
    header('Location: fees.php');
    exit;
  }
}

$rows = [];
if ($studentId > 0) {
  $stmt = $conn->prepare("
    SELECT i.*, s.semester, c.course_name
    FROM invoices i
    JOIN fees f ON f.id = i.fee_id
    JOIN students s ON s.id = i.student_id
    LEFT JOIN courses c ON c.id = s.course_id
    WHERE i.student_id = ?
    ORDER BY i.created_at DESC
  ");
  $stmt->bind_param('i', $studentId);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
      <div class="text-muted mb-3">Your invoices and payment status.</div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Invoice</th>
                  <th>Course</th>
                  <th>Sem</th>
                  <th class="text-end">Amount</th>
                  <th>Due</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="7" class="text-center text-muted">No invoices yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td><?= h($r['invoice_no']) ?></td>
                      <td><?= h((string)$r['course_name']) ?></td>
                      <td><?= h((string)$r['semester']) ?></td>
                      <td class="text-end"><?= h((string)$r['amount_due']) ?></td>
                      <td><?= h((string)$r['due_date']) ?></td>
                      <td>
                        <span class="badge <?= $r['status']==='paid' ? 'bg-success' : 'bg-secondary' ?>">
                          <?= h($r['status']) ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($r['status'] === 'unpaid'): ?>
                          <form method="POST" style="display: inline;">
                            <input type="hidden" name="invoice_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success">Pay</button>
                          </form>
                        <?php endif; ?>
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
