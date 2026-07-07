<?php
// /User/academic-submit.php
session_start();
require dirname(__DIR__) . '/Main/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// ---------- AUTH (UNTOUCHED) ----------
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
  header('Location: /User/user-login.php');
  exit;
}
$username  = $_SESSION['user']['username'] ?? ($_SESSION['user']['user_name'] ?? 'user');
$sessionId = $_SESSION['assessment_session_id'] ?? null;
if (!$sessionId) {
  header('Location: /User/test-session.php');
  exit;
}

// ---------- Load attempt (UNTOUCHED) ----------
$att = $pdo->prepare("
  SELECT id, deadline, status
  FROM academic_attempts
  WHERE session_id=?
  ORDER BY id DESC
  LIMIT 1
");
$att->execute([$sessionId]);
$attempt = $att->fetch(PDO::FETCH_ASSOC);
if (!$attempt) {
  header('Location: /User/academic-test.php');
  exit;
}

if (($attempt['status'] ?? '') === 'finished' || ($attempt['status'] ?? '') === 'expired') {
  header('Location: /User/test-result.php');
  exit;
}

// ---------- Timer hard check (UNTOUCHED) ----------
if (time() >= strtotime($attempt['deadline'])) {
  $pdo->prepare("UPDATE academic_attempts SET status='expired', finished_at=NOW() WHERE id=?")
    ->execute([(int)$attempt['id']]);
  header('Location: /User/test-result.php?reason=timeout');
  exit;
}

// ---------- Read POST (UNTOUCHED) ----------
$old = [
  'field'     => trim($_POST['field'] ?? ''),      // cluster
  'level'     => trim($_POST['level'] ?? ''),      // Diploma/Bachelor
  'course_id' => trim($_POST['course_id'] ?? ''),
  'cgpa'      => trim($_POST['cgpa'] ?? '')
];

// ---------- Validate basic (UNTOUCHED) ----------
$err = null;
if (in_array('', $old, true)) {
  $err = 'Please fill in all fields.';
}

if (!$err) {
  $cg = (float)$old['cgpa'];
  if ($cg < 0 || $cg > 4.00) $err = 'CGPA must be between 0.00 and 4.00.';
}

$validLevels = ['Diploma', 'Bachelor'];
if (!$err && !in_array($old['level'], $validLevels, true)) {
  $err = 'Please select a valid level.';
}

$courseRow = null;
if (!$err) {
  $cid = (int)$old['course_id'];
  if ($cid <= 0) {
    $err = 'Please select a valid course.';
  } else {
    $c = $pdo->prepare("
      SELECT id, cluster, level, course_name
      FROM academic_courses
      WHERE id=?
      LIMIT 1
    ");
    $c->execute([$cid]);
    $courseRow = $c->fetch(PDO::FETCH_ASSOC);

    if (!$courseRow) {
      $err = 'Course not found. Please choose again.';
    } elseif (($courseRow['cluster'] ?? '') !== $old['field']) {
      $err = 'Selected course does not match the chosen field.';
    } elseif (($courseRow['level'] ?? '') !== $old['level']) {
      $err = 'Selected course does not match the chosen level.';
    }
  }
}

// ---------- If error: back (UNTOUCHED) ----------
if ($err) {
  $_SESSION['acad_form_error'] = $err;
  $_SESSION['acad_form_old']   = $old;
  header('Location: /User/academic-test.php');
  exit;
}

// ---------- Save (UNTOUCHED) ----------
$pdo->prepare("
  INSERT INTO academic_answers (attempt_id, field_of_study, course_id, course, level, cgpa, marked_at)
  VALUES (?, ?, ?, ?, ?, ?, NOW())
")->execute([
  (int)$attempt['id'],
  $old['field'],
  (int)$courseRow['id'],
  (string)$courseRow['course_name'],
  $old['level'],
  (float)$old['cgpa']
]);

$pdo->prepare("UPDATE academic_attempts SET status='finished', finished_at=NOW() WHERE id=?")
  ->execute([(int)$attempt['id']]);

$pdo->prepare("UPDATE assessment_sessions SET status='submitted', finished_at=NOW() WHERE id=? LIMIT 1")
  ->execute([(int)$sessionId]);

// ---------- NEW UI RENDERING ----------
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assessment Complete | Entry2Pros</title>
  <link rel="stylesheet" href="/Main/index.css">
  <link rel="stylesheet" href="/Main/user.css">
  <link rel="stylesheet" href="/Main/assessment.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>

  <div class="page-wrapper dashboard-wrapper">
    <header class="main-header">
      <div class="brand-wrapper">
        <div class="brand-name-only">Entry<span class="text-green">2</span>Pros</div>
      </div>
      <nav class="user-nav">
        <div class="profile-pill-new">
          <div class="user-brand">
            <div class="avatar-mini"><i data-lucide="user"></i></div>
            <span class="user-handle">@<?= htmlspecialchars($username) ?></span>
          </div>
          <a href="/User/logout.php" class="logout-minimal">
            <span>Logout</span>
            <i data-lucide="power"></i>
          </a>
        </div>
      </nav>
    </header>

    <main class="dashboard-content">
      <div class="container" style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">

        <div class="instruction-card" style="max-width: 600px; width: 100%; text-align: center; display: flex; flex-direction: column; align-items: center;">

          <div class="icon-box-fixed" style="margin-bottom: 20px; width: 64px; height: 64px; background: #f0fdf4; display: flex; justify-content: center; align-items: center;">
            <i data-lucide="check-circle" style="color: #10b981; width: 32px; height: 32px;"></i>
          </div>

          <div class="test-header" style="text-align: center;">
            <h1>Academic <span class="highlight">Complete</span></h1>
            <p class="hero-sub" style="margin-left: auto; margin-right: auto; max-width: 450px;">
              Great job! You have finished the final stage. Your academic qualifications and test results are now being analyzed.
            </p>
          </div>

          <div class="ins-list" style="margin-top: 30px; text-align: left; display: inline-block; width: 100%; max-width: 400px;">
            <li style="justify-content: center;"><i data-lucide="shield-check" class="tick-green"></i> <span>Academic information verified</span></li>
            <li style="justify-content: center;"><i data-lucide="database" class="tick-green"></i> <span>Data synced with profile</span></li>
          </div>

          <div style="margin-top: 40px; width: 100%;">
            <a href="/User/test-result.php" class="btn btn-primary btn-large w-full" style="text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 10px;">
              View My Results
              <i data-lucide="layout-dashboard"></i>
            </a>
          </div>
        </div>

      </div>
    </main>

    <footer class="footer-fixed">
      <p>&copy; 2025 Entry2Profession. Built for the next generation of professionals.</p>
    </footer>
  </div>

  <script>
    lucide.createIcons();
  </script>
</body>

</html>