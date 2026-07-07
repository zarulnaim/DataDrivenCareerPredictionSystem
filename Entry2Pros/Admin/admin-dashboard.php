<?php
// /Admin/admin-dashboard.php
session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}
$username = $_SESSION['user']['username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Entry2Pros</title>
    <link rel="stylesheet" href="../Main/index.css">
    <link rel="stylesheet" href="../Main/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="dashboard-body">

    <aside class="sidebar">
        <div class="sidebar-brand">
            Entry<span class="text-green">2</span>Pros
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Main Console</div>
            <a href="admin-dashboard.php" class="nav-link active">
                <i data-lucide="layout-grid"></i> Overview
            </a>

            <div class="nav-label">Assessment Systems</div>
            <a href="aptitude-question.php" class="nav-link">
                <i data-lucide="brain"></i> Aptitude Bank
            </a>
            <a href="personality-question.php" class="nav-link">
                <i data-lucide="fingerprint"></i> Personality Bank
            </a>

            <div class="nav-label">Career Directory</div>
            <a href="job-listing.php" class="nav-link">
                <i data-lucide="briefcase"></i> Job Postings
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="/Main/logout.php" class="logout-action">
                <div class="logout-icon-box">
                    <i data-lucide="log-out"></i>
                </div>
                <span>Terminate Session</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-left">
                <h1>System <span class="text-green">Overview</span></h1>
                <p>Monitor and configure core assessment modules.</p>
            </div>

            <div class="header-right">
                <div class="admin-profile-card">
                    <div class="avatar-circle">
                        <i data-lucide="shield-check"></i>
                    </div>
                    <div class="profile-details">
                        <span class="p-role">Logged in as</span>
                        <span class="p-name"><?= htmlspecialchars($username) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-content" style="padding-top: 40px; margin-top: 10px;">

            <div class="action-grid">
                <a href="aptitude-question.php" class="action-card">
                    <div class="card-body">
                        <div class="icon-circle green-bg"><i data-lucide="binary"></i></div>
                        <h3>Aptitude Management</h3>
                        <p>Configure assessment logic, difficulty parameters, and item activation.</p>
                        <div class="card-tags">
                            <span>Logic Scaling</span>
                            <span>Attributes</span>
                        </div>
                    </div>
                </a>

                <a href="personality-question.php" class="action-card">
                    <div class="card-body">
                        <div class="icon-circle blue-bg"><i data-lucide="fingerprint"></i></div>
                        <h3>Personality Engine</h3>
                        <p>Manage trait statements with advanced reverse-scoring configuration.</p>
                        <div class="card-tags">
                            <span>Psychometric</span>
                            <span>Reverse Logic</span>
                        </div>
                    </div>
                </a>

                <a href="job-listing.php" class="action-card">
                    <div class="card-body">
                        <div class="icon-circle gold-bg"><i data-lucide="timer"></i></div>
                        <h3>Career Directory</h3>
                        <p>Administer job opportunities with automated expiry synchronization.</p>
                        <div class="card-tags">
                            <span>Auto-Timer</span>
                            <span>Live Status</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <footer class="admin-footer-clean">
            <p>&copy; 2025 Entry2Profession. Internal Admin Control Panel.</p>
        </footer>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>