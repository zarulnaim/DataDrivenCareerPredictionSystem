<?php
// /User/aptitude-test.php
session_start();
require dirname(__DIR__) . '/Main/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// -------------- AUTH CHECK --------------
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
  header('Location: /User/user-login.php');
  exit;
}

$userId   = (int)$_SESSION['user']['id'];
$username = $_SESSION['user']['username'] ?? 'user';

if (empty($_SESSION['assessment_session_id']) || empty($_SESSION['aptitude_attempt_id'])) {
  header('Location: /User/test-session.php');
  exit;
}

$sessionId = (int)$_SESSION['assessment_session_id'];
$attemptId = (int)$_SESSION['aptitude_attempt_id'];

// -------------- LOAD ATTEMPT (for timer/status) --------------
$attStmt = $pdo->prepare("
  SELECT id, session_id, started_at, deadline, status
  FROM aptitude_attempts
  WHERE id = ?
  LIMIT 1
");
$attStmt->execute([$attemptId]);
$attemptRow = $attStmt->fetch(PDO::FETCH_ASSOC);

if (!$attemptRow) {
  header('Location: /User/test-session.php');
  exit;
}

// already done or expired?
if ($attemptRow['status'] === 'finished' || $attemptRow['status'] === 'expired') {
  header('Location: /User/aptitude-submit.php');
  exit;
}

// TIMER ENFORCEMENT
$deadlineTs = strtotime($attemptRow['deadline']);
$nowTs      = time();
$timeLeft   = $deadlineTs - $nowTs;

if ($timeLeft <= 0) {
  $markExp = $pdo->prepare("
    UPDATE aptitude_attempts
    SET status='expired', finished_at=NOW()
    WHERE id=?
  ");
  $markExp->execute([$attemptId]);

  header('Location: /User/aptitude-submit.php?reason=timeout');
  exit;
}

$timeLeftSeconds = max(0, $timeLeft);

// -------------- SESSION STATE (flow engine) --------------
$questionOrder = $_SESSION['aptitude_order']   ?? [];
$index         = $_SESSION['aptitude_index']   ?? 0;
$phase         = $_SESSION['aptitude_phase']   ?? 'main';   // 'main' | 'skipped' | 'end_main' | 'done'
$answers       = $_SESSION['aptitude_answers'] ?? [];
$skipped       = $_SESSION['aptitude_skipped'] ?? [];

if (!$questionOrder || !is_array($questionOrder)) {
  header('Location: /User/aptitude-intro.php');
  exit;
}

/**
 * IMPORTANT (UI-only support):
 * Keep a copy of the ORIGINAL main question order so progress is always /TOTAL_MAIN,
 * even when session aptitude_order gets replaced by skippedIds.
 * This does NOT change test flow, only fixes the progress display.
 */
if (empty($_SESSION['aptitude_main_order']) && is_array($questionOrder) && $questionOrder) {
  $_SESSION['aptitude_main_order'] = $questionOrder;
}

// -------------- HANDLE POST (answer / skip / back / proceed_end) --------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $action = $_POST['action'] ?? '';
  $qid    = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
  $choice = $_POST['answer'] ?? '';

  // record answer if provided
  if ($qid && in_array($choice, ['A', 'B', 'C', 'D'], true)) {
    $_SESSION['aptitude_answers'][$qid] = $choice;
    unset($_SESSION['aptitude_skipped'][$qid]);
  }

  if ($action === 'skip' && $qid) {
    if (empty($_SESSION['aptitude_answers'][$qid])) {
      $_SESSION['aptitude_skipped'][$qid] = true;
    }
    $_SESSION['aptitude_index'] = $index + 1;
  } elseif ($action === 'back') {
    // ✅ Fix: If we're on the end screen, go back to the LAST main question and restore phase=main
    if (($_SESSION['aptitude_phase'] ?? 'main') === 'end_main') {
      $_SESSION['aptitude_phase'] = 'main';
      $_SESSION['aptitude_index'] = max(0, count($questionOrder) - 1);
    } else {
      $_SESSION['aptitude_index'] = max(0, $index - 1);
    }
  } elseif ($action === 'proceed_end') {
    // From end screen → go to skipped or submit
    $skippedIds = array_keys($_SESSION['aptitude_skipped'] ?? []);
    if ($skippedIds) {
      $_SESSION['aptitude_phase'] = 'skipped';
      $_SESSION['aptitude_order'] = $skippedIds;
      $_SESSION['aptitude_index'] = 0;
    } else {
      $_SESSION['aptitude_phase'] = 'done';
      header('Location: /User/aptitude-submit.php');
      exit;
    }
  } else {
    // default: next (auto-next after answer)
    $_SESSION['aptitude_index'] = $index + 1;
  }

  // handle phase transitions
  if ($_SESSION['aptitude_phase'] === 'main') {
    $totalMain = count($questionOrder);
    if ($_SESSION['aptitude_index'] >= $totalMain) {
      // Pause at completion screen
      $_SESSION['aptitude_phase'] = 'end_main';
      // keep index as-is (== $totalMain) so Back can jump to last question
    }
  } elseif ($_SESSION['aptitude_phase'] === 'skipped') {
    $totalSkipped = count($_SESSION['aptitude_order']);
    if ($_SESSION['aptitude_index'] >= $totalSkipped) {
      $_SESSION['aptitude_phase'] = 'done';
      header('Location: /User/aptitude-submit.php');
      exit;
    }
  }

  header('Location: /User/aptitude-test.php');
  exit;
}

// ---------------- UI HELPERS (progress + labels) ----------------
$mainOrder = $_SESSION['aptitude_main_order'] ?? [];
$totalMainQuestions = is_array($mainOrder) ? count($mainOrder) : 0;

$answeredCount = 0;
if ($totalMainQuestions > 0) {
  foreach ($mainOrder as $qid) {
    $qid = (int)$qid;
    $val = $_SESSION['aptitude_answers'][$qid] ?? '';
    if (in_array($val, ['A', 'B', 'C', 'D'], true)) {
      $answeredCount++;
    }
  }
} else {
  // fallback (shouldn't happen): count whatever answers exist
  $answeredCount = is_array($_SESSION['aptitude_answers'] ?? null) ? count($_SESSION['aptitude_answers']) : 0;
}

$progressPct = ($totalMainQuestions > 0)
  ? (int)round(($answeredCount / $totalMainQuestions) * 100)
  : 0;

if ($progressPct < 0) $progressPct = 0;
if ($progressPct > 100) $progressPct = 100;

// ---------------- RENDER ----------------

// End-of-main screen
if ($phase === 'end_main') {
  $hasSkipped = !empty($_SESSION['aptitude_skipped']);
  $phaseLabel = 'Aptitude';
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aptitude – Completed</title>
    <link rel="stylesheet" href="/Main/test.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script>
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
          window.location.href = "/User/aptitude-submit.php?reason=timeout";
          return;
        }

        box.textContent = formatTime(timeLeft);
        timeLeft -= 1;
        setTimeout(tickTimer, 1000);
      }

      window.addEventListener('DOMContentLoaded', tickTimer);
    </script>
  </head>

  <body>
    <main class="exam-page">
      <section class="exam-card">

        <header class="exam-top">
          <div class="exam-badges">
            <span class="badge badge-green"><?= htmlspecialchars($phaseLabel) ?></span>
          </div>

          <div class="exam-timer" aria-label="Time left">
            <span class="timer-label">Time Left</span>
            <span class="timer-value" id="timer-display">--:--</span>
          </div>
        </header>

        <div class="exam-progress">
          <div class="progress-meta">
            <span class="progress-text">Answered <?= (int)$answeredCount ?> / <?= (int)$totalMainQuestions ?></span>
            <span class="progress-text"><?= (int)$progressPct ?>%</span>
          </div>
          <div class="progress-bar" role="progressbar" aria-valuenow="<?= (int)$progressPct ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-fill" style="width: <?= (int)$progressPct ?>%;"></div>
          </div>
        </div>

        <div class="complete-wrap">
          <div class="complete-card">
            <h1 class="complete-title">You’ve completed the Aptitude questions.</h1>
            <p class="complete-desc">
              <?php if ($hasSkipped): ?>
                You still have <strong>skipped questions</strong> to review before submitting.
              <?php else: ?>
                No skipped questions detected. You can submit now.
              <?php endif; ?>
            </p>

            <div class="complete-info">
              <div class="info-pill">
                <span class="info-label">Status</span>
                <span class="info-value"><?= $hasSkipped ? 'Action Required' : 'Ready to Submit' ?></span>
              </div>
              <div class="info-pill">
                <span class="info-label">Skipped</span>
                <span class="info-value"><?= (int)count($_SESSION['aptitude_skipped'] ?? []) ?></span>
              </div>
            </div>

            <div class="complete-actions">
              <form action="" method="POST" class="inline-form">
                <input type="hidden" name="action" value="back">
                <button type="submit" class="btn btn-ghost">Back</button>
              </form>

              <form action="" method="POST" class="inline-form">
                <input type="hidden" name="action" value="proceed_end">
                <button type="submit" class="btn btn-primary">
                  <?= $hasSkipped ? 'Review Skipped' : 'Submit' ?>
                </button>
              </form>
            </div>
          </div>
        </div>

      </section>

      <footer class="exam-footer">&copy; 2025 Entry2Profession. All rights reserved.</footer>
    </main>
  </body>

  </html>
