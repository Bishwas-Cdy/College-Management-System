<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['student']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../partials/flash.php';

$active = 'messages';
$pageTitle = 'Messages';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// student id
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$st = $stmt->get_result()->fetch_assoc();
$stmt->close();
$studentId = (int)($st['id'] ?? 0);

if ($studentId <= 0) {
  flash_set('error', 'Student profile not found.');
  header('Location: dashboard.php');
  exit;
}

// allowed faculty list (those assigned to subjects student is enrolled in)
$stmt = $conn->prepare("
  SELECT DISTINCT f.user_id, f.name, f.department
  FROM enrollments e
  JOIN faculty_subject fs ON fs.subject_id = e.subject_id
  JOIN faculty f ON f.id = fs.faculty_id
  WHERE e.student_id = ?
  ORDER BY f.name
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$faculty_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
  $toUser = (int)($_POST['to_user_id'] ?? 0);
  $subject = trim((string)($_POST['subject'] ?? ''));
  $body = trim((string)($_POST['body'] ?? ''));

  if ($toUser <= 0 || $body === '') {
    flash_set('error', 'Receiver and message body are required.');
    header('Location: messages.php');
    exit;
  }

  $allowed = false;
  foreach ($faculty_list as $f) {
    if ((int)$f['user_id'] === $toUser) { $allowed = true; break; }
  }
  if (!$allowed) {
    flash_set('error', 'Not allowed to message this faculty.');
    header('Location: messages.php');
    exit;
  }

  $stmt = $conn->prepare("
    INSERT INTO messages (sender_user_id, receiver_user_id, subject, body)
    VALUES (?, ?, NULLIF(?, ''), ?)
  ");
  $stmt->bind_param('iiss', $userId, $toUser, $subject, $body);
  $stmt->execute();
  $stmt->close();

  flash_set('success', 'Message sent.');
  header('Location: messages.php');
  exit;
}

// view + mark read
$viewId = (int)($_GET['view'] ?? 0);
$viewMsg = null;
if ($viewId > 0) {
  $stmt = $conn->prepare("
    SELECT m.*, u.email AS sender_email
    FROM messages m
    JOIN users u ON u.id = m.sender_user_id
    WHERE m.id = ? AND m.receiver_user_id = ?
    LIMIT 1
  ");
  $stmt->bind_param('ii', $viewId, $userId);
  $stmt->execute();
  $viewMsg = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($viewMsg && (int)$viewMsg['is_read'] === 0) {
    $stmt = $conn->prepare("UPDATE messages SET is_read=1, read_at=NOW() WHERE id=? AND receiver_user_id=?");
    $stmt->bind_param('ii', $viewId, $userId);
    $stmt->execute();
    $stmt->close();
    $viewMsg['is_read'] = 1;
  }
}

// inbox list (from faculty)
$stmt = $conn->prepare("
  SELECT m.id, m.subject, m.body, m.is_read, m.created_at,
         f.name AS sender_name, f.department
  FROM messages m
  JOIN faculty f ON f.user_id = m.sender_user_id
  WHERE m.receiver_user_id = ?
  ORDER BY m.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$inbox = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';

// reply
$replyToId = (int)($_GET['reply'] ?? 0);
$replyTo = null;
if ($replyToId > 0) {
  $stmt = $conn->prepare("SELECT id, sender_user_id, subject, body FROM messages WHERE id = ? AND receiver_user_id = ? LIMIT 1");
  $stmt->bind_param('ii', $replyToId, $userId);
  $stmt->execute();
  $replyTo = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
      <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
      <div class="text-muted mb-3">Inbox and sending messages to faculty.</div>

      <?php include __DIR__ . '/../partials/flash_view.php'; ?>

      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Compose</h5>

              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="send">

                <div class="col-12">
                  <label class="form-label fw-semibold">To (Faculty) *</label>
                  <select class="form-select" name="to_user_id" required>
                    <option value="0">-- Select --</option>
                    <?php foreach ($faculty_list as $f): ?>
                      <option value="<?= (int)$f['user_id'] ?>" <?= $replyTo && (int)$replyTo['sender_user_id'] === (int)$f['user_id'] ? 'selected' : '' ?>>
                        <?= h($f['name']) ?><?= $f['department'] ? ' | ' . h($f['department']) : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Subject</label>
                  <input class="form-control" name="subject" placeholder="Optional subject" value="<?= $replyTo ? 'Re: ' . h((string)($replyTo['subject'] ?? '')) : '' ?>">
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Message *</label>
                  <textarea class="form-control" name="body" rows="5" required><?= $replyTo ? "\n\n---\n" . h((string)$replyTo['body']) : '' ?></textarea>
                </div>

                <div class="col-12 d-grid mt-2">
                  <button class="btn btn-primary" type="submit"><i class="bi bi-send me-2"></i>Send</button>
                </div>
              </form>

            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 rounded-4 shadow-sm mb-3">
            <div class="card-body p-4">
              <h5 class="fw-semibold mb-3">Inbox</h5>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>From</th>
                      <th>Subject</th>
                      <th>Time</th>
                      <th class="text-end">Open</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$inbox): ?>
                      <tr><td colspan="4" class="text-center text-muted">No messages.</td></tr>
                    <?php else: ?>
                      <?php foreach ($inbox as $m): ?>
                        <tr class="<?= ((int)$m['is_read'] === 0) ? 'table-warning' : '' ?>">
                          <td><?= h($m['sender_name']) ?><?= $m['department'] ? '<br><span class="text-muted small">'.h($m['department']).'</span>' : '' ?></td>
                          <td><?= h((string)($m['subject'] ?? '')) ?></td>
                          <td class="text-muted small"><?= h($m['created_at']) ?></td>
                          <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="messages.php?view=<?= (int)$m['id'] ?>">
                              <i class="bi bi-eye"></i>
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

          <?php if ($viewMsg): ?>
            <div class="card border-0 rounded-4 shadow-sm">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-semibold">Message</div>
                    <div class="text-muted small"><?= h($viewMsg['created_at']) ?></div>
                  </div>
                  <div class="btn-group" role="group">
                    <a class="btn btn-sm btn-primary" href="messages.php?reply=<?= (int)$viewMsg['id'] ?>"><i class="bi bi-reply me-1"></i>Reply</a>
                    <a class="btn btn-sm btn-outline-secondary" href="messages.php">Close</a>
                  </div>
                </div>
                <hr>
                <div class="fw-semibold mb-1"><?= h((string)($viewMsg['subject'] ?? '(No subject)')) ?></div>
                <div style="white-space: pre-wrap;"><?= h((string)$viewMsg['body']) ?></div>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
