<?php
// /User/personality-submit.php
session_start();
require dirname(__DIR__) . '/Main/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header('Location: /User/user-login.php');
    exit;
}

$userId   = (int)$_SESSION['user']['id'];
$username = $_SESSION['user']['username'] ?? ($_SESSION['user']['user_name'] ?? 'user');
$reason   = $_GET['reason'] ?? '';
$done      = isset($_GET['done']);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personality Submitted | Entry2Pros</title>
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
                        <?php if ($reason === 'timeout'): ?>
                            <h1>Section <span class="highlight">Expired</span></h1>
                            <p class="hero-sub" style="margin-left: auto; margin-right: auto; max-width: 450px;">
                                Your personality test was marked as expired due to a timeout. Your progress has been saved.
                            </p>
                        <?php else: ?>
                            <h1>Personality <span class="highlight">Finished</span></h1>
                            <p class="hero-sub" style="margin-left: auto; margin-right: auto; max-width: 450px;">
                                <?php if ($done): ?>
                                    Great job! Your responses were saved and scored to build your professional profile.
                                <?php else: ?>
                                    Your attempt is closed. You can now proceed to the final section of the assessment.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="ins-list" style="margin-top: 30px; text-align: left; display: inline-block; width: 100%; max-width: 400px;">
                        <li style="justify-content: center;"><i data-lucide="shield-check" class="tick-green"></i> <span>Traits analyzed successfully</span></li>
                        <li style="justify-content: center;"><i data-lucide="database" class="tick-green"></i> <span>Data synced with profile</span></li>
                    </div>
                    <div style="margin-top: 40px; width: 100%;">
                        <a href="/User/academic-intro.php" class="btn btn-primary btn-large w-full" style="text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 10px;">
                            Continue to Academic
                            <i data-lucide="arrow-right-circle"></i>
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