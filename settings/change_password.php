<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';

$pageTitle = 'Change Password';
$active = 'change_password';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $oldPassword = (string)($_POST['old_password'] ?? '');
  $newPassword = (string)($_POST['new_password'] ?? '');
  $confirmPassword = (string)($_POST['confirm_password'] ?? '');

  // Validation
  if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
    $errorMsg = 'All fields are required.';
  } elseif (strlen($newPassword) < 8) {
    $errorMsg = 'New password must be at least 8 characters.';
  } elseif ($newPassword !== $confirmPassword) {
    $errorMsg = 'Passwords do not match.';
  } else {
    // Verify old password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
      $errorMsg = 'User not found.';
    } elseif (!password_verify($oldPassword, $user['password'])) {
      $errorMsg = 'Old password is incorrect.';
    } else {
      // Update password
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
      $stmt->bind_param('si', $hashedPassword, $userId);
      $stmt->execute();
      $stmt->close();

      $successMsg = 'Password changed successfully.';
      $_POST = []; // Clear form
    }
  }
}

$role = $_SESSION['role'] ?? 'guest';

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
        <h3 class="fw-bold mb-1"><?= h($pageTitle) ?></h3>
        <div class="text-muted mb-3">Update your account password.</div>

        <div class="row">
          <div class="col-lg-6">
            <div class="card border-0 rounded-4 shadow-sm">
              <div class="card-body p-4">

                <?php if ($successMsg): ?>
                  <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= h($successMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= h($errorMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
                <?php endif; ?>

                <form method="POST" class="row g-3">

                  <div class="col-12">
                    <label class="form-label fw-semibold">Current Password *</label>
                    <input class="form-control" type="password" name="old_password" required>
                    <div class="form-text">Enter your current password to verify identity.</div>
                  </div>

                  <div class="col-12">
                    <label class="form-label fw-semibold">New Password *</label>
                    <input class="form-control" type="password" name="new_password" required minlength="8">
                    <div class="form-text">Minimum 8 characters. Use a mix of uppercase, lowercase, numbers, and symbols for security.</div>
                  </div>

                  <div class="col-12">
                    <label class="form-label fw-semibold">Confirm New Password *</label>
                    <input class="form-control" type="password" name="confirm_password" required minlength="8">
                    <div class="form-text">Re-enter your new password to confirm.</div>
                  </div>

                  <div class="col-12 d-grid">
                    <button class="btn btn-primary" type="submit">
                      <i class="bi bi-shield-lock me-2"></i>Change Password
                    </button>
                  </div>

                  <div class="col-12">
                    <a href="../dashboard.php" class="btn btn-outline-secondary w-100">Cancel</a>
                  </div>

                </form>

              </div>
            </div>

            <div class="card border-0 rounded-4 shadow-sm mt-3">
              <div class="card-body p-4">
                <h5 class="fw-semibold mb-2">Password Requirements</h5>
                <ul class="small list-unstyled">
                  <li><i class="bi bi-check-circle text-success me-2"></i>At least 8 characters long</li>
                  <li><i class="bi bi-check-circle text-success me-2"></i>Unique and not used before</li>
                  <li><i class="bi bi-check-circle text-success me-2"></i>Mix of uppercase and lowercase</li>
                  <li><i class="bi bi-check-circle text-success me-2"></i>At least one number (0-9)</li>
                  <li><i class="bi bi-check-circle text-success me-2"></i>At least one special character (!@#$%)</li>
                </ul>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
