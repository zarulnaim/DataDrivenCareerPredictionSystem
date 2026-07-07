<?php
// /User/academic-intro.php
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

// ✅ Backend Logic (Handling the Academic Attempt)
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

    $p = $pdo->prepare("SELECT id, status FROM academic_attempts WHERE session_id=? LIMIT 1");
    $p->execute([$sessionId]);
    $att = $p->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        $pdo->prepare("INSERT INTO academic_attempts (session_id, started_at, deadline, status) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'in_progress')")->execute([$sessionId]);
        $academicAttemptId = (int)$pdo->lastInsertId();
    } else {
        $academicAttemptId = (int)$att['id'];
        if ($att['status'] === 'not_started') {
            $pdo->prepare("UPDATE academic_attempts SET started_at=NOW(), deadline = DATE_ADD(NOW(), INTERVAL 5 MINUTE), status = 'in_progress' WHERE id=?")->execute([$academicAttemptId]);
        }
    }

    $_SESSION['assessment_session_id'] = $sessionId;
    $_SESSION['academic_attempt_id']   = $academicAttemptId;

    header('Location: /User/academic-test.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Profile — Instructions | Entry2Pros</title>
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
                            <h1>Academic <span class="highlight">Profile</span></h1>
                            <p class="hero-sub">This section collects your latest academic data to fine-tune your career matching. Use accurate details for the best results.</p>
                        </div>

                        <div class="test-steps-stack">
                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">1</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Academic Info</h4>
                                        <span class="badge-time">QUICK</span>
                                    </div>
                                    <p>Qualification, field of study, institution, and graduation year.</p>
                                </div>
                            </div>

                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">2</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>CGPA</h4>
                                        <span class="badge-time">PRECISE</span>
                                    </div>
                                    <p>Enter your CGPA. Accuracy is key for job matching.</p>
                                </div>
                            </div>

                            <div class="step-card">
                                <div class="icon-box-fixed">
                                    <span style="font-weight:800">3</span>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Finalize</h4>
                                        <span class="badge-time">RESULT</span>
                                    </div>
                                    <p>After this, you'll proceed to view your full career insights.</p>
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
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Timer: 5 Minutes total.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Use official transcripts for CGPA.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Stable internet is required.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Final submission locks data.</span></li>
                            </ul>

                            <form method="POST" action="">
                                <button type="submit" class="btn btn-primary btn-large w-full" style="cursor: pointer; border: none; font-family: inherit;">
                                    Start Academic Profile
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