<?php
  exit;
}

// --------- (Normal question rendering continues) ---------
$phaseNow   = $_SESSION['aptitude_phase'] ?? 'main';
$orderNow   = ($phaseNow === 'skipped') ? ($_SESSION['aptitude_order'] ?? []) : $questionOrder;
$currIndex  = $_SESSION['aptitude_index'] ?? 0;
$totalNow   = count($orderNow);

if ($phaseNow === 'done' || $currIndex >= $totalNow) {
  header('Location: /User/aptitude-submit.php');
  exit;
}

$currentQuestionId = (int)$orderNow[$currIndex];

$qStmt = $pdo->prepare("
  SELECT id, section, question_txt,
         option_a, option_b, option_c, option_d
  FROM aptitude_questions
  WHERE id = ?
  LIMIT 1
");
$qStmt->execute([$currentQuestionId]);
$qRow = $qStmt->fetch(PDO::FETCH_ASSOC);

if (!$qRow) {
  $_SESSION['aptitude_index'] = $currIndex + 1;
  header('Location: /User/aptitude-test.php');
  exit;
}

$currentAnswer = $_SESSION['aptitude_answers'][$currentQuestionId] ?? '';

$phaseLabel = ($phaseNow === 'skipped') ? 'Review Skipped' : 'Aptitude';
$questionNumberHuman   = $currIndex + 1;
$totalQuestionsHuman   = $totalNow;
$canGoBack = ($currIndex > 0);
$canSkip   = ($phaseNow === 'main');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aptitude Test</title>

  <link rel="stylesheet" href="/Main/test.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <script>
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
        window.location.href = "/User/aptitude-submit.php?reason=timeout";
        return;
      }

      box.textContent = formatTime(timeLeft);
      timeLeft -= 1;
      setTimeout(tickTimer, 1000);
    }

    window.addEventListener('DOMContentLoaded', tickTimer);

    function chooseAnswer(ansVal) {
      const form = document.getElementById('answer-form');
      document.getElementById('answer-field').value = ansVal;
      document.getElementById('action-field').value = "next";
      form.submit();
    }
  </script>
