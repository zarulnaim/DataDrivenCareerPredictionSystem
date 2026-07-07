<?php
// /User/aptitude-intro.php
session_start();
require dirname(__DIR__) . '/Main/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

// Require login
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header('Location: /User/user-login.php');
    exit;
}

$userId   = (int)$_SESSION['user']['id'];
$username = $_SESSION['user']['username'] ?? '';
$name     = $_SESSION['user']['name'] ?? 'Fresh Graduate';

// If user submits the "Start Aptitude Test" button:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Find or create assessment_sessions row for this user
    $stmt = $pdo->prepare("
    SELECT id, status
    FROM assessment_sessions
    WHERE user_id = ? AND status = 'in_progress'
    ORDER BY started_at DESC
    LIMIT 1
  ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $sessionId = (int)$row['id'];
    } else {
        $ins = $pdo->prepare("
      INSERT INTO assessment_sessions (user_id, status, started_at)
      VALUES (?, 'in_progress', NOW())
    ");
        $ins->execute([$userId]);
        $sessionId = (int)$pdo->lastInsertId();
    }

    // 2. Create / resume aptitude_attempts for this session
    $aptSel = $pdo->prepare("
    SELECT id, status, started_at, deadline
    FROM aptitude_attempts
    WHERE session_id = ?
    LIMIT 1
  ");
    $aptSel->execute([$sessionId]);
    $apt = $aptSel->fetch(PDO::FETCH_ASSOC);

    if (!$apt) {
        // brand new aptitude attempt
        $aptIns = $pdo->prepare("
      INSERT INTO aptitude_attempts
        (session_id, started_at, deadline, status)
      VALUES
        (?, NOW(), DATE_ADD(NOW(), INTERVAL 45 MINUTE), 'in_progress')
    ");
        $aptIns->execute([$sessionId]);
        $aptitudeAttemptId = (int)$pdo->lastInsertId();

        // fetch fresh attempt row
        $aptSel->execute([$sessionId]);
        $apt = $aptSel->fetch(PDO::FETCH_ASSOC);
    } else {
        $aptitudeAttemptId = (int)$apt['id'];

        // if attempt exists but hasn't started yet
        if ($apt['status'] === 'not_started') {
            $aptUpd = $pdo->prepare("
        UPDATE aptitude_attempts
        SET started_at = NOW(),
            deadline   = DATE_ADD(NOW(), INTERVAL 45 MINUTE),
            status     = 'in_progress'
        WHERE id = ?
      ");
            $aptUpd->execute([$aptitudeAttemptId]);

            // reload
            $aptSel->execute([$sessionId]);
            $apt = $aptSel->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 3. Initialise in-session flow state for Aptitude
    // We only reset if we are either starting fresh or resuming this specific attempt for the first time
    $shouldInitFlow = true;
    if (
        isset($_SESSION['aptitude_attempt_id']) &&
        $_SESSION['aptitude_attempt_id'] == $aptitudeAttemptId &&
        !empty($_SESSION['aptitude_order']) &&
        is_array($_SESSION['aptitude_order'])
    ) {
        $shouldInitFlow = false;
    }

    if ($shouldInitFlow) {
        // pull ALL active aptitude questions
        $qAllStmt = $pdo->query("
      SELECT id
      FROM aptitude_questions
      WHERE is_active = 1
    ");
        $allQuestionIds = $qAllStmt->fetchAll(PDO::FETCH_COLUMN); // [12,5,9,...]

        // shuffle for this attempt
        shuffle($allQuestionIds);

        // session data model for the aptitude engine
        $_SESSION['aptitude_all_ids'] = $allQuestionIds;
        $_SESSION['aptitude_order']   = $allQuestionIds; // question_id sequence
        $_SESSION['aptitude_index']   = 0;               // pointer in current phase
        $_SESSION['aptitude_answers'] = [];              // question_id => 'A','B','C','D'
        $_SESSION['aptitude_skipped'] = [];              // question_id => true if skipped
        $_SESSION['aptitude_phase']   = 'main';          // 'main' -> normal walk, 'skipped' -> revisit skipped, 'done' -> completed
    }

    // 4. Stash important IDs for later pages
    $_SESSION['assessment_session_id'] = $sessionId;
    $_SESSION['aptitude_attempt_id']   = $aptitudeAttemptId;

    // 5. Send user into the actual test screen
    header('Location: /User/aptitude-test.php');
    exit;
}

// If GET (first load), just render the intro screen below.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aptitude Test — Instructions | Entry2Pros</title>
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
                    <a href="/Main/logout.php" class="logout-minimal">
                        <span>Logout</span>
                        <i data-lucide="power"></i>
                    </a>
                </div>
            </nav>
        </header>

        <main class="dashboard-content">
            <div class="container">
                <div class="test-layout-grid">

                    <div class="test-content-main">
                        <div class="test-header">
                            <h1>Aptitude <span class="highlight">Test</span></h1>
                            <p class="hero-sub">This section measures problem-solving style, reasoning, and basic numerics. Answer honestly at your own pace.</p>
                        </div>

                        <div class="test-steps-stack">
                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">1</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Question Format</h4>
                                        <span class="badge-time">MCQ</span>
                                    </div>
                                    <p>Multiple-choice questions. Only one correct answer per question.</p>
                                </div>
                            </div>

                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">2</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Skip & Back</h4>
                                        <span class="badge-time">FLEXIBLE</span>
                                    </div>
                                    <p>You can SKIP unsure questions. Revisit them after the main round.</p>
                                </div>
                            </div>

                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">3</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Finish & Submit</h4>
                                        <span class="badge-time">1 CLICK</span>
                                    </div>
                                    <p>Once finished, submit for grading and move to Personality Test.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="test-sidebar">
                        <div class="instruction-card">
                            <div class="ins-header">
                                <i data-lucide="alert-circle" style="color: #f59e0b;"></i>
                                <span>Important Guidelines</span>
                            </div>
                            <ul class="ins-list">
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Timer: 45 Minutes total.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Starts once you click Begin.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Refreshing won't reset timer.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Answers lock upon submission.</span></li>
                            </ul>

                            <form method="POST" action="">
                                <button type="submit" class="btn btn-primary btn-large w-full" style="cursor: pointer; border: none; font-family: inherit;">
                                    Start Aptitude Test
                                    <i data-lucide="play-circle"></i>
                                </button>
                            </form>
                        </div>
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