<?php
session_start();
require dirname(__DIR__) . '/Main/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header('Location: /User/user-login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
// Added this line to match Dashboard header logic
$name   = $_SESSION['user']['name']     ?? 'Fresh Graduate';
$username = $_SESSION['user']['username'] ?? 'user';

$sessionId = $_SESSION['assessment_session_id'] ?? null;
if (!$sessionId) {
    $st = $pdo->prepare("SELECT id FROM assessment_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) $sessionId = (int)$row['id'];
}
if (!$sessionId) {
    header('Location: /User/test-session.php');
    exit;
}

/** -------- Fetch latest attempts safely -------- */
$stApt = $pdo->prepare("SELECT * FROM aptitude_attempts WHERE session_id = ? ORDER BY id DESC LIMIT 1");
$stApt->execute([$sessionId]);
$apt = $stApt->fetch(PDO::FETCH_ASSOC) ?: [];

$stPer = $pdo->prepare("SELECT * FROM personality_attempts WHERE session_id = ? ORDER BY id DESC LIMIT 1");
$stPer->execute([$sessionId]);
$per = $stPer->fetch(PDO::FETCH_ASSOC) ?: [];

$stAca = $pdo->prepare("SELECT * FROM academic_attempts WHERE session_id = ? ORDER BY id DESC LIMIT 1");
$stAca->execute([$sessionId]);
$aca = $stAca->fetch(PDO::FETCH_ASSOC) ?: null;

$acaAns = null;
if ($aca && isset($aca['id'])) {
    $stAcaAns = $pdo->prepare("SELECT * FROM academic_answers WHERE attempt_id = ? ORDER BY id DESC LIMIT 1");
    $stAcaAns->execute([(int)$aca['id']]);
    $acaAns = $stAcaAns->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** -------- Helpers -------- */
function pct10($x)
{
    if ($x === null) return 0;
    $p = (int) round(((float)$x / 10) * 100);
    return max(0, min(100, $p));
}

function build_in_placeholders(array $items): string
{
    return implode(',', array_fill(0, count($items), '?'));
}

/**
 * Bar renderer with DB tooltip (?)
 * HTML structure kept simple, styling moved to CSS
 */
function bar($name, $val, $color, $tip = '')
{
    $val = max(0, min(100, (int)$val));

    $nameEsc = htmlspecialchars((string)$name, ENT_QUOTES);
    $tipEsc  = htmlspecialchars((string)$tip, ENT_QUOTES);

    $help = '';
    if ($tipEsc !== '') {
        $help = "<button type='button' class='help-dot' title='{$tipEsc}' aria-label='Explain {$nameEsc}'>?</button>";
    }

    // Note: formatting is controlled via result.css now
    return "
  <div class='bar-block'>
    <div class='bar-top'>
      <span class='bar-label'>{$nameEsc}{$help}</span>
      <span class='bar-percent'>{$val}%</span>
    </div>
    <div class='bar-bg'><div class='bar-fill' style='width:{$val}%;background:{$color};'></div></div>
  </div>";
}

/** -------- Convert scores to % -------- */
$O  = pct10($per['score_openness'] ?? null);
$C  = pct10($per['score_conscientiousness'] ?? null);
$E  = pct10($per['score_extraversion'] ?? null);
$A  = pct10($per['score_agreeableness'] ?? null);
$N  = pct10($per['score_neuroticism'] ?? null);

$LG = pct10($apt['score_logical'] ?? null);
$NM = pct10($apt['score_numerical'] ?? null);
$SP = pct10($apt['score_spatial'] ?? null);
$PC = pct10($apt['score_perceptual'] ?? null);
$VB = pct10($apt['score_verbal'] ?? null);

$needKeys = [
    "O_score",
    "C_score",
    "E_score",
    "A_score",
    "N_score",
    "abstract_reasoning",
    "numerical_aptitude",
    "spatial_aptitude",
    "perceptual_aptitude",
    "verbal_reasoning",
];

$featureTips = [];

try {
    if (!empty($needKeys)) {
        $in = build_in_placeholders($needKeys);
        $st = $pdo->prepare("SELECT feature_key, brief_explain FROM feature_explanations WHERE feature_key IN ($in)");
        $st->execute($needKeys);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $featureTips[$r['feature_key']] = $r['brief_explain'] ?? '';
        }
    }
} catch (Exception $e) {
    $featureTips = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results | Entry2Pros</title>

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

                <div class="result-header">
                    <h1>Assessment <span class="highlight">Analysis</span></h1>
                    <p>Great job, <?= htmlspecialchars($name) ?>. Here is the breakdown of your DNA.</p>
                </div>

                <div class="results-grid">

                    <div class="result-card">
                        <div class="card-header">
                            <div class="icon-box soft-orange"><i data-lucide="fingerprint"></i></div>
                            <h3>Personality Results</h3>
                        </div>
                        <div class="card-body">
                            <?= bar("Openness", $O, "#ffb300", $featureTips["O_score"] ?? "") ?>
                            <?= bar("Conscientiousness", $C, "#8e44ad", $featureTips["C_score"] ?? "") ?>
                            <?= bar("Extraversion", $E, "#00b894", $featureTips["E_score"] ?? "") ?>
                            <?= bar("Agreeableness", $A, "#27ae60", $featureTips["A_score"] ?? "") ?>
                            <?= bar("Neuroticism", $N, "#e74c3c", $featureTips["N_score"] ?? "") ?>
                        </div>
                    </div>

                    <div class="result-card">
                        <div class="card-header">
                            <div class="icon-box soft-blue"><i data-lucide="brain"></i></div>
                            <h3>Aptitude Results</h3>
                        </div>
                        <div class="card-body">
                            <?= bar("Abstract Reasoning", $LG, "#2980b9", $featureTips["abstract_reasoning"] ?? "") ?>
                            <?= bar("Numerical Aptitude", $NM, "#f39c12", $featureTips["numerical_aptitude"] ?? "") ?>
                            <?= bar("Spatial Aptitude", $SP, "#27ae60", $featureTips["spatial_aptitude"] ?? "") ?>
                            <?= bar("Perceptual Aptitude", $PC, "#8e44ad", $featureTips["perceptual_aptitude"] ?? "") ?>
                            <?= bar("Verbal Reasoning", $VB, "#16a085", $featureTips["verbal_reasoning"] ?? "") ?>
                        </div>
                    </div>

                </div>

                <div class="bottom-section">
                    <div class="academic-strip">
                        <div class="acd-icon"><i data-lucide="graduation-cap"></i></div>
                        <div class="acd-info">
                            <span class="acd-label">Academic Profile</span>
                            <span class="acd-value">
                                <?= htmlspecialchars($acaAns['level'] ?? 'N/A') ?> in
                                <?= htmlspecialchars($acaAns['field_of_study'] ?? 'General') ?>
                            </span>
                        </div>
                        <div class="acd-stat">
                            <span class="acd-label">CGPA</span>
                            <span class="acd-value highlight"><?= isset($acaAns['cgpa']) ? number_format((float)$acaAns['cgpa'], 2) : '-' ?></span>
                        </div>
                    </div>

                    <div class="action-area">
                        <a href="/User/career-result.php" class="btn btn-primary btn-result-next">
                            Unveil Career Matches
                            <i data-lucide="arrow-right"></i>
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