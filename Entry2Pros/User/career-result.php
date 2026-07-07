<?php
session_start();
require dirname(__DIR__) . '/Main/db.php';
require dirname(__DIR__) . '/Main/ml_xgboost.php';

if (!isset($_SESSION['assessment_session_id'])) {
  die("No assessment session found. Please start the assessment again.");
}
$sessionId = (int)$_SESSION['assessment_session_id'];

// --- Setup User Vars for Header (Match Dashboard) ---
$username = $_SESSION['user']['username'] ?? 'User';
$name     = $_SESSION['user']['name'] ?? 'Future Pro';

$ML_URL = "http://127.0.0.1:8000/predict";
$mlError = null;
$ml = null;

function build_in_placeholders(array $items): string
{
  return implode(',', array_fill(0, count($items), '?'));
}

function pct($row)
{
  return (is_array($row) && isset($row["prob"])) ? round(((float)$row["prob"]) * 100) : null;
}
function sharePct($row)
{
  return (is_array($row) && isset($row["share_percent"])) ? round(((float)$row["share_percent"]), 0) : null;
}

function getCareerBrief(PDO $pdo, ?string $careerName): ?string
{
  if (!$careerName || $careerName === "N/A") return null;

  $st = $pdo->prepare("
    SELECT brief_explain
    FROM career_explanations
    WHERE career_name = ?
    LIMIT 1
  ");
  $st->execute([$careerName]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  return $row["brief_explain"] ?? null;
}

/**
 * STEP 1) Get Academic cluster first
 */
$acadStmt = $pdo->prepare("
  SELECT aa.cgpa, ac.cluster
  FROM academic_attempts atp
  JOIN academic_answers aa ON aa.attempt_id = atp.id
  LEFT JOIN academic_courses ac ON ac.id = aa.course_id
  WHERE atp.session_id = :sid AND atp.status = 'finished'
  ORDER BY atp.id DESC, aa.id DESC
  LIMIT 1
");
$acadStmt->execute([":sid" => $sessionId]);
$acad = $acadStmt->fetch(PDO::FETCH_ASSOC);

if (!$acad) {
  $mlError = "Academic step not completed. Please finish Academic step first.";
}

$dbCluster = $acad["cluster"] ?? "";
$allowedClusters = ["Information Technology", "Accounting/Finance", "Biology", "Physics/Engineering"];

if (!$mlError && !in_array($dbCluster, $allowedClusters, true)) {
  $mlError = "Academic cluster not found. Please re-select your course/field in Academic step.";
}

/**
 * STEP 2) Get Aptitude + Personality
 */
$apt = null;
$per = null;

if (!$mlError) {
  $aptStmt = $pdo->prepare("
    SELECT score_numerical, score_verbal, score_logical, score_spatial, score_perceptual
    FROM aptitude_attempts
    WHERE session_id = :sid AND status = 'finished'
    ORDER BY id DESC
    LIMIT 1
  ");
  $aptStmt->execute([":sid" => $sessionId]);
  $apt = $aptStmt->fetch(PDO::FETCH_ASSOC);

  $perStmt = $pdo->prepare("
    SELECT score_openness, score_conscientiousness, score_extraversion, score_agreeableness, score_neuroticism
    FROM personality_attempts
    WHERE session_id = :sid AND status = 'finished'
    ORDER BY id DESC
    LIMIT 1
  ");
  $perStmt->execute([":sid" => $sessionId]);
  $per = $perStmt->fetch(PDO::FETCH_ASSOC);

  if (!$apt || !$per) {
    $mlError = "Incomplete assessment data. Please ensure Aptitude and Personality steps are finished.";
  }
}

/**
 * STEP 3) Call ML
 */
if (!$mlError) {
  $payload = [
    "field_cluster" => $dbCluster,

    "O_score" => (float)$per["score_openness"],
    "C_score" => (float)$per["score_conscientiousness"],
    "E_score" => (float)$per["score_extraversion"],
    "A_score" => (float)$per["score_agreeableness"],
    "N_score" => (float)$per["score_neuroticism"],

    "numerical" => (float)$apt["score_numerical"],
    "spatial" => (float)$apt["score_spatial"],
    "perceptual" => (float)$apt["score_perceptual"],
    "abstract_reasoning" => (float)$apt["score_logical"],
    "verbal" => (float)$apt["score_verbal"],

    "cgpa" => (float)$acad["cgpa"],
  ];

  try {
    $ml = ml_predict($ML_URL, $payload, 10);

    $upd = $pdo->prepare("
      UPDATE assessment_sessions
      SET ml_predicted_at = NOW()
      WHERE id = :sid
    ");
    $upd->execute([":sid" => $sessionId]);
  } catch (Exception $e) {
    $mlError = $e->getMessage();
    $ml = null;
  }
}

/**
 * STEP 4) Results
 */
$top3 = $ml["top3"] ?? [];
$top1 = $top3[0] ?? null;
$top2 = $top3[1] ?? null;
$top3x = $top3[2] ?? null;

$top1Career = $top1["career"] ?? "N/A";
$top2Career = $top2["career"] ?? "N/A";
$top3Career = $top3x["career"] ?? "N/A";

$_SESSION['top3_careers'] = array_values(array_filter([$top1Career, $top2Career, $top3Career], fn($c) => $c && $c !== 'N/A'));
$_SESSION['field_cluster'] = $dbCluster;

$_SESSION['predicted_top3_careers'] = array_values(array_unique(array_filter(
  [$top1Career, $top2Career, $top3Career],
  fn($c) => is_string($c) && $c !== '' && $c !== 'N/A'
)));

$_SESSION['predicted_cluster'] = $dbCluster ?: '';


$topCareer = $top1Career;
$topProb   = pct($top1);

$top1Brief = getCareerBrief($pdo, $top1Career);
$top2Brief = getCareerBrief($pdo, $top2Career);
$top3Brief = getCareerBrief($pdo, $top3Career);

$why = "This recommendation is generated from your personality (OCEAN), aptitude subscores, and CGPA within your selected field cluster.";

/**
 * Map ML factor name -> DB feature_key
 */
$featureKeyMap = [
  "O_score" => "O_score",
  "C_score" => "C_score",
  "E_score" => "E_score",
  "A_score" => "A_score",
  "N_score" => "N_score",

  "Numerical Aptitude" => "numerical_aptitude",
  "numerical" => "numerical_aptitude",
  "Spatial Aptitude" => "spatial_aptitude",
  "spatial" => "spatial_aptitude",
  "Perceptual Aptitude" => "perceptual_aptitude",
  "perceptual" => "perceptual_aptitude",
  "Abstract Reasoning" => "abstract_reasoning",
  "abstract_reasoning" => "abstract_reasoning",
  "Verbal Reasoning" => "verbal_reasoning",
  "verbal" => "verbal_reasoning",

  "GPA" => "cgpa",
  "cgpa" => "cgpa",
];

$fallbackLabel = [
  "O_score" => "Openness (O)",
  "C_score" => "Conscientiousness (C)",
  "E_score" => "Extraversion (E)",
  "A_score" => "Agreeableness (A)",
  "N_score" => "Neuroticism (N)",
  "numerical_aptitude" => "Numerical Aptitude",
  "spatial_aptitude" => "Spatial Aptitude",
  "perceptual_aptitude" => "Perceptual Aptitude",
  "abstract_reasoning" => "Abstract Reasoning",
  "verbal_reasoning" => "Verbal Reasoning",
  "cgpa" => "CGPA",
];

$topFactors = $ml["explain"]["top_factors"] ?? [];

/**
 * Pull Feature explanations (tooltips)
 */
$featureInfoMap = [];
$needKeys = [];

foreach ($topFactors as $f) {
  $raw = $f["feature"] ?? "";
  $key = $featureKeyMap[$raw] ?? $raw;
  if ($key) $needKeys[$key] = true;
}
$needKeys = array_keys($needKeys);

if (!empty($needKeys)) {
  try {
    $in = build_in_placeholders($needKeys);
    $st = $pdo->prepare("
      SELECT feature_key, display_name, brief_explain
      FROM feature_explanations
      WHERE feature_key IN ($in)
    ");
    $st->execute($needKeys);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $featureInfoMap[$r["feature_key"]] = $r;
    }
  } catch (Exception $e) {
    $featureInfoMap = [];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Career Prediction | Entry2Pros</title>

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

          <h1>Career <span class="highlight">Prediction</span></h1>
          <p>Based on your unique profile, here is where you'll shine.</p>


        </div>

        <div class="result-card top-career-card">
          <div class="card-header">
            <div class="icon-box soft-red"><i data-lucide="target"></i></div>
            <h3>Top Career Match</h3>
          </div>

          <div class="career-main-content">
            <?php if ($mlError): ?>
              <p class="error-msg"><?php echo htmlspecialchars($mlError); ?></p>
            <?php endif; ?>

            <div class="career-title-large">
              <?php echo htmlspecialchars($topCareer); ?>
            </div>

            <div class="career-meta">
              <span class="badge-field"><?php echo htmlspecialchars($dbCluster ?: "N/A"); ?></span>
              <?php if ($topProb !== null): ?>
                <span class="badge-confidence"><?php echo $topProb; ?>% Match</span>
              <?php endif; ?>
            </div>

            <div class="career-desc">
              <?php if (!empty($top1Brief)): ?>
                <?php echo htmlspecialchars($top1Brief); ?>
              <?php else: ?>
                <?php echo htmlspecialchars($why); ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="result-card">
          <div class="card-header">
            <div class="icon-box soft-orange"><i data-lucide="key"></i></div>
            <h3>Key Contributors</h3>
          </div>

          <div class="card-body">
            <?php if (!empty($topFactors)): ?>
              <p class="text-subtle">These percentages show how much each factor contributed to your Top Career Match (relative share among the top contributing factors, not your raw scores).</p>
              <div class="chips-container">
                <?php foreach ($topFactors as $f):
                  $raw = $f["feature"] ?? "";
                  $key = $featureKeyMap[$raw] ?? $raw;
                  $dbRow = $featureInfoMap[$key] ?? null;
                  $label = $dbRow["display_name"] ?? ($fallbackLabel[$key] ?? $raw);
                  $sp = sharePct($f);
                  $tipBody = $dbRow["brief_explain"] ?? "No explanation available yet.";
                ?>
                  <div class="chip" title="<?php echo htmlspecialchars($tipBody); ?>">
                    <span class="chip-label"><?php echo htmlspecialchars($label); ?></span>
                    <?php if ($sp !== null): ?>
                      <span class="chip-val"><?php echo $sp; ?>%</span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-subtle">No factor explanation available.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="results-grid">

          <div class="result-card mini-match">
            <div class="match-rank">#2</div>
            <h4 class="match-title"><?php echo htmlspecialchars($top2Career); ?></h4>
            <?php if ($p2 = pct($top2)): ?>
              <div class="match-conf"><?php echo $p2; ?>% Match</div>
            <?php endif; ?>
            <p class="match-desc">
              <?php echo htmlspecialchars($top2Brief ?? "Alternative career path suitable for your profile."); ?>
            </p>
          </div>

          <div class="result-card mini-match">
            <div class="match-rank">#3</div>
            <h4 class="match-title"><?php echo htmlspecialchars($top3Career); ?></h4>
            <?php if ($p3 = pct($top3x)): ?>
              <div class="match-conf"><?php echo $p3; ?>% Match</div>
            <?php endif; ?>
            <p class="match-desc">
              <?php echo htmlspecialchars($top3Brief ?? "Another potential direction worth exploring."); ?>
            </p>
          </div>

        </div>

        <div class="action-area" style="max-width: 500px; display: flex; gap: 10px; margin: 30px auto; align-items: center;">

          <a href="test-result.php" class="btn-back-ghost" title="Back to Results">
            <i data-lucide="arrow-left"></i>
          </a>

          <a href="job-result.php" class="btn btn-primary btn-result-next" style="flex: 1; margin:0; display: flex; align-items: center; justify-content: center; gap: 10px;">
            View Matching Job Listings
            <i data-lucide="briefcase"></i>
          </a>
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