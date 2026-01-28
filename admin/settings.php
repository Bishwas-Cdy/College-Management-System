<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../partials/flash.php';

$pageTitle = 'System Settings';
$active = 'settings';

$success = flash_get('success');
$error = flash_get('error');

// Fetch all settings
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key ASC");
if ($res) {
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
  }
}

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $college_name = trim((string)($_POST['college_name'] ?? ''));
    $academic_year = trim((string)($_POST['academic_year'] ?? ''));
    $default_language = trim((string)($_POST['default_language'] ?? ''));

    if ($college_name === '') {
      flash_set('error', 'College name is required.');
      header('Location: settings.php');
      exit;
    }

    // Update settings
    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    
    $stmt->bind_param('ss', $college_name, $key);
    $key = 'college_name';
    $stmt->execute();
    
    $stmt->bind_param('ss', $academic_year, $key);
    $key = 'academic_year';
    $stmt->execute();
    
    $stmt->bind_param('ss', $default_language, $key);
    $key = 'default_language';
    $stmt->execute();
    
    $stmt->close();

    $userId = (int)($_SESSION['user_id'] ?? 0);
    try {
      @log_audit($conn, $userId, 'update', 'system_settings', 0, "Updated system settings");
    } catch (Throwable $e) {
      // Audit logging failed but update succeeded
    }

    flash_set('success', 'Settings updated successfully.');
    header('Location: settings.php');
    exit;
  } catch (Throwable $e) {
    flash_set('error', 'Error updating settings: ' . $e->getMessage());
    header('Location: settings.php');
    exit;
  }
}

require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../partials/app_navbar.php';
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-3"><?php include __DIR__ . '/../partials/app_sidebar.php'; ?></div>

      <div class="col-lg-9">
        <div class="glass rounded-4 p-4 border mb-3">
          <h3 class="fw-bold mb-1">System Settings</h3>
          <p class="small-muted mb-0">Manage college information and system configuration.</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="glass rounded-4 p-4 border">
              <h5 class="fw-semibold mb-3">Settings</h5>
              <form method="POST">
                <div class="mb-3">
                  <label class="form-label fw-semibold">College Name</label>
                  <input class="form-control" type="text" name="college_name" required value="<?= htmlspecialchars($settings['college_name'] ?? '') ?>">
                  <small class="text-muted">Display name for your institution</small>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Academic Year</label>
                  <input class="form-control" type="text" name="academic_year" placeholder="e.g., 2025-2026" value="<?= htmlspecialchars($settings['academic_year'] ?? '') ?>">
                  <small class="text-muted">Current academic year</small>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Default Language</label>
                  <select class="form-select" name="default_language">
                    <option value="en" <?= ($settings['default_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="es" <?= ($settings['default_language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish</option>
                    <option value="fr" <?= ($settings['default_language'] ?? '') === 'fr' ? 'selected' : '' ?>>French</option>
                  </select>
                  <small class="text-muted">System default language</small>
                </div>

                <div class="d-flex gap-2 mt-4">
                  <button class="btn btn-primary rounded-3" type="submit">
                    <i class="bi bi-save me-2"></i>Save Settings
                  </button>
                  <a class="btn btn-outline-dark rounded-3" href="dashboard.php">Cancel</a>
                </div>
              </form>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3">Information</h5>
              <div class="mb-3">
                <strong>College Name:</strong>
                <p class="text-muted mb-0"><?= htmlspecialchars($settings['college_name'] ?? 'Not set') ?></p>
              </div>
              <div class="mb-3">
                <strong>Academic Year:</strong>
                <p class="text-muted mb-0"><?= htmlspecialchars($settings['academic_year'] ?? 'Not set') ?></p>
              </div>
              <div class="mb-3">
                <strong>Default Language:</strong>
                <p class="text-muted mb-0">
                  <?php
                    $langs = ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French'];
                    $langCode = $settings['default_language'] ?? 'en';
                    echo htmlspecialchars($langs[$langCode] ?? 'Unknown');
                  ?>
                </p>
              </div>
              <hr>
              <p class="small text-muted">These settings control the basic configuration of your CMS instance. Update them as needed to reflect your institution's information.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

