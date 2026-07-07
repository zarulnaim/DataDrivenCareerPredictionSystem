<?php
// /User/personality-intro.php
session_start();
require dirname(__DIR__) . '/Main/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// ✅ Require login
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header('Location: /User/user-login.php');
    exit;
}

$userId   = (int)$_SESSION['user']['id'];
$username = $_SESSION['user']['username'] ?? '';

// ✅ Backend Logic (Unchanged as requested)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = $pdo->prepare("SELECT id FROM assessment_sessions WHERE user_id=? AND status='in_progress' ORDER BY started_at DESC LIMIT 1");
    $s->execute([$userId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $sessionId = (int)$row['id'];
    } else {
        $pdo->prepare("INSERT INTO assessment_sessions (user_id, status, started_at) VALUES (?, 'in_progress', NOW())")->execute([$userId]);
        $sessionId = (int)$pdo->lastInsertId();
    }

    $p = $pdo->prepare("SELECT id, status FROM personality_attempts WHERE session_id=? LIMIT 1");
    $p->execute([$sessionId]);
    $att = $p->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        $pdo->prepare("INSERT INTO personality_attempts (session_id, started_at, deadline, status) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'in_progress')")->execute([$sessionId]);
        $personalityAttemptId = (int)$pdo->lastInsertId();
    } else {
        $personalityAttemptId = (int)$att['id'];
        if ($att['status'] === 'not_started') {
            $pdo->prepare("UPDATE personality_attempts SET started_at=NOW(), deadline = DATE_ADD(NOW(), INTERVAL 10 MINUTE), status = 'in_progress' WHERE id=?")->execute([$personalityAttemptId]);
        }
    }

    if (empty($_SESSION['personality_attempt_id']) || $_SESSION['personality_attempt_id'] != $personalityAttemptId) {
        $ids = $pdo->query("SELECT id FROM personality_questions WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        shuffle($ids);
        $_SESSION['personality_order']   = $ids;
        $_SESSION['personality_index']   = 0;
        $_SESSION['personality_phase']   = 'main';
        $_SESSION['personality_answers'] = [];
        $_SESSION['personality_skipped'] = [];
    }

    $_SESSION['assessment_session_id']  = $sessionId;
    $_SESSION['personality_attempt_id'] = $personalityAttemptId;

    header('Location: /User/personality-test.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personality Test — Instructions | Entry2Pros</title>
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
                            <h1>Personality <span class="highlight">Test</span></h1>
                            <p class="hero-sub">This section measures your traits, communication style, and emotional tendencies. Answer honestly — there are no right or wrong answers.</p>
                        </div>

                        <div class="test-steps-stack">
                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">1</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Statements</h4>
                                        <span class="badge-time">AGREE / DISAGREE</span>
                                    </div>
                                    <p>Choose the option that best reflects you. Be yourself!</p>
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
                                    <p>You can skip questions and revisit them later if you're unsure.</p>
                                </div>
                            </div>

                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">3</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Continue</h4>
                                        <span class="badge-time">NEXT STAGE</span>
                                    </div>
                                    <p>After completing this, you will proceed to your Academic Profile.</p>
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
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Timer: 10 Minutes total.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Be honest, no trick questions.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Stable internet is required.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Cannot change after submission.</span></li>
                            </ul>

                            <form method="POST" action="">
                                <button type="submit" class="btn btn-primary btn-large w-full" style="cursor: pointer; border: none; font-family: inherit;">
                                    Start Personality Test
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