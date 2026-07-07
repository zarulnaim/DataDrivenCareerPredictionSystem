<?php
// /User/academic-test.php
session_start();
require dirname(__DIR__) . '/Main/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// ---------- AUTH ----------
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
  header('Location: /User/user-login.php');
  exit;
}
$username  = $_SESSION['user']['username'] ?? '';
$sessionId = $_SESSION['assessment_session_id'] ?? null;
if (!$sessionId) {
  header('Location: /User/test-session.php');
  exit;
}

// ---------- Ensure academic_attempt (5 min) ----------
$attSel = $pdo->prepare("
  SELECT id, deadline, status
  FROM academic_attempts
  WHERE session_id=?
  ORDER BY id DESC
  LIMIT 1
");
$attSel->execute([$sessionId]);
$attempt = $attSel->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
  $pdo->prepare("
    INSERT INTO academic_attempts (session_id, started_at, deadline, status)
    VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'in_progress')
  ")->execute([$sessionId]);

  $attSel->execute([$sessionId]);
  $attempt = $attSel->fetch(PDO::FETCH_ASSOC);
} elseif (($attempt['status'] ?? '') === 'not_started') {
  $pdo->prepare("
    UPDATE academic_attempts
    SET started_at=NOW(),
        deadline=DATE_ADD(NOW(), INTERVAL 5 MINUTE),
        status='in_progress'
    WHERE id=?
  ")->execute([(int)$attempt['id']]);

  $attSel->execute([$sessionId]);
  $attempt = $attSel->fetch(PDO::FETCH_ASSOC);
}

if (!$attempt) {
  die('Could not start academic attempt.');
}
if (($attempt['status'] ?? '') === 'finished' || ($attempt['status'] ?? '') === 'expired') {
  header('Location: /User/test-result.php');
  exit;
}

// ---------- Timer ----------
$timeLeft = strtotime($attempt['deadline']) - time();
if ($timeLeft <= 0) {
  $pdo->prepare("UPDATE academic_attempts SET status='expired', finished_at=NOW() WHERE id=?")
    ->execute([(int)$attempt['id']]);
  header('Location: /User/test-result.php?reason=timeout');
  exit;
}
$timeLeftSeconds = max(0, $timeLeft);

// ---------- Sticky form data ----------
$old = $_SESSION['acad_form_old'] ?? [
  'field'     => '',   // cluster
  'level'     => '',   // Diploma/Bachelor
  'course_id' => '',
  'cgpa'      => ''
];
$errorMsg = $_SESSION['acad_form_error'] ?? null;
unset($_SESSION['acad_form_old'], $_SESSION['acad_form_error']);

// ---------- Pull courses from DB (ikut schema kau) ----------
$courses = $pdo->query("
  SELECT id, cluster, level, course_name
  FROM academic_courses
  ORDER BY cluster, level, course_name
")->fetchAll(PDO::FETCH_ASSOC);

// Build: COURSE_MAP[cluster][level] = [{id,name}]
$COURSE_MAP = [];
$CLUSTERS = [];
foreach ($courses as $r) {
  $cl = (string)($r['cluster'] ?? '');
  $lv = (string)($r['level'] ?? '');
  $id = (int)($r['id'] ?? 0);
  $nm = (string)($r['course_name'] ?? '');

  if ($cl === '' || $lv === '' || $id <= 0 || $nm === '') continue;

  $CLUSTERS[$cl] = true;
  if (!isset($COURSE_MAP[$cl])) $COURSE_MAP[$cl] = [];
  if (!isset($COURSE_MAP[$cl][$lv])) $COURSE_MAP[$cl][$lv] = [];
  $COURSE_MAP[$cl][$lv][] = ['id' => $id, 'name' => $nm];
}
$CLUSTERS = array_keys($CLUSTERS);
sort($CLUSTERS);

// Levels based on DB enum
$LEVELS = ['Diploma', 'Bachelor'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Academic Qualification</title>

  <link rel="stylesheet" href="/Main/test.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <script>
    // =========================
    // Timer (UI only)
    // =========================
    let timeLeft = <?= (int)$timeLeftSeconds ?>;

    function formatTime(sec) {
      const m = Math.floor(sec / 60);
      const s = sec % 60;
      return (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);
    }

    function tickTimer() {
      const box = document.getElementById('timer-display');
      if (!box) return;

      if (timeLeft <= 0) {
        box.textContent = "00:00";
        // Redirect back to this page so backend will mark expired & redirect properly
        window.location.href = "/User/academic-test.php?reason=timeout";
        return;
      }

      box.textContent = formatTime(timeLeft);
      timeLeft -= 1;
      setTimeout(tickTimer, 1000);
    }

    // =========================
    // Course map (UI only)
    // =========================
    const COURSE_MAP = <?= json_encode($COURSE_MAP, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const OLD = <?= json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function populateCourses(keepSelected) {
      const cluster = document.getElementById('field').value;
      const level = document.getElementById('level').value;
      const sel = document.getElementById('course_id');

      sel.innerHTML = '<option value="">-- Select Course --</option>';

      const list = (COURSE_MAP && COURSE_MAP[cluster] && COURSE_MAP[cluster][level]) ? COURSE_MAP[cluster][level] : [];
      for (const item of list) {
        const opt = document.createElement('option');
        opt.value = String(item.id);
        opt.textContent = item.name;

        if (keepSelected && String(OLD.course_id) === String(item.id)) {
          opt.selected = true;
        }
        sel.appendChild(opt);
      }
    }

    window.addEventListener('DOMContentLoaded', () => {
      tickTimer();

      const fieldSel = document.getElementById('field');
      const levelSel = document.getElementById('level');

      fieldSel.addEventListener('change', () => populateCourses(false));
      levelSel.addEventListener('change', () => populateCourses(false));

      if (OLD.field) fieldSel.value = OLD.field;
      if (OLD.level) levelSel.value = OLD.level;
      populateCourses(true);
    });
  </script>
</head>

<body>
  <main class="exam-page">
    <section class="exam-card exam-card--form">

      <!-- Top bar (same as aptitude style) -->
      <header class="exam-top">
        <div class="exam-badges">
          <span class="badge badge-green">Academic</span>
          <span class="badge badge-soft">Final Step</span>
        </div>

        <div class="exam-timer" aria-label="Time left">
          <span class="timer-label">Time Left</span>
          <span class="timer-value" id="timer-display">--:--</span>
        </div>
      </header>

      <!-- Body -->
      <div class="exam-body exam-body--form">
        <h1 class="acad-title">Academic Qualification</h1>
        <p class="acad-desc">
          Select your field, education level, course/major, and CGPA accurately. This will be used in your final recommendation.
        </p>

        <?php if (!empty($errorMsg)): ?>
          <div class="acad-error"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <form id="academic-form" method="POST" action="/User/academic-submit.php" class="acad-form" novalidate>
          <div class="acad-grid">

            <!-- Field (cluster) -->
            <div class="acad-field">
              <label class="acad-label" for="field">Field of Study</label>
              <select id="field" name="field" class="acad-control" required>
                <option value="">-- Select Field --</option>
                <?php foreach ($CLUSTERS as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>" <?= $old['field'] === $c ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Level -->
            <div class="acad-field">
              <label class="acad-label" for="level">Level of Education</label>
              <select id="level" name="level" class="acad-control" required>
                <option value="">-- Select Level --</option>
                <?php foreach ($LEVELS as $lv): ?>
                  <option value="<?= htmlspecialchars($lv) ?>" <?= $old['level'] === $lv ? 'selected' : '' ?>>
                    <?= ($lv === 'Bachelor') ? "Bachelor's Degree" : "Diploma" ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Course -->
            <div class="acad-field acad-field--full">
              <label class="acad-label" for="course_id">Course / Major</label>
              <select id="course_id" name="course_id" class="acad-control" required>
                <option value="">-- Select Course --</option>
                <!-- injected by JS -->
              </select>
            </div>

            <!-- CGPA -->
            <div class="acad-field acad-field--full">
              <label class="acad-label" for="cgpa">CGPA</label>
              <input
                type="number"
                id="cgpa"
                name="cgpa"
                class="acad-control"
                step="0.01"
                min="0"
                max="4.00"
                placeholder="e.g., 3.25"
                value="<?= htmlspecialchars($old['cgpa']) ?>"
                required>
            </div>

          </div>
        </form>
      </div>

      <!-- Bottom bar (button always visible like aptitude) -->
      <div class="exam-nav">
        <div class="nav-left">
          <span class="nav-note">Complete all fields to continue.</span>
        </div>

        <div class="nav-right">
          <button type="submit" class="btn btn-primary" form="academic-form">
            Next
          </button>
        </div>
      </div>

    </section>

    <footer class="exam-footer">&copy; 2025 Entry2Profession. All rights reserved.</footer>
  </main>
</body>

</html>