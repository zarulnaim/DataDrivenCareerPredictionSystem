<?php
// /Admin/admin-login.php
session_start();
require dirname(__DIR__) . '/Main/db.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['admin_username'] ?? '');
    $password = $_POST['admin_password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please fill in both fields.";
    } else {
        // Keeping your original logic: searching in 'admins' table
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Keeping your original session structure
            $_SESSION['user'] = [
                'id'       => $admin['id'],
                'username' => $admin['username'],
                'role'     => 'admin'
            ];
            header('Location: admin-dashboard.php');
            exit;
        } else {
            $error = "Invalid admin username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Entry2Pros</title>
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
                <a href="../Main/index.php" class="admin-link">
                    <i data-lucide="home"></i> Back to Home
                </a>
            </nav>
        </header>

        <main class="hero-section">
            <div class="container hero-grid" style="grid-template-columns: 1fr 1fr;">

                <div class="hero-text-area">
                    <div class="badge" style="background: rgba(15, 23, 42, 0.1); color: var(--dark-slate);">ADMINISTRATIVE PORTAL</div>
                    <h1>Secure <span class="highlight" style="color: var(--dark-slate);">Console</span>.</h1>
                    <p>Access the administration portal to manage question banks, review content, and maintain job listings.</p>

                    <ul class="benefit-list">
                        <li><i data-lucide="shield-check" class="check-icon" style="color: var(--dark-slate);"></i> Assessment Content Management</li>
                        <li><i data-lucide="shield-check" class="check-icon" style="color: var(--dark-slate);"></i> Content Review & Moderation</li>
                        <li><i data-lucide="shield-check" class="check-icon" style="color: var(--dark-slate);"></i> Job Listings Administration</li>
                    </ul>
                </div>

                <div class="hero-action-area">
                    <div class="glass-card auth-card" style="border-top: 5px solid var(--dark-slate);">
                        <div class="form-header" style="text-align: left;">
                            <h3 style="font-weight: 800; color: var(--dark-slate); font-size: 1.8rem;">Admin Login</h3>
                            <p style="margin-bottom: 20px; font-size: 0.9rem;">Please enter your credentials to login.</p>
                        </div>

                        <form action="" method="POST" class="styled-form" style="text-align: left;">
                            <div class="input-group">
                                <label for="admin_username">Admin Username</label>
                                <input type="text" name="admin_username" id="admin_username" placeholder="Enter admin ID"
                                    value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="admin_password">Password</label>
                                <input type="password" name="admin_password" id="admin_password" placeholder="********" required>
                            </div>

                            <button type="submit" class="btn" style="background: var(--dark-slate); margin-top: 10px; color: white; padding: 14px;">
                                Log In to Console <i data-lucide="log-in" style="width: 18px;"></i>
                            </button>
                        </form>
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