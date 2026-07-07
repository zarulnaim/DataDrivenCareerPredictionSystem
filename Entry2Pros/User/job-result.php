<?php
session_start();
require dirname(__DIR__) . '/Main/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$username = $_SESSION['user']['username'] ?? 'User';

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalize_phone_for_whatsapp(?string $phone): string
{
    if (!$phone) return '';
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) > 0 && $digits[0] === '0') {
        $digits = '60' . substr($digits, 1);
    }
    return $digits;
}

function gmail_compose_link(string $to, string $subject = '', string $body = ''): string
{
    $q = ['view' => 'cm', 'fs' => '1', 'to' => $to];
    if ($subject !== '') $q['su'] = $subject;
    if ($body !== '')    $q['body'] = $body;
    return 'https://mail.google.com/mail/?' . http_build_query($q);
}

function whatsapp_link(string $phoneDigits, string $text = ''): string
{
    $q = ['phone' => $phoneDigits];
    if ($text !== '') $q['text'] = $text;
    return 'https://api.whatsapp.com/send?' . http_build_query($q);
}

function time_left_badge(string $validUntil): array
{
    $now = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    $vu  = new DateTime($validUntil, new DateTimeZone('Asia/Kuala_Lumpur'));

    if ($vu <= $now) return ['label' => 'Expired', 'expired' => true];

    $diff = $now->diff($vu);
    $days = (int)$diff->days;

    if ($days >= 1) return ['label' => $days . ' days left', 'expired' => false];

    $hours = (int)$diff->h;
    $mins  = (int)$diff->i;

    if ($hours >= 1) return ['label' => $hours . ' hours left', 'expired' => false];
    return ['label' => max(1, $mins) . ' mins left', 'expired' => false];
}

$top3Careers = $_SESSION['top3_careers'] ?? [];
$top3Careers = array_values(array_filter(array_map('trim', (array)$top3Careers)));
$top3Careers = array_slice($top3Careers, 0, 3);

// --- Fallback UI if Session Missing ---
if (count($top3Careers) === 0) {
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Matching Jobs | Entry2Pros</title>
        <link rel="stylesheet" href="/Main/index.css">
        <link rel="stylesheet" href="/Main/user.css">
        <link rel="stylesheet" href="/Main/result.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
    </head>

    <body>
        <div class="page-wrapper dashboard-wrapper">
            <header class="main-header">
                <div class="brand-wrapper">
                    <div class="brand-name-only">Entry<span class="text-green">2</span>Pros</div>
                </div>
            </header>
            <main class="dashboard-content result-content-override">
                <div class="container">
                    <div class="result-card top-career-card">
                        <div class="card-header">
                            <div class="icon-box soft-red"><i data-lucide="alert-circle"></i></div>
                            <h3>Session Expired</h3>
                        </div>
                        <div class="career-main-content">
                            <p class="text-subtle">Top 3 careers not found. Please return to your results.</p>
                            <br>
                            <a href="career-result.php" class="btn btn-primary">Back to Career Result</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <script>
            lucide.createIcons();
        </script>
    </body>

    </html>
<?php
    exit;
}

$selectedCareer = isset($_GET['career']) ? trim((string)$_GET['career']) : $top3Careers[0];
if (!in_array($selectedCareer, $top3Careers, true)) {
    $selectedCareer = $top3Careers[0];
}

// Auto-deactivate logic
try {
    $pdo->exec("UPDATE job_listings SET is_active = 0 WHERE is_active = 1 AND valid_until < NOW()");
} catch (Exception $e) {
}

