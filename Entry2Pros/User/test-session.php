<?php
session_start();
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header('Location: /User/user-login.php');
    exit;
}
$name     = $_SESSION['user']['name']     ?? 'Fresh Graduate';
$username = $_SESSION['user']['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment | Entry2Pros</title>
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
                            <h1>Ready to level up, <span class="highlight"><?= htmlspecialchars($name) ?></span>?</h1>
                            <p class="hero-sub">Discover your career DNA. Complete these 3 steps to unlock personalized job matching.</p>
                        </div>

                        <div class="test-steps-stack">
                            <div class="step-card active">
                                <div class="icon-box-fixed">
                                    <i data-lucide="brain"></i>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Aptitude Test</h4>
                                        <span class="badge-time">45 MIN</span>
                                    </div>
                                    <p>Logic, mathematics, and problem-solving patterns.</p>
                                </div>
                            </div>

                            <div class="step-card active">
                                <div class="icon-box-fixed">
                                    <i data-lucide="smile"></i>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Personality Test</h4>
                                        <span class="badge-time">10 MIN</span>
                                    </div>
                                    <p>Behavioral traits and work-style preferences.</p>
                                </div>
                            </div>

                            <div class="step-card active">
                                <div class="icon-box-fixed">
                                    <i data-lucide="graduation-cap"></i>
                                </div>
                                <div class="step-info-text">
                                    <div class="step-meta">
                                        <h4>Academic Profile</h4>
                                        <span class="badge-time">5 MIN</span>
                                    </div>
                                    <p>Your education background and CGPA details.</p>
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
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Sit in a quiet environment.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Timer cannot be paused.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Stable internet is required.</span></li>
                                <li><i data-lucide="check-circle-2" class="tick-green"></i> <span>Total time: ~60 minutes.</span></li>
                            </ul>

                            <a href="/User/aptitude-intro.php" class="btn btn-primary btn-large w-full" style="text-decoration: none; border: none; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: 10px;">
                                Begin Assessment
                                <i data-lucide="play-circle"></i>
                            </a>
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