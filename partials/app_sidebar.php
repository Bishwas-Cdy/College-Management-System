<?php
// partials/app_sidebar.php
// Shared sidebar for all dashboards.
// Usage: $active = 'dashboard'; include(__DIR__ . '/app_sidebar.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$role = $_SESSION['role'] ?? 'guest';
$active = $active ?? 'dashboard';

function isActive(string $key, string $active): string {
  return $key === $active ? 'active' : '';
}

// Menu by role
$menus = [
  'admin' => [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => 'dashboard.php'],
    ['key' => 'students',  'label' => 'Students',  'icon' => 'bi-mortarboard',  'href' => 'students.php'],
    ['key' => 'faculty',   'label' => 'Faculty',   'icon' => 'bi-person-workspace', 'href' => 'faculty.php'],
    ['key' => 'courses',   'label' => 'Courses',   'icon' => 'bi-journal-bookmark', 'href' => 'courses.php'],
    ['key' => 'subjects',  'label' => 'Subjects',  'icon' => 'bi-book',         'href' => 'subjects.php'],
    ['key' => 'faculty_subject', 'label' => 'Assign', 'icon' => 'bi-link-45deg', 'href' => 'faculty_subject.php'],
    ['key' => 'enrollments', 'label' => 'Enrollments', 'icon' => 'bi-people', 'href' => 'enrollments.php'],
    ['key' => 'timetable', 'label' => 'Timetable', 'icon' => 'bi-calendar2-week', 'href' => 'timetable.php'],
    ['key' => 'attendance','label' => 'Attendance Report','icon' => 'bi-calendar-check', 'href' => 'attendance_report.php'],
    ['key' => 'exams',     'label' => 'Exams',     'icon' => 'bi-award',        'href' => 'exams.php'],
    ['key' => 'fees',      'label' => 'Fees',      'icon' => 'bi-cash-coin',    'href' => 'fees.php'],
    ['key' => 'invoices',  'label' => 'Invoices',  'icon' => 'bi-receipt',      'href' => 'invoices.php'],
    ['key' => 'fee_report','label' => 'Fee Report','icon' => 'bi-bar-chart',    'href' => 'fee_report.php'],
    ['key' => 'messages',  'label' => 'Messaging', 'icon' => 'bi-chat-dots',    'href' => 'messages.php'],
    ['key' => 'materials', 'label' => 'Materials', 'icon' => 'bi-folder2-open','href' => 'materials.php'],
    ['key' => 'settings',  'label' => 'Settings',  'icon' => 'bi-gear',         'href' => 'settings.php'],
    ['key' => 'change_password', 'label' => 'Change Password', 'icon' => 'bi-shield-lock', 'href' => '../settings/change_password.php'],
  ],
  'faculty' => [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => 'dashboard.php'],
    ['key' => 'timetable', 'label' => 'Timetable', 'icon' => 'bi-calendar2-week', 'href' => 'timetable.php'],
    ['key' => 'attendance','label' => 'Attendance','icon' => 'bi-calendar-check', 'href' => 'attendance_create.php'],
    ['key' => 'marks',     'label' => 'Enter Marks', 'icon' => 'bi-award',      'href' => 'marks_entry.php'],
    ['key' => 'materials', 'label' => 'Materials', 'icon' => 'bi-folder2-open','href' => 'materials.php'],
    ['key' => 'messages',  'label' => 'Messages',  'icon' => 'bi-chat-dots',    'href' => 'messages.php'],
    ['key' => 'change_password', 'label' => 'Change Password', 'icon' => 'bi-shield-lock', 'href' => '../settings/change_password.php'],
  ],
  'student' => [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => 'dashboard.php'],
    ['key' => 'timetable', 'label' => 'Timetable', 'icon' => 'bi-calendar2-week', 'href' => 'timetable.php'],
    ['key' => 'attendance','label' => 'Attendance','icon' => 'bi-calendar-check', 'href' => 'attendance.php'],
    ['key' => 'results',   'label' => 'Results',   'icon' => 'bi-award',        'href' => 'results.php'],
    ['key' => 'fees',      'label' => 'Invoices',  'icon' => 'bi-receipt',      'href' => 'fees.php'],
    ['key' => 'materials', 'label' => 'Materials', 'icon' => 'bi-folder2-open','href' => 'materials.php'],
    ['key' => 'messages',  'label' => 'Messages',  'icon' => 'bi-chat-dots',    'href' => 'messages.php'],
    ['key' => 'change_password', 'label' => 'Change Password', 'icon' => 'bi-shield-lock', 'href' => '../settings/change_password.php'],
  ],
];

$menuItems = $menus[$role] ?? [
  ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => 'dashboard.php'],
];
?>

<aside class="glass rounded-4 p-3 border h-100">
  <div class="d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-layout-text-sidebar-reverse fs-5 text-primary"></i>
    <div class="fw-semibold">Menu</div>
  </div>

  <div class="list-group list-group-flush">
    <?php foreach ($menuItems as $item): ?>
      <a
        class="list-group-item list-group-item-action rounded-3 mb-2 <?= isActive($item['key'], $active) ?>"
        href="<?= htmlspecialchars($item['href']) ?>"
      >
        <i class="bi <?= htmlspecialchars($item['icon']) ?> me-2"></i>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="mt-3">
    <a class="btn btn-outline-danger w-100 rounded-3" href="../auth/logout.php">
      <i class="bi bi-box-arrow-right me-2"></i>Logout
    </a>
  </div>
</aside>
