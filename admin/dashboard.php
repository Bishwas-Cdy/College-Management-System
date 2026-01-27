<?php
// admin/dashboard.php

require_once __DIR__ . '/../auth/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Admin Dashboard';
$active = 'dashboard';

// Fetch statistics
$total_students = 0;
$total_faculty = 0;
$total_courses = 0;
$unpaid_invoices = 0;

// Count students
$res = $conn->query("SELECT COUNT(*) as count FROM students");
if ($res) {
  $row = $res->fetch_assoc();
  $total_students = (int)($row['count'] ?? 0);
}

// Count faculty
$res = $conn->query("SELECT COUNT(*) as count FROM faculty");
if ($res) {
  $row = $res->fetch_assoc();
  $total_faculty = (int)($row['count'] ?? 0);
}

// Count courses
$res = $conn->query("SELECT COUNT(*) as count FROM courses");
if ($res) {
  $row = $res->fetch_assoc();
  $total_courses = (int)($row['count'] ?? 0);
}

// Count unpaid invoices
$res = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'unpaid'");
if ($res) {
  $row = $res->fetch_assoc();
  $unpaid_invoices = (int)($row['count'] ?? 0);
}

// Fetch recent notifications/activity
$recent_activity = [];
try {
  $stmt = $conn->prepare("
    SELECT message, created_at
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 5
  ");
  if ($stmt) {
    $stmt->execute();
    $recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
    $stmt->close();
  }
} catch (Throwable $e) {
  // Notifications table doesn't exist or has different schema
  $recent_activity = [];
}

include(__DIR__ . '/../partials/header.php');
include(__DIR__ . '/../partials/app_navbar.php');
?>

<main class="py-4">
  <div class="container">
    <div class="row g-3">

      <div class="col-lg-3">
        <?php include(__DIR__ . '/../partials/app_sidebar.php'); ?>
      </div>

      <div class="col-lg-9">
        <div class="glass rounded-4 p-4 border mb-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <h3 class="fw-bold mb-1">Admin Dashboard</h3>
              <!-- <p class="small-muted mb-0">Manage users, academics, attendance, exams, and fees.</p> -->
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-primary rounded-3" href="students.php">
                <i class="bi bi-person-plus me-2"></i>Add Student
              </a>
              <a class="btn btn-outline-dark rounded-3" href="faculty.php">
                <i class="bi bi-person-workspace me-2"></i>Add Faculty
              </a>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-mortarboard fs-4 text-primary"></i>
                  <span class="fw-semibold">Students</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $total_students ?></div>
                <div class="small-muted">Total students</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-person-workspace fs-4 text-success"></i>
                  <span class="fw-semibold">Faculty</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $total_faculty ?></div>
                <div class="small-muted">Total faculty</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-journal-bookmark fs-4 text-dark"></i>
                  <span class="fw-semibold">Courses</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $total_courses ?></div>
                <div class="small-muted">Total courses</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 rounded-4 card-hover h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-cash-coin fs-4 text-warning"></i>
                  <span class="fw-semibold">Pending Fees</span>
                </div>
                <div class="display-6 fw-bold mb-0"><?= $unpaid_invoices ?></div>
                <div class="small-muted">Unpaid invoices</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-7">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><i class="bi bi-bell me-2"></i>Recent Activity</h5>
              <?php if (!empty($recent_activity)): ?>
                <div class="small">
                  <ul class="list-unstyled">
                    <?php foreach ($recent_activity as $activity): ?>
                      <li class="mb-3 pb-3 border-bottom">
                        <div class="small-muted"><?= htmlspecialchars($activity['message'] ?? '') ?></div>
                        <div class="small-muted" style="font-size: 0.75rem;">
                          <?php 
                            $date = new DateTime($activity['created_at'] ?? 'now');
                            echo $date->format('M d, Y H:i');
                          ?>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php else: ?>
                <div class="small-muted text-center py-4">
                  <p class="mb-0">No recent activity yet.</p>
                  <p class="small mb-0">Activities will appear here as you use the system.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="glass rounded-4 p-4 border h-100">
              <h5 class="fw-semibold mb-3"><i class="bi bi-lightning-charge me-2"></i>Quick Links</h5>
              <div class="d-grid gap-2">
                <a class="btn btn-outline-dark rounded-3" href="attendance_report.php"><i class="bi bi-calendar-check me-2"></i>Attendance Reports</a>
                <a class="btn btn-outline-dark rounded-3" href="exams.php"><i class="bi bi-award me-2"></i>Exams and Results</a>
                <a class="btn btn-outline-dark rounded-3" href="fees.php"><i class="bi bi-cash-stack me-2"></i>Fees and Invoices</a>
                <a class="btn btn-outline-dark rounded-3" href="settings.php"><i class="bi bi-gear me-2"></i>System Settings</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include(__DIR__ . '/../partials/footer.php'); ?>
