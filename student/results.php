<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_role(['student']);

$active = 'results';
$pageTitle = 'My Results';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

// student profile
$stmt = $conn->prepare("SELECT id, course_id, semester FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$studentId = (int)($student['id'] ?? 0);
$courseId = (int)($student['course_id'] ?? 0);
$semester = (string)($student['semester'] ?? '');

$examId = (int)($_GET['exam_id'] ?? 0);

// Published exams for this course+semester
$exams_list = [];
if ($courseId > 0 && trim($semester) !== '') {
  $stmt = $conn->prepare("
    SELECT id, exam_name, exam_date
    FROM exams
    WHERE course_id = ? AND semester = ? AND is_published = 1
    ORDER BY created_at DESC
  ");
  $stmt->bind_param('is', $courseId, $semester);
  $stmt->execute();
  $exams_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// Marks for selected exam
$marks_rows = [];
if ($studentId > 0 && $examId > 0) {
  $stmt = $conn->prepare("
    SELECT
      s.subject_name,
      m.marks
    FROM marks m
    JOIN subjects s ON s.id = m.subject_id
    WHERE m.exam_id = ? AND m.student_id = ?
    ORDER BY s.subject_name ASC
  ");
  $stmt->bind_param('ii', $examId, $studentId);
  $stmt->execute();
  $marks_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
      <div class="text-muted mb-3">Only published exams are visible.</div>

      <div class="card border-0 rounded-4 shadow-sm mb-3">
        <div class="card-body p-4">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-10">
              <label class="form-label fw-semibold">Select Exam</label>
              <select class="form-select" name="exam_id">
                <option value="0">-- Select --</option>
                <?php foreach ($exams_list as $e): ?>
                  <option value="<?= (int)$e['id'] ?>" <?= $examId === (int)$e['id'] ? 'selected' : '' ?>>
                    <?= h($e['exam_name']) ?> <?= $e['exam_date'] ? ' | ' . h($e['exam_date']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
          <?php if ($examId <= 0): ?>
            <div class="alert alert-info mb-0">Select an exam to view marks.</div>
          <?php else: ?>
            <?php if (!$marks_rows): ?>
              <div class="alert alert-warning mb-0">No marks found (not entered yet).</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Subject</th>
                      <th class="text-end">Marks</th>
                      <th class="text-end">Percentage</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($marks_rows as $r): 
                      $marks = (int)($r['marks'] ?? 0);
                      $maxMarks = 100;
                      $percentage = ($maxMarks > 0) ? round(($marks / $maxMarks) * 100, 2) : 0;
                    ?>
                      <tr>
                        <td><?= h($r['subject_name']) ?></td>
                        <td class="text-end"><?= $marks ?>/<?= $maxMarks ?></td>
                        <td class="text-end"><?= $percentage ?>%</td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
