<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

$active = 'messages';
$pageTitle = 'All Messages';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$rows = $conn->query("
  SELECT m.*,
    su.email AS sender_email,
    ru.email AS receiver_email
  FROM messages m
  JOIN users su ON su.id = m.sender_user_id
  JOIN users ru ON ru.id = m.receiver_user_id
  ORDER BY m.created_at DESC
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
      <div class="text-muted mb-3">Monitoring view (read-only).</div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>From</th>
                  <th>To</th>
                  <th>Subject</th>
                  <th>Status</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="5" class="text-center text-muted">No messages.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $m): ?>
                    <tr>
                      <td><?= h($m['sender_email']) ?></td>
                      <td><?= h($m['receiver_email']) ?></td>
                      <td><?= h((string)($m['subject'] ?? '')) ?></td>
                      <td><?= ((int)$m['is_read']===1) ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-secondary">Unread</span>' ?></td>
                      <td class="text-muted small"><?= h($m['created_at']) ?></td>
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
