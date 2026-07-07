<?php
// /User/user-dashboard.php
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
    <title>Dashboard | Entry2Pros</title>
    <link rel="stylesheet" href="/Main/index.css">
    <link rel="stylesheet" href="/Main/user.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>

    <div class="page-wrapper dashboard-wrapper">

        <header class="main-header">
            <div class="brand-wrapper">
                <div class="brand-name-only">
                    Entry<span class="text-green">2</span>Pros
                </div>
            </div>

            <nav class="user-nav">
                <div class="profile-pill-new">
                    <div class="user-brand">
                        <div class="avatar-mini"><i data-lucide="user"></i></div>
                        <span class="user-handle">@<?= htmlspecialchars($username) ?></span>
                    </div>
                    <a href="/Main/logout.php" class="logout-minimal" title="Logout">
                        <span>Logout</span>
                        <i data-lucide="power"></i>
                    </a>
                </div>
            </nav>
        </header>

        <main class="dashboard-content">
            <div class="container">

                <div class="dashboard-hero">
                    <h1>Hi, <span class="highlight"><?= htmlspecialchars($name) ?></span>!</h1>
                    <p class="hero-sub">Your professional journey starts here. Take the assessment to unlock your career DNA and find jobs that actually fit your profile.</p>

                    <a href="/User/test-session.php" class="btn btn-primary btn-large">
                        Start Assessment
                        <i data-lucide="arrow-right" class="btn-icon"></i>
                    </a>
                </div>

                <div class="roadmap-section">
                    <h3 class="section-title">The Road to Your Career</h3>

                    <div class="steps-grid">
                        <div class="step-card active">
                            <div class="step-badge">1</div>
                            <div class="step-icon">
                                <i data-lucide="clipboard-list"></i>
                            </div>
                            <h4>Assessment</h4>
                            <p>Take a test that will reflect your interests and skills.</p>
                        </div>

                        <div class="step-card">
                            <div class="step-badge">2</div>
                            <div class="step-icon">
                                <i data-lucide="bar-chart-3"></i>
                            </div>
                            <h4>Test Results</h4>
                            <p>Get a detailed profile of your strengths.</p>
                        </div>

                        <div class="step-card">
                            <div class="step-badge">3</div>
                            <div class="step-icon">
                                <i data-lucide="lightbulb"></i>
                            </div>
                            <h4>Top 3 Careers</h4>
                            <p>Personalized guidance for your future.</p>
                        </div>

                        <div class="step-card">
                            <div class="step-badge">4</div>
                            <div class="step-icon">
                                <i data-lucide="briefcase"></i>
                            </div>
                            <h4>Job Matching</h4>
                            <p>Find companies looking for people like you.</p>
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