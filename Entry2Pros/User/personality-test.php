<?php
// /User/personality-test.php
session_start();
require dirname(__DIR__) . '/Main/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// -------- AUTH --------
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
  header('Location: /User/user-login.php');
  exit;
}

$userId   = (int)$_SESSION['user']['id'];
$username = $_SESSION['user']['username'] ?? 'user';

// must come from an active assessment session
if (empty($_SESSION['assessment_session_id'])) {
  header('Location: /User/test-session.php');
  exit;
}

$sessionId = (int)$_SESSION['assessment_session_id'];

function e(string $v): string
{
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/*
Tables:
- personality_attempts(id, session_id, started_at, deadline, finished_at, status,
  score_openness, score_conscientiousness, score_extraversion, score_agreeableness, score_neuroticism)
- personality_questions(id, statement_txt, section, reverse_scored, is_active, ...)
- personality_answers(id, attempt_id, statement_id, response) UNIQUE(attempt_id,statement_id)
*/

// -------- Ensure attempt (15 minutes) --------
$attSel = $pdo->prepare("
  SELECT id, started_at, deadline, status
  FROM personality_attempts
  WHERE session_id = ?
  ORDER BY started_at DESC, id DESC
  LIMIT 1
");
$attSel->execute([$sessionId]);
$attempt = $attSel->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
  $ins = $pdo->prepare("
    INSERT INTO personality_attempts (session_id, started_at, deadline, status)
    VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 'in_progress')
  ");
  $ins->execute([$sessionId]);

  $attSel->execute([$sessionId]);
  $attempt = $attSel->fetch(PDO::FETCH_ASSOC);
} else {
  $attId = (int)$attempt['id'];
  if ($attempt['status'] === 'not_started') {
    $upd = $pdo->prepare("
      UPDATE personality_attempts
      SET started_at = NOW(),
          deadline   = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
          status     = 'in_progress'
      WHERE id = ?
    ");
    $upd->execute([$attId]);

    $attSel->execute([$sessionId]);
    $attempt = $attSel->fetch(PDO::FETCH_ASSOC);
  }
}

if (!$attempt) {
  die("Could not start personality attempt.");
}
$attemptId = (int)$attempt['id'];

// ---------- OCEAN scoring (robust) ----------
function computeAndSavePersonalityScores(PDO $pdo, int $attemptId): void
{
  $mapTrait = function ($raw): string {
    $s = strtoupper(trim((string)$raw));
    if (in_array($s, ['O', 'OPEN', 'OPENNESS'], true)) return 'O';
    if (in_array($s, ['C', 'CONS', 'CONSC', 'CONSCIENTIOUSNESS'], true)) return 'C';
    if (in_array($s, ['E', 'EXTRA', 'EXTRAVERSION'], true)) return 'E';
    if (in_array($s, ['A', 'AGREE', 'AGREEABLENESS'], true)) return 'A';
    if (in_array($s, ['N', 'NEURO', 'NEUROTICISM'], true)) return 'N';
    return '';
  };

  $stmt = $pdo->prepare("
    SELECT q.section, q.reverse_scored, a.response
    FROM personality_answers a
    JOIN personality_questions q ON q.id = a.statement_id
    WHERE a.attempt_id = ?
  ");
  $stmt->execute([$attemptId]);

  $sum = ['O' => 0, 'C' => 0, 'E' => 0, 'A' => 0, 'N' => 0];
  $cnt = ['O' => 0, 'C' => 0, 'E' => 0, 'A' => 0, 'N' => 0];

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sec = $mapTrait($row['section'] ?? '');
    if (!isset($sum[$sec])) continue;

    $resp = (int)($row['response'] ?? 0);
    if ($resp < 1 || $resp > 5) continue;

    if (!empty($row['reverse_scored'])) {
      $resp = 6 - $resp; // reverse 1..5
    }

    $sum[$sec] += $resp;
    $cnt[$sec] += 1;
  }

  $avg = [];
  foreach ($sum as $k => $v) {
    $avg[$k] = $cnt[$k] ? round($v / $cnt[$k], 2) : null;
  }

  // convert 1–5 -> 0–10
  $scaled = [];
  foreach ($avg as $k => $v) {
    $scaled[$k] = ($v === null) ? null : round((($v - 1) / 4) * 10, 2);
  }

  $upd = $pdo->prepare("
    UPDATE personality_attempts
    SET
      score_openness          = :o,
      score_conscientiousness = :c,
      score_extraversion      = :e,
      score_agreeableness     = :a,
      score_neuroticism       = :n
    WHERE id = :id
  ");
  $upd->execute([
    ':o' => $scaled['O'],
    ':c' => $scaled['C'],
    ':e' => $scaled['E'],
    ':a' => $scaled['A'],
    ':n' => $scaled['N'],
    ':id' => $attemptId,
  ]);
}

// already done/expired -> go submit page
if ($attempt['status'] === 'finished' || $attempt['status'] === 'expired') {
  header('Location: /User/personality-submit.php');
  exit;
}

// -------- Timer --------
$deadlineTs = strtotime((string)$attempt['deadline']);
$nowTs      = time();
$timeLeft   = $deadlineTs - $nowTs;

if ($timeLeft <= 0) {
  $pdo->prepare("UPDATE personality_attempts SET status='expired', finished_at=NOW() WHERE id=?")
    ->execute([$attemptId]);
  header('Location: /User/personality-submit.php?reason=timeout');
  exit;
}

$timeLeftSeconds = max(0, $timeLeft);

// -------- Session Flow State --------
$questionOrder = $_SESSION['personality_order']   ?? null;
$index         = (int)($_SESSION['personality_index'] ?? 0);
$phase         = $_SESSION['personality_phase']   ?? 'main'; // main | skipped | end_main | done
$answers       = $_SESSION['personality_answers'] ?? [];
$skipped       = $_SESSION['personality_skipped'] ?? [];

if (!$questionOrder || !is_array($questionOrder)) {
  // initialize question order (random active)
  // initialize question order (random active) — EXCLUDE empty statements
  $qIds = $pdo->query("
  SELECT id
  FROM personality_questions
  WHERE is_active=1
    AND statement_txt IS NOT NULL
    AND TRIM(statement_txt) <> ''
")->fetchAll(PDO::FETCH_COLUMN);

  shuffle($qIds);

  $_SESSION['personality_order']   = $questionOrder = $qIds;
  $_SESSION['personality_index']   = $index = 0;
  $_SESSION['personality_phase']   = $phase = 'main';
  $_SESSION['personality_answers'] = $answers = [];
  $_SESSION['personality_skipped'] = $skipped = [];

  // ✅ UI-only support (same idea as aptitude_main_order)
  $_SESSION['personality_main_order'] = $qIds;
}

// ✅ UI-only support:
// Keep ORIGINAL main order for consistent progress /TOTAL_MAIN even when order changes to skippedIds.
if (empty($_SESSION['personality_main_order']) && is_array($questionOrder) && $questionOrder && $phase === 'main') {
  $_SESSION['personality_main_order'] = $questionOrder;
}

// -------- Handle POST (answer/skip/back/proceed_end) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action      = $_POST['action'] ?? '';
  $statementId = isset($_POST['statement_id']) ? (int)$_POST['statement_id'] : 0;
  $resp        = isset($_POST['response']) ? (int)$_POST['response'] : null;

  // record answer if valid
  if ($statementId && $resp !== null && $resp >= 1 && $resp <= 5) {
    // verify statement still active
    $check = $pdo->prepare("SELECT id FROM personality_questions WHERE id=? AND is_active=1 LIMIT 1");
    $check->execute([$statementId]);

    if ($check->fetch(PDO::FETCH_ASSOC)) {
      // UPSERT
      $up = $pdo->prepare("
        INSERT INTO personality_answers (attempt_id, statement_id, response)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE response = VALUES(response)
      ");
      $up->execute([$attemptId, $statementId, $resp]);

      $_SESSION['personality_answers'][$statementId] = $resp;
      unset($_SESSION['personality_skipped'][$statementId]);
    }
  }

  if ($action === 'skip' && $statementId) {
    if (empty($_SESSION['personality_answers'][$statementId])) {
      $_SESSION['personality_skipped'][$statementId] = true;
    }
    $_SESSION['personality_index'] = $index + 1;
  } elseif ($action === 'back') {
    if (($_SESSION['personality_phase'] ?? 'main') === 'end_main') {
      $_SESSION['personality_phase'] = 'main';
      $_SESSION['personality_index'] = max(0, count($_SESSION['personality_order']) - 1);
    } else {
      $_SESSION['personality_index'] = max(0, $index - 1);
    }
  } elseif ($action === 'proceed_end') {
    $skippedIds = array_keys($_SESSION['personality_skipped'] ?? []);
    if ($skippedIds) {
      $_SESSION['personality_phase'] = 'skipped';
      $_SESSION['personality_order'] = $skippedIds;
      $_SESSION['personality_index'] = 0;
    } else {
      $_SESSION['personality_phase'] = 'done';
      computeAndSavePersonalityScores($pdo, $attemptId);
      $pdo->prepare("UPDATE personality_attempts SET status='finished', finished_at=NOW() WHERE id=?")
        ->execute([$attemptId]);
      header('Location: /User/personality-submit.php?done=1');
      exit;
    }
  } else {
    // default next (auto-next after answer)
    $_SESSION['personality_index'] = $index + 1;
  }

  // transitions
  if (($_SESSION['personality_phase'] ?? 'main') === 'main') {
    $totalMain = count($_SESSION['personality_order']);
    if (($_SESSION['personality_index'] ?? 0) >= $totalMain) {
      $_SESSION['personality_phase'] = 'end_main';
    }
  } elseif (($_SESSION['personality_phase'] ?? '') === 'skipped') {
    $totalSkipped = count($_SESSION['personality_order']);
    if (($_SESSION['personality_index'] ?? 0) >= $totalSkipped) {
      $_SESSION['personality_phase'] = 'done';
      computeAndSavePersonalityScores($pdo, $attemptId);
      $pdo->prepare("UPDATE personality_attempts SET status='finished', finished_at=NOW() WHERE id=?")
        ->execute([$attemptId]);
      header('Location: /User/personality-submit.php?done=1');
      exit;
    }
  }

  header('Location: /User/personality-test.php');
  exit;
}

// ---------------- UI HELPERS (progress + labels) ----------------
$mainOrder = $_SESSION['personality_main_order'] ?? [];
$totalMainStatements = is_array($mainOrder) ? count($mainOrder) : 0;

$answeredCount = 0;
if ($totalMainStatements > 0) {
  foreach ($mainOrder as $sid) {
    $sid = (int)$sid;
    $val = $_SESSION['personality_answers'][$sid] ?? null;
    $val = is_numeric($val) ? (int)$val : 0;
    if ($val >= 1 && $val <= 5) {
      $answeredCount++;
    }
  }
} else {
  $answeredCount = is_array($_SESSION['personality_answers'] ?? null) ? count($_SESSION['personality_answers']) : 0;
}

$progressPct = ($totalMainStatements > 0)
  ? (int)round(($answeredCount / $totalMainStatements) * 100)
  : 0;

if ($progressPct < 0) $progressPct = 0;
if ($progressPct > 100) $progressPct = 100;

// ---------------- RENDER ----------------

// End-of-main screen
if ($phase === 'end_main') {
  $hasSkipped = !empty($_SESSION['personality_skipped']);
  $phaseLabel = 'Personality';
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personality – Completed</title>
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
          window.location.href = "/User/personality-submit.php?reason=timeout";
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
            <span class="badge badge-green"><?= e($phaseLabel) ?></span>
          </div>

          <div class="exam-timer" aria-label="Time left">
            <span class="timer-label">Time Left</span>
            <span class="timer-value" id="timer-display">--:--</span>
          </div>
        </header>

        <div class="exam-progress">
          <div class="progress-meta">
            <span class="progress-text">Answered <?= (int)$answeredCount ?> / <?= (int)$totalMainStatements ?></span>
            <span class="progress-text"><?= (int)$progressPct ?>%</span>
          </div>
          <div class="progress-bar" role="progressbar" aria-valuenow="<?= (int)$progressPct ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-fill" style="width: <?= (int)$progressPct ?>%;"></div>
          </div>
        </div>

        <div class="complete-wrap">
          <div class="complete-card">
            <h1 class="complete-title">You’ve completed the Personality statements.</h1>
            <p class="complete-desc">
              <?php if ($hasSkipped): ?>
                You still have <strong>skipped statements</strong> to review before submitting.
              <?php else: ?>
                No skipped statements detected. You can submit now.
              <?php endif; ?>
            </p>

            <div class="complete-info">
              <div class="info-pill">
                <span class="info-label">Status</span>
                <span class="info-value"><?= $hasSkipped ? 'Action Required' : 'Ready to Submit' ?></span>
              </div>
              <div class="info-pill">
                <span class="info-label">Skipped</span>
                <span class="info-value"><?= (int)count($_SESSION['personality_skipped'] ?? []) ?></span>
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

// --------- Normal statement rendering ---------
$phaseNow   = $_SESSION['personality_phase'] ?? 'main';
$orderNow   = $_SESSION['personality_order'] ?? [];
$currIndex  = (int)($_SESSION['personality_index'] ?? 0);
$totalNow   = count($orderNow);

// safety: if ran out, finalize
if ($phaseNow === 'done' || $currIndex >= $totalNow) {
  computeAndSavePersonalityScores($pdo, $attemptId);
  $pdo->prepare("UPDATE personality_attempts SET status='finished', finished_at=NOW() WHERE id=?")
    ->execute([$attemptId]);
  header('Location: /User/personality-submit.php?done=1');
  exit;
}

$currentStatementId = (int)$orderNow[$currIndex];

// fetch statement
$qStmt = $pdo->prepare("
  SELECT id, statement_txt
  FROM personality_questions
  WHERE id = ?
  LIMIT 1
");
$qStmt->execute([$currentStatementId]);
$stRow = $qStmt->fetch(PDO::FETCH_ASSOC);

$statementText = trim((string)($stRow['statement_txt'] ?? ''));

// kalau row tak wujud ATAU statement kosong -> buang dari order & reload (avoid blank screen)
if (!$stRow || $statementText === '') {

  // buang ID ini dari order supaya tak muncul lagi
  if (isset($_SESSION['personality_order']) && is_array($_SESSION['personality_order'])) {
    array_splice($_SESSION['personality_order'], $currIndex, 1);
  }

  // buang juga dari skipped/answers kalau ada (elak mismatch "Statement x / y")
  unset($_SESSION['personality_skipped'][$currentStatementId]);
  unset($_SESSION['personality_answers'][$currentStatementId]);

  // jangan tambah index, sebab kita dah buang current item
  header('Location: /User/personality-test.php');
  exit;
}


if (!$stRow) {
  $_SESSION['personality_index'] = $currIndex + 1;
  header('Location: /User/personality-test.php');
  exit;
}

$currentAnswer = $_SESSION['personality_answers'][$currentStatementId] ?? null;
$currentAnswer = is_numeric($currentAnswer) ? (int)$currentAnswer : 0;

$phaseLabel = ($phaseNow === 'skipped') ? 'Review Skipped' : 'Personality';
$statementNumberHuman = $currIndex + 1;
$totalStatementsHuman = $totalNow;

$canGoBack = ($currIndex > 0);
$canSkip   = ($phaseNow === 'main');

// Likert labels (reuse opt-btn component)
$likert = [
  1 => "Strongly Disagree",
  2 => "Disagree",
  3 => "Neutral",
  4 => "Agree",
  5 => "Strongly Agree",
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Personality Test</title>

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
        window.location.href = "/User/personality-submit.php?reason=timeout";
        return;
      }

      box.textContent = formatTime(timeLeft);
      timeLeft -= 1;
      setTimeout(tickTimer, 1000);
    }

    window.addEventListener('DOMContentLoaded', tickTimer);

    function chooseResponse(val) {
      const form = document.getElementById('answer-form');
      document.getElementById('response-field').value = val;
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
            <?= e($phaseLabel) ?>
          </span>

          <span class="badge badge-soft">
            Statement <?= (int)$statementNumberHuman ?> / <?= (int)$totalStatementsHuman ?>
          </span>
        </div>

        <div class="exam-timer" aria-label="Time left">
          <span class="timer-label">Time Left</span>
          <span class="timer-value" id="timer-display">--:--</span>
        </div>
      </header>

      <div class="exam-progress">
        <div class="progress-meta">
          <span class="progress-text">Answered <?= (int)$answeredCount ?> / <?= (int)$totalMainStatements ?></span>
          <span class="progress-text"><?= (int)$progressPct ?>%</span>
        </div>
        <div class="progress-bar" role="progressbar" aria-valuenow="<?= (int)$progressPct ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-fill" style="width: <?= (int)$progressPct ?>%;"></div>
        </div>
      </div>

      <div class="exam-body">
        <div class="question-text" title="<?= e($statementText) ?>">
          <?= e($statementText) ?>
        </div>


        <form id="answer-form" action="" method="POST">
          <input type="hidden" name="statement_id" value="<?= (int)$stRow['id'] ?>">
          <input type="hidden" name="response" id="response-field" value="">
          <input type="hidden" name="action" id="action-field" value="">
        </form>

        <div class="options">
          <?php foreach ($likert as $val => $label): ?>
            <button
              type="button"
              class="opt-btn <?= ($currentAnswer === (int)$val ? 'selected' : '') ?>"
              onclick="chooseResponse(<?= (int)$val ?>)">
              <span class="opt-key"><?= (int)$val ?></span>
              <span class="opt-text"><?= e($label) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="exam-nav">
        <div class="nav-left">
          <form action="" method="POST" class="inline-form">
            <input type="hidden" name="statement_id" value="<?= (int)$stRow['id'] ?>">
            <input type="hidden" name="action" value="back">
            <button
              type="submit"
              class="btn btn-ghost <?= !$canGoBack ? 'is-disabled' : '' ?>"
              <?= !$canGoBack ? 'disabled' : '' ?>>Back</button>
          </form>
        </div>

        <div class="nav-right">
          <form action="" method="POST" class="inline-form">
            <input type="hidden" name="statement_id" value="<?= (int)$stRow['id'] ?>">
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