// Fetch jobs logic
$jobs = [];
try {
    $st = $pdo->prepare("
    SELECT
      id, career_name, title, company_name, location,
      description, requirements,
      contact_email, contact_phone,
      apply_url, valid_until, is_active,
      created_at
    FROM job_listings
    WHERE career_name = :career
      AND is_active = 1
      AND valid_until >= NOW()
    ORDER BY created_at DESC, id DESC
  ");
    $st->execute([':career' => $selectedCareer]);
    $raw = $st->fetchAll(PDO::FETCH_ASSOC);

    $byId = [];
    foreach ($raw as $r) {
        $jid = (int)($r['id'] ?? 0);
        if ($jid > 0) $byId[$jid] = $r;
    }
    $jobs = array_values($byId);
} catch (Exception $e) {
    $jobs = [];
}

$jobsFound = count($jobs);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matching Jobs | Entry2Profession</title>

    <link rel="stylesheet" href="/Main/index.css">
    <link rel="stylesheet" href="/Main/user.css">
    <link rel="stylesheet" href="/Main/result.css">

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

        <main class="dashboard-content result-content-override">
            <div class="container">

                <div class="job-header-wrapper">
                    <div class="result-header" style="text-align: center; margin-bottom: 30px; width: 100%;">
                        <h1 style="margin: 0 auto;">Matching <span class="highlight">Job Listings</span></h1>
                        <p style="margin: 5px auto 0 auto;">Explore opportunities tailored to your top 3 career.</p>
                    </div>
                    <a href="career-result.php" class="btn-back-ghost" title="Back to Careers">
                        <i data-lucide="arrow-left"></i>
                    </a>
                </div>

                <div class="result-card filter-container" style="margin-bottom: 25px; border: none !important; border-radius: 12px; padding: 15px 25px; background: white; transition: none !important; transform: none !important; box-shadow: 0 2px 10px rgba(0,0,0,0.05) !important; animation: none !important;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">

                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="background: #f0fdf4; padding: 8px; border-radius: 10px;">
                                <i data-lucide="filter" style="width: 18px; color: #10b981;"></i>
                            </div>
                            <h3 style="font-size: 1rem; margin: 0; color: #1e293b; font-weight: 700;">Filter by Career</h3>
                        </div>

                        <form method="get" style="display: flex; align-items: center; gap: 12px; flex-grow: 1; justify-content: flex-end;">
                            <label for="career" style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Select:</label>
                            <select id="career" name="career" onchange="this.form.submit()" style="padding: 8px 16px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-size: 0.9rem; color: #1e293b; background: #f8fafc; cursor: pointer; min-width: 220px; transition: border-color 0.2s;">
                                <?php foreach ($top3Careers as $c): ?>
                                    <option value="<?php echo h($c); ?>" <?php echo ($c === $selectedCareer) ? 'selected' : ''; ?>>
                                        <?php echo h($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div style="background: #10b981; color: white; padding: 5px 15px; border-radius: 50px; font-size: 0.8rem; font-weight: 600;">
                                <?php echo (int)$jobsFound; ?> Jobs Found
                            </div>
                        </form>
                    </div>
                </div>

                <div class="job-list-container">

                    <?php if ($jobsFound === 0): ?>
                        <div class="result-card no-jobs-card">
                            <div class="icon-box soft-red no-jobs-icon"><i data-lucide="inbox"></i></div>
                            <h3>No Active Listings</h3>
                            <p class="text-subtle no-jobs-text">
                                Currently, there are no open positions for <strong><?php echo h($selectedCareer); ?></strong>. Please check back later or explore your other career matches.
                            </p>
                        </div>
                    <?php else: ?>

                        <?php foreach ($jobs as $job): ?>
                            <?php
                            $email = trim((string)($job['contact_email'] ?? ''));
                            $phone = normalize_phone_for_whatsapp($job['contact_phone'] ?? '');
                            $applyUrl = trim((string)($job['apply_url'] ?? ''));

                            $title = (string)($job['title'] ?? '');
                            $company = (string)($job['company_name'] ?? '');
                            $location = (string)($job['location'] ?? '');
                            $desc = (string)($job['description'] ?? '');
                            $req  = (string)($job['requirements'] ?? '');
                            $validUntil = (string)($job['valid_until'] ?? '');
                            $postedAt = (string)($job['created_at'] ?? '');

                            $left = $validUntil ? time_left_badge($validUntil) : ['label' => 'N/A', 'expired' => false];

                            // Link Logic
                            $gmailHref = ($email !== '')
                                ? gmail_compose_link($email, "Application for " . $title, "Hi HR Team,\n\nI would like to apply for: " . $title . " at " . $company . ".")
                                : '#';
                            $waHref = ($phone !== '')
                                ? whatsapp_link($phone, "Hi, I would like to apply for: " . $title . " (" . $company . ").")
                                : '#';
                            ?>

                            <div class="result-card job-card">
                                <div class="job-card-header">
                                    <div class="job-header-left" style="gap: 12px;">
                                        <div class="job-icon-box soft-blue" style="width: 40px; height: 40px;">
                                            <i data-lucide="briefcase" style="width: 18px;"></i>
                                        </div>
                                        <div style="text-align: left;">
                                            <h3 class="job-title" style="font-size: 1.4rem; font-weight: 700; margin: 0; color: #1e293b; line-height: 1.2;">
                                                <?php echo h($title); ?>
                                            </h3>
                                            <span class="job-company" style="font-size: 1rem; font-weight: 500; color: #64748b;">
                                                <?php echo h($company); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="<?php echo $left['expired'] ? 'is-expired-badge' : 'badge-confidence'; ?>">
                                        <i data-lucide="clock" style="width: 14px;"></i>
                                        <?php echo h($left['label']); ?>
                                    </div>
                                </div>

                                <div class="job-body">
                                    <div class="job-meta-row" style="display: flex; gap: 10px; margin-bottom: 15px;">
                                        <?php if ($location !== ''): ?>
                                            <div class="meta-pill" style="display: flex; align-items: center; gap: 6px; background: #eff6ff; border: 1px solid #bfdbfe; padding: 5px 14px; border-radius: 50px; font-size: 0.8rem; color: #1e40af; font-weight: 500;">
                                                <i data-lucide="map-pin" style="width: 14px; color: #3b82f6;"></i>
                                                <?php echo h($location); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($postedAt !== ''): ?>
                                            <div class="meta-pill" style="display: flex; align-items: center; gap: 6px; background: #f5f3ff; border: 1px solid #ddd6fe; padding: 5px 14px; border-radius: 50px; font-size: 0.8rem; color: #5b21b6; font-weight: 500;">
                                                <i data-lucide="calendar" style="width: 14px; color: #8b5cf6;"></i>
                                                Posted: <?php echo h(date('d M Y', strtotime($postedAt))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <hr class="job-divider">

                                    <div class="job-details-full" style="width: 100% !important; display: block !important; text-align: left; box-sizing: border-box;">
                                        <div style="margin-bottom: 12px; width: 100%;">
                                            <h4 style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 4px; letter-spacing: 0.5px; font-weight: 700;">DESCRIPTION</h4>
                                            <div style="font-size: 0.85rem; line-height: 1.6; color: #475569; width: 100%; display: block;">
                                                <?php echo nl2br(h($desc)); ?>
                                            </div>
                                        </div>

                                        <?php if (!empty($req)): ?>
                                            <div style="margin-bottom: 5px; width: 100%;">
                                                <h4 style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 4px; letter-spacing: 0.5px; font-weight: 700;">REQUIREMENTS</h4>
                                                <div style="font-size: 0.85rem; line-height: 1.6; color: #475569; width: 100%; display: block;">
                                                    <?php
                                                    $cleanReq = preg_replace('/^Requirements:\s*/i', '', $req);
                                                    echo nl2br(h($cleanReq));
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="job-card-actions">
                                        <a class="btn-action btn-gmail <?php echo ($email === '') ? 'btn-disabled' : ''; ?>"
                                            href="<?php echo h($gmailHref); ?>" target="_blank">
                                            <i data-lucide="mail"></i> Email
                                        </a>

                                        <a class="btn-action btn-wa <?php echo ($phone === '') ? 'btn-disabled' : ''; ?>"
                                            href="<?php echo h($waHref); ?>" target="_blank">
                                            <i data-lucide="message-circle"></i> WhatsApp
                                        </a>

                                        <a class="btn-action btn-apply-job <?php echo ($applyUrl === '') ? 'btn-disabled' : ''; ?>"
                                            href="<?php echo h($applyUrl ?: '#'); ?>" target="_blank">
                                            Apply Now <i data-lucide="external-link" style="width:16px;"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
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