<?php

// /User/aptitude-submit.php

session_start();

require dirname(__DIR__) . '/Main/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');



// ---------- AUTH (UNTOUCHED) ----------

if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {

    header('Location: /User/user-login.php');
    exit;
}

$userId   = (int)$_SESSION['user']['id'];

$username = $_SESSION['user']['username'] ?? ($_SESSION['user']['user_name'] ?? 'user');



if (empty($_SESSION['assessment_session_id']) || empty($_SESSION['aptitude_attempt_id'])) {

    header('Location: /User/test-session.php');
    exit;
}

$sessionId = (int)$_SESSION['assessment_session_id'];

$attemptId = (int)$_SESSION['aptitude_attempt_id'];



// ---------- Load attempt (UNTOUCHED) ----------

$att = $pdo->prepare("

  SELECT id, session_id, status

  FROM aptitude_attempts

  WHERE id = ?

  LIMIT 1

");

$att->execute([$attemptId]);

$attemptRow = $att->fetch(PDO::FETCH_ASSOC);

if (!$attemptRow) {
    header('Location: /User/test-session.php');
    exit;
}



$alreadySubmitted = ($attemptRow['status'] === 'finished' || $attemptRow['status'] === 'expired');

$answers = $_SESSION['aptitude_answers'] ?? [];



// ---------- Compute & save per section (WEIGHTED - UNTOUCHED) ----------

$weights = ['easy' => 1.00, 'medium' => 1.35, 'hard' => 1.80];

$secTotalW   = ['numerical' => 0, 'verbal' => 0, 'abstract' => 0, 'spatial' => 0, 'perceptual' => 0];

$secCorrectW = ['numerical' => 0, 'verbal' => 0, 'abstract' => 0, 'spatial' => 0, 'perceptual' => 0];



if (!$alreadySubmitted) {

    $pdo->beginTransaction();

    try {

        $servedIds = $_SESSION['aptitude_all_ids'] ?? [];

        $servedIds = array_values(array_unique(array_map('intval', $servedIds)));



        if (!$servedIds) {

            $servedIds = array_keys($answers);

            $servedIds = array_values(array_unique(array_map('intval', $servedIds)));
        }



        $placeholders = implode(',', array_fill(0, count($servedIds), '?'));

        $metaStmt = $pdo->prepare("

      SELECT id, section, difficulty, correct_opt

      FROM aptitude_questions

      WHERE id IN ($placeholders)

    ");

        $metaStmt->execute($servedIds);

        $metaRows = $metaStmt->fetchAll(PDO::FETCH_ASSOC);



        $metaById = [];

        foreach ($metaRows as $r) {

            $metaById[(int)$r['id']] = $r;
        }



        $insAns = $pdo->prepare("

      INSERT INTO aptitude_answers (attempt_id, question_id, selected_opt, is_correct, marked_at)

      VALUES (?, ?, ?, ?, NOW())

      ON DUPLICATE KEY UPDATE

        selected_opt = VALUES(selected_opt),

        is_correct   = VALUES(is_correct),

        marked_at    = VALUES(marked_at)

    ");



        foreach ($servedIds as $qid) {

            $qid = (int)$qid;

            if (!$qid || empty($metaById[$qid])) continue;

            $section = $metaById[$qid]['section'] ?? null;

            if (!$section || !isset($secTotalW[$section])) continue;

            $diff = $metaById[$qid]['difficulty'] ?? 'medium';

            $w    = $weights[$diff] ?? 1.35;

            $secTotalW[$section] += $w;

            $selected = $answers[$qid] ?? null;

            $isCorrect = 0;

            if ($selected && in_array($selected, ['A', 'B', 'C', 'D'], true)) {

                $correctOpt = $metaById[$qid]['correct_opt'] ?? null;

                $isCorrect = ($correctOpt && $selected === $correctOpt) ? 1 : 0;

                $insAns->execute([$attemptId, $qid, $selected, $isCorrect]);
            }

            if ($isCorrect === 1) {
                $secCorrectW[$section] += $w;
            }
        }



        $score_numerical  = ($secTotalW['numerical']  > 0) ? round(($secCorrectW['numerical']  / $secTotalW['numerical'])  * 10, 2) : null;

        $score_verbal     = ($secTotalW['verbal']     > 0) ? round(($secCorrectW['verbal']     / $secTotalW['verbal'])     * 10, 2) : null;

        $score_logical    = ($secTotalW['abstract']   > 0) ? round(($secCorrectW['abstract']   / $secTotalW['abstract'])   * 10, 2) : null;

        $score_spatial    = ($secTotalW['spatial']    > 0) ? round(($secCorrectW['spatial']    / $secTotalW['spatial'])    * 10, 2) : null;

        $score_perceptual = ($secTotalW['perceptual'] > 0) ? round(($secCorrectW['perceptual'] / $secTotalW['perceptual']) * 10, 2) : null;



        $upd = $pdo->prepare("

      UPDATE aptitude_attempts

      SET status = 'finished', finished_at = NOW(),

        score_numerical  = :sn, score_verbal = :sv, score_logical = :sl,

        score_spatial = :ss, score_perceptual = :sp

      WHERE id = :id LIMIT 1

    ");

        $upd->execute([':sn' => $score_numerical, ':sv' => $score_verbal, ':sl' => $score_logical, ':ss' => $score_spatial, ':sp' => $score_perceptual, ':id' => $attemptId]);

        $pdo->commit();
    } catch (Throwable $e) {

        $pdo->rollBack();

        $softError = true;
    }

    unset($_SESSION['aptitude_order'], $_SESSION['aptitude_index'], $_SESSION['aptitude_phase'], $_SESSION['aptitude_answers'], $_SESSION['aptitude_skipped'], $_SESSION['aptitude_all_ids']);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Test Submitted | Entry2Pros</title>

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



                    <?php if (!empty($softError)): ?>

                        <div class="test-header" style="text-align: center;">

                            <h1>Submission <span class="highlight">Error</span></h1>

                            <p class="hero-sub" style="margin-left: auto; margin-right: auto; max-width: 450px;">Something went wrong while saving your results. Please contact support if this persists.</p>

                        </div>

                    <?php elseif ($alreadySubmitted): ?>

                        <div class="test-header" style="text-align: center;">

                            <h1>Aptitude <span class="highlight">Completed</span></h1>

                            <p class="hero-sub" style="margin-left: auto; margin-right: auto; max-width: 450px;">Great job! Your answers have been processed and scored to build your professional profile.</p>

                        </div>

                    <?php else: ?>

                        <div class="test-header" style="text-align: center;">

                            <h1>Aptitude <span class="highlight">Finished</span></h1>

                            <p class="hero-sub" style="margin-left: auto; margin-right: auto; max-width: 450px;">Great job! Your answers have been processed and scored to build your professional profile.</p>

                        </div>

                    <?php endif; ?>



                    <div class="ins-list" style="margin-top: 30px; text-align: left; display: inline-block; width: 100%; max-width: 400px;">

                        <li style="justify-content: center;"><i data-lucide="shield-check" class="tick-green"></i> <span>Scores calculated successfully</span></li>

                        <li style="justify-content: center;"><i data-lucide="database" class="tick-green"></i> <span>Data synced with profile</span></li>

                    </div>



                    <div style="margin-top: 40px; width: 100%;">

                        <?php if (empty($softError)): ?>

                            <a href="/User/personality-intro.php" class="btn btn-primary btn-large w-full" style="text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 10px;">

                                Continue to Personality

                                <i data-lucide="arrow-right-circle"></i>

                            </a>

                        <?php else: ?>

                            <a href="/User/aptitude-test.php" class="btn btn-secondary btn-large w-full" style="text-decoration: none; display: flex; justify-content: center; align-items: center;">

                                Try Again

                            </a>

                        <?php endif; ?>

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