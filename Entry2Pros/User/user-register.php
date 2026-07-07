<?php
// /User/user-register.php
session_start();
require dirname(__DIR__) . '/Main/db.php';

$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['fullname'] ?? '');
    $user_name    = trim($_POST['username'] ?? '');
    $email        = strtolower(trim($_POST['email'] ?? ''));
    $phone_number = trim($_POST['phone'] ?? '');
    $gender_raw   = trim($_POST['gender'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm-password'] ?? '';

    $gender = strtolower($gender_raw); // 'male' | 'female'

    // Your original validation logic
    if ($full_name === '' || $user_name === '' || $email === '' || $phone_number === '' || $gender === '' || $password === '' || $confirm === '') {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        $errors[] = "Gender must be Male or Female.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }
    $digits = preg_replace('/\D+/', '', $phone_number);
    if (strlen($digits) < 8) {
        $errors[] = "Phone number looks invalid.";
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Your original INSERT logic
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, user_name, password, email, phone_number, gender, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$full_name, $user_name, $hash, $email, $phone_number, $gender]);

            $ok = true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $msg = $e->errorInfo[2] ?? $e->getMessage();
                if (stripos($msg, 'email') !== false) {
                    $errors[] = "Email already registered.";
                } elseif (stripos($msg, 'user_name') !== false || stripos($msg, 'username') !== false) {
                    $errors[] = "Username already taken.";
                } elseif (stripos($msg, 'phone') !== false) {
                    $errors[] = "Phone number already used.";
                } else {
                    $errors[] = "Duplicate value. Try a different email/username/phone.";
                }
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fresh Grad Registration | Entry2Pros</title>
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
                    <i data-lucide="home" class="nav-icon"></i> Back to Home
                </a>
            </nav>
        </header>

        <main class="hero-section">
            <div class="container hero-grid">

                <div class="hero-text-area">
                    <div class="badge">START YOUR JOURNEY</div>
                    <h1>Join <span class="highlight">Entry2Pros</span>.</h1>
                    <p>The smartest way for fresh graduates to bridge the gap between education and profession.</p>

                    <ul class="benefit-list">
                        <li><i data-lucide="check-circle-2" class="check-icon"></i> Career Path Matching</li>
                        <li><i data-lucide="check-circle-2" class="check-icon"></i> Strengths & Skill Insights</li>
                        <li><i data-lucide="check-circle-2" class="check-icon"></i> Entry-Level Jobs That Fit You</li>
                    </ul>
                </div>

                <div class="hero-action-area">
                    <div class="glass-card auth-card wide-card">
                        <div class="form-header" style="text-align: left;">
                            <h3 style="font-weight: 800; color: var(--dark-slate); font-size: 1.8rem;">Create Account</h3>
                            <p style="margin-bottom: 15px; font-size: 0.9rem;">Fill in your details to start your journey.</p>
                        </div>

                        <form action="" method="POST" class="styled-form" style="text-align: left;" novalidate>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="input-group">
                                    <label for="fullname">Full Name</label>
                                    <input type="text" id="fullname" name="fullname" placeholder="Zarul Naim" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
                                </div>
                                <div class="input-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" placeholder="zarul123" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="input-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                                <div class="input-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" placeholder="0123456789" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" required style="padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-family: 'Poppins';">
                                    <option value="">-- Select --</option>
                                    <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male' ? 'selected' : '') ?>>Male</option>
                                    <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female' ? 'selected' : '') ?>>Female</option>
                                </select>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="input-group">
                                    <label for="password">Password</label>
                                    <input type="password" id="password" name="password" placeholder="********" required>
                                </div>
                                <div class="input-group">
                                    <label for="confirm-password">Confirm Password</label>
                                    <input type="password" id="confirm-password" name="confirm-password" placeholder="********" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top: 10px; padding: 16px;">
                                Create My Account <i data-lucide="user-plus"></i>
                            </button>
                        </form>

                        <div class="form-footer" style="margin-top: 15px; text-align: center;">
                            <p style="margin-bottom: 0; font-size: 0.85rem;">Already have an account? <a href="user-login.php" class="text-link">Login here</a></p>
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

        // Error Popups
        <?php if (!empty($errors)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                html: '<?= implode("<br>", array_map("addslashes", $errors)) ?>',
                timer: 2000, // Lagi laju
                showConfirmButton: false,
                timerProgressBar: false, // Bar dibuang
                customClass: {
                    popup: 'swal-popup-custom',
                    title: 'swal-title-custom'
                }
            });
        <?php endif; ?>

        // Success Popup
        <?php if ($ok): ?>
            Swal.fire({
                icon: 'success',
                title: 'Account Created!',
                text: 'Redirecting...',
                timer: 2000,
                showConfirmButton: false,
                timerProgressBar: false, // Bar dibuang
                customClass: {
                    popup: 'swal-popup-custom',
                    title: 'swal-title-custom'
                },
                willClose: () => {
                    window.location.href = 'user-login.php';
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>