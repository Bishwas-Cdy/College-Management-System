<?php
// partials/app_navbar.php
// Shared top navbar for all dashboards.
// Usage: $pageTitle = "Dashboard"; include(__DIR__ . '/app_navbar.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Get system settings (college name)
$collegeName = 'CMS';
if (!isset($conn)) {
  $conn = null;
  if (file_exists(__DIR__ . '/../config/db.php')) {
    try {
      require_once __DIR__ . '/../config/db.php';
    } catch (Throwable $e) {
      $conn = null;
    }
  }
}
if ($conn) {
  $settings = get_system_settings($conn);
  $collegeName = $settings['college_name'] ?? 'CMS';
}

$role = $_SESSION['role'] ?? 'guest';
$email = $_SESSION['email'] ?? '';
$name = $_SESSION['name'] ?? '';
$userLabel = $name !== '' ? $name : ($email !== '' ? $email : 'User');

$roleBadgeClass = 'text-bg-secondary';
if ($role === 'admin')   $roleBadgeClass = 'text-bg-primary';
if ($role === 'faculty') $roleBadgeClass = 'text-bg-success';
if ($role === 'student') $roleBadgeClass = 'text-bg-info';

$roleLabel = ucfirst((string)$role);
$pageTitle = $pageTitle ?? 'Dashboard';
?>

<nav class="navbar navbar-expand-lg sticky-top bg-white border-bottom">
  <div class="container">
    <div class="w-100 px-3 py-2 d-flex align-items-center justify-content-between">
      <a class="navbar-brand fw-bold text-primary m-0 d-flex align-items-center gap-2" href="../index.php">
        <i class="bi bi-grid-fill"></i>
        <span><?= htmlspecialchars($collegeName) ?></span>
      </a>

      <div class="d-flex align-items-center gap-2">
        <span class="badge <?= $roleBadgeClass ?> rounded-pill px-3 py-2">
          <i class="bi bi-person-badge me-2"></i><?= htmlspecialchars($roleLabel) ?>
        </span>

        <div class="dropdown">
          <button class="btn btn-light border rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($userLabel) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <span class="dropdown-item-text small text-muted">
                Signed in as<br><strong><?= htmlspecialchars($email) ?></strong>
              </span>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item text-danger" href="../auth/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav>