</head>

<body>
  <main class="exam-page">
    <section class="exam-card">

      <header class="exam-top">
        <div class="exam-badges">
          <span class="badge <?= ($phaseNow === 'skipped') ? 'badge-amber' : 'badge-green' ?>">
            <?= htmlspecialchars($phaseLabel) ?>
          </span>
          <span class="badge badge-soft">
            Question <?= (int)$questionNumberHuman ?> / <?= (int)$totalQuestionsHuman ?>
          </span>
        </div>

        <div class="exam-timer" aria-label="Time left">
          <span class="timer-label">Time Left</span>
          <span class="timer-value" id="timer-display">--:--</span>
        </div>
      </header>

      <div class="exam-progress">
        <div class="progress-meta">
          <span class="progress-text">Answered <?= (int)$answeredCount ?> / <?= (int)$totalMainQuestions ?></span>
          <span class="progress-text"><?= (int)$progressPct ?>%</span>
        </div>
        <div class="progress-bar" role="progressbar" aria-valuenow="<?= (int)$progressPct ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-fill" style="width: <?= (int)$progressPct ?>%;"></div>
        </div>
      </div>

      <div class="exam-body">
        <div class="question-text" title="<?= htmlspecialchars($qRow['question_txt']) ?>">
          <?= htmlspecialchars($qRow['question_txt']) ?>
        </div>

        <form id="answer-form" action="" method="POST">
          <input type="hidden" name="question_id" value="<?= (int)$qRow['id'] ?>">
          <input type="hidden" name="answer" id="answer-field" value="">
          <input type="hidden" name="action" id="action-field" value="">
        </form>

        <div class="options">
          <button type="button" class="opt-btn <?= ($currentAnswer === 'A' ? 'selected' : '') ?>" onclick="chooseAnswer('A')">
            <span class="opt-key">A</span>
            <span class="opt-text"><?= htmlspecialchars($qRow['option_a']) ?></span>
          </button>

          <button type="button" class="opt-btn <?= ($currentAnswer === 'B' ? 'selected' : '') ?>" onclick="chooseAnswer('B')">
            <span class="opt-key">B</span>
            <span class="opt-text"><?= htmlspecialchars($qRow['option_b']) ?></span>
          </button>

          <button type="button" class="opt-btn <?= ($currentAnswer === 'C' ? 'selected' : '') ?>" onclick="chooseAnswer('C')">
            <span class="opt-key">C</span>
            <span class="opt-text"><?= htmlspecialchars($qRow['option_c']) ?></span>
          </button>

          <button type="button" class="opt-btn <?= ($currentAnswer === 'D' ? 'selected' : '') ?>" onclick="chooseAnswer('D')">
            <span class="opt-key">D</span>
            <span class="opt-text"><?= htmlspecialchars($qRow['option_d']) ?></span>
          </button>
        </div>
      </div>

      <div class="exam-nav">
        <div class="nav-left">
          <form action="" method="POST" class="inline-form">
            <input type="hidden" name="question_id" value="<?= (int)$qRow['id'] ?>">
            <input type="hidden" name="action" value="back">
            <button
              type="submit"
              class="btn btn-ghost <?= !$canGoBack ? 'is-disabled' : '' ?>"
              <?= !$canGoBack ? 'disabled' : '' ?>>Back</button>
          </form>
        </div>

        <div class="nav-right">
          <form action="" method="POST" class="inline-form">
            <input type="hidden" name="question_id" value="<?= (int)$qRow['id'] ?>">
            <input type="hidden" name="action" value="skip">
            <button
              type="submit"
              class="btn btn-warn <?= !$canSkip ? 'is-disabled' : '' ?>"
              <?= !$canSkip ? 'disabled' : '' ?>>Skip</button>
          </form>
        </div>
      </div>

    </section>

    <footer class="exam-footer">&copy; 2025 Entry2Profession. All rights reserved.</footer>
  </main>
</body>

</html>