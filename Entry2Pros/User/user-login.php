<?php
// /User/user-login.php
session_start();
require dirname(__DIR__) . '/Main/db.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['username'] ?? ''); // username OR email
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = "Please fill in both fields.";
    } else {
        // Keeping your original logic exactly
        $stmt = $pdo->prepare(
            "SELECT id, full_name, user_name, email, password
       FROM users
       WHERE user_name = ? OR email = ?
       LIMIT 1"
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Keeping your original session structure
            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'name'     => $user['full_name'],
                'username' => $user['user_name'],
            ];
            header('Location: user-dashboard.php');
            exit;
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fresh Grad Login | Entry2Pros</title>
    <link rel="stylesheet" href="../Main/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="page-wrapper">
        <header class="main-header">
            <div class="brand-wrapper">
                <div class="brand-name-only">
                    Entry<span class="text-green">2</span>Pros
                </div>
            </div>
            <nav class="top-nav">
                <a href="/Main/index.php" class="admin-link">
                    <i data-lucide="home" class="nav-icon"></i> Back to Home
                </a>
            </nav>
        </header>

        <main class="hero-section">
            <div class="container hero-grid">

                <div class="hero-text-area">
                    <div class="badge">CANDIDATE PORTAL</div>
                    <h1>Take the <span class="highlight">Next Step</span>.</h1>
                    <p>Login to explore career paths tailored just for your personality and skills.</p>

                    <ul class="benefit-list">
                        <li><i data-lucide="check-circle-2" class="check-icon"></i> Career Path Matching</li>
                        <li><i data-lucide="check-circle-2" class="check-icon"></i> Strengths & Skill Insights</li>
                        <li><i data-lucide="check-circle-2" class="check-icon"></i> Entry-Level Jobs That Fit You</li>
                    </ul>
                </div>

                <div class="hero-action-area">
                    <div class="glass-card auth-card">
                        <div class="form-header" style="text-align: left;">
                            <h3 style="font-weight: 800; color: var(--dark-slate); font-size: 2rem;">Welcome Back</h3>
                            <p style="margin-bottom: 20px;">Please enter your details to login.</p>
                        </div>

                        <form action="" method="POST" class="styled-form" style="text-align: left;">
                            <div class="input-group">
                                <label for="username">Username or Email</label>
                                <input type="text" id="username" name="username" placeholder="Enter your username" required
                                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            </div>

                            <div class="input-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" placeholder="********" required />
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                                Login to Account
                                <i data-lucide="move-right" class="btn-icon"></i>
                            </button>
                        </form>

                        <div class="divider"><span>OR</span></div>

                        <div class="form-footer">
                            <p style="max-width: 100%; margin-bottom: 0;">Don't have an account? <a href="user-register.php" class="text-link">Register here</a></p>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <footer class="main-footer">
            <p>&copy; 2025 Entry2Profession. Built for the next generation of professionals.</p>
        </footer>
    </div>

    <script>
        lucide.createIcons();

        <?php if (!empty($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?= addslashes($error) ?>',
                timer: 2000, // Masa dipendekkan
                showConfirmButton: false,
                timerProgressBar: false, // Bar dibuang
                customClass: {
                    popup: 'swal-popup-custom',
                    title: 'swal-title-custom'
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>