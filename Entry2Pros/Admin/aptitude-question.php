<?php
// /Admin/aptitude-question.php
session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}
require dirname(__DIR__) . '/Main/db.php';

$username = $_SESSION['user']['username'] ?? 'admin';
$errors = [];
$validSections = ['numerical', 'spatial', 'perceptual', 'abstract', 'verbal'];
$validDiff = ['easy', 'medium', 'hard'];

/* ---------- Actions (FULL BACKEND LOGIC RESTORED) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create
    if (isset($_POST['create'])) {
        $question_txt = trim($_POST['question_txt'] ?? '');
        $section      = $_POST['section'] ?? '';
        $difficulty   = $_POST['difficulty'] ?? 'medium';
        $option_a     = trim($_POST['option_a'] ?? '');
        $option_b     = trim($_POST['option_b'] ?? '');
        $option_c     = trim($_POST['option_c'] ?? '');
        $option_d     = trim($_POST['option_d'] ?? '');
        $correct_opt  = $_POST['correct_opt'] ?? '';
        $is_active    = isset($_POST['is_active']) ? 1 : 0;

        if (!in_array($section, $validSections, true)) $errors[] = "Please choose a section.";
        if (!in_array($difficulty, $validDiff, true)) $errors[] = "Please choose a difficulty.";
        if ($question_txt === '' || $option_a === '' || $option_b === '' || $option_c === '' || $option_d === '') $errors[] = "Fill all fields.";
        if (!in_array($correct_opt, ['A', 'B', 'C', 'D'], true)) $errors[] = "Choose the correct option.";

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO aptitude_questions (question_txt, section, difficulty, option_a, option_b, option_c, option_d, correct_opt, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$question_txt, $section, $difficulty, $option_a, $option_b, $option_c, $option_d, $correct_opt, $is_active]);
            header('Location: aptitude-question.php?ok=1');
            exit;
        }
    }

    // Update
    if (isset($_POST['update'])) {
        $id           = (int)($_POST['id'] ?? 0);
        $question_txt = trim($_POST['question_txt'] ?? '');
        $option_a     = trim($_POST['option_a'] ?? '');
        $option_b     = trim($_POST['option_b'] ?? '');
        $option_c     = trim($_POST['option_c'] ?? '');
        $option_d     = trim($_POST['option_d'] ?? '');
        $correct_opt  = $_POST['correct_opt'] ?? '';
        $is_active    = isset($_POST['is_active']) ? 1 : 0; // Tambah line ni

        if (!$errors) {
            // Tambah is_active=? dalam SQL query
            $stmt = $pdo->prepare("UPDATE aptitude_questions SET question_txt=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_opt=?, is_active=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$question_txt, $option_a, $option_b, $option_c, $option_d, $correct_opt, $is_active, $id]);
            header('Location: aptitude-question.php?ok=1');
            exit;
        }
    }

    // Delete
    if (isset($_POST['delete'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM aptitude_questions WHERE id=?");
            $stmt->execute([$id]);
            header('Location: aptitude-question.php?ok=1');
            exit;
        }
    }

    // Toggle active
    if (isset($_POST['toggle'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $row = $pdo->prepare("SELECT is_active FROM aptitude_questions WHERE id=?");
            $row->execute([$id]);
            if ($q = $row->fetch()) {
                $new = $q['is_active'] ? 0 : 1;
                $pdo->prepare("UPDATE aptitude_questions SET is_active=?, updated_at=NOW() WHERE id=?")->execute([$new, $id]);
                header('Location: aptitude-question.php?ok=1');
                exit;
            }
        }
    }
}

/* ---------- Fetch Logic ---------- */
$editing = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM aptitude_questions WHERE id=?");
    $stmt->execute([$eid]);
    $editing = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
$sectionFilter = $_GET['section'] ?? '';

$sql = "SELECT * FROM aptitude_questions WHERE 1=1";
$params = [];
if ($sectionFilter !== '' && in_array($sectionFilter, $validSections)) {
    $sql .= " AND section = ?";
    $params[] = $sectionFilter;
}
if ($search !== '') {
    $sql .= " AND question_txt LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aptitude Bank | Entry2Pros</title>
    <link rel="stylesheet" href="../Main/index.css">
    <link rel="stylesheet" href="../Main/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="dashboard-body">

    <aside class="sidebar">
        <div class="sidebar-brand">Entry<span class="text-green">2</span>Pros</div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main Console</div>
            <a href="admin-dashboard.php" class="nav-link"><i data-lucide="layout-grid"></i> Overview</a>
            <div class="nav-label">Assessment Systems</div>
            <a href="aptitude-question.php" class="nav-link active"><i data-lucide="brain"></i> Aptitude Bank</a>
            <a href="personality-question.php" class="nav-link"><i data-lucide="fingerprint"></i> Personality Bank</a>
            <div class="nav-label">Career Directory</div>
            <a href="job-listing.php" class="nav-link"><i data-lucide="briefcase"></i> Job Postings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="/Main/logout.php" class="logout-action">
                <div class="logout-icon-box"><i data-lucide="log-out"></i></div>
                <span>Terminate Session</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-left">
                <h1>Aptitude <span class="text-green">Bank</span></h1>
                <p>Manage and configure aptitude assessment questions.</p>
            </div>
            <div class="header-right">
                <div class="admin-profile-card">
                    <div class="avatar-circle"><i data-lucide="shield-check"></i></div>
                    <div class="profile-details">
                        <span class="p-role">Logged in as</span>
                        <span class="p-name"><?= htmlspecialchars($username) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-content">

            <div class="top-action" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; background:white; padding:15px 25px; border-radius:15px; border:1px solid var(--border);">
                <button onclick="openModal('addModal')" class="btn-add-main" style="background:var(--accent); color:white; padding:12px 24px; border-radius:12px; border:none; font-weight:700; display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <i data-lucide="plus-circle" size="20"></i> Add Question
                </button>

                <form method="get" style="display:flex; gap:12px;">
                    <select name="section" style="padding:10px; border-radius:10px; border:1px solid #e2e8f0; font-family:inherit;">
                        <option value="">All Categories</option>
                        <?php foreach ($validSections as $sec): ?>
                            <option value="<?= $sec ?>" <?= ($sectionFilter === $sec ? 'selected' : '') ?>><?= ucfirst($sec) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search question..." style="padding:10px; border-radius:10px; border:1px solid #e2e8f0; width:280px; font-family:inherit;">
                    <button type="submit" class="btn-filter">Filter</button>
                </form>
            </div>

            <div id="addModal" class="modal-overlay <?= isset($_GET['add']) ? 'active' : '' ?>">
                <div class="modal-container" style="max-width: 700px; padding: 18px; border-radius: 12px;">
                    <button class="close-modal" onclick="closeModal('addModal')"><i data-lucide="x" size="18"></i></button>
                    <h3 style="margin-bottom:15px; font-size:18px; font-weight:800; color:var(--side);">Create New Question</h3>

                    <form method="post" action="aptitude-question.php">
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Question Text</label>
                            <textarea name="question_txt" rows="2" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px;" required></textarea>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Section</label>
                                <select name="section" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                    <?php foreach ($validSections as $sec): ?>
                                        <option value="<?= $sec ?>"><?= ucfirst($sec) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Difficulty Level</label>
                                <select name="difficulty" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:12px;">
                            <input type="text" name="option_a" placeholder="Option A" style="padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                            <input type="text" name="option_b" placeholder="Option B" style="padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                            <input type="text" name="option_c" placeholder="Option C" style="padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                            <input type="text" name="option_d" placeholder="Option D" style="padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                        </div>

                        <div style="display:flex; gap:12px; align-items:flex-end; margin-bottom:18px;">
                            <div style="flex: 1;">
                                <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Correct Answer</label>
                                <select name="correct_opt" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;">
                                    <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <label style="display:flex; align-items:center; gap:8px; height:38px; background:#f8fafc; border:1px solid #e2e8f0; padding:0 12px; border-radius:8px; cursor:pointer; margin:0; flex:1; justify-content:center;">
                                <input type="checkbox" name="is_active" checked style="width:15px; height:15px; accent-color:var(--accent);">
                                <span style="font-weight:700; font-size:12px; color:#475569;">Active</span>
                            </label>
                        </div>

                        <button type="submit" name="create" style="width:100%; background:var(--accent); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px;">Publish Question</button>
                    </form>
                </div>
            </div>

            <?php if ($editing): ?>
                <div id="editModal" class="modal-overlay active">
                    <div class="modal-container" style="max-width: 700px; padding: 18px; border-radius: 12px; border-top: 5px solid var(--accent); position: relative;">
                        <button type="button" class="close-modal" onclick="closeEditModal()">
                            <i data-lucide="x" size="18"></i>
                        </button>
                        </button>

                        <div style="margin-bottom:15px;">
                            <h3 style="font-size:18px; font-weight:800; color:var(--side); margin-bottom:2px;">Edit Question Details</h3>
                            <span style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:800;">QUESTION ID: #<?= (int)$editing['id'] ?></span>
                        </div>

                        <form method="post" action="aptitude-question.php">
                            <input type="hidden" name="id" value="<?= $editing['id'] ?>">

                            <div style="margin-bottom:10px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Question Text</label>
                                <textarea name="question_txt" rows="2" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px; line-height:1.4;" required><?= htmlspecialchars($editing['question_txt']) ?></textarea>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Section</label>
                                    <select name="section" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                        <?php foreach ($validSections as $sec): ?>
                                            <option value="<?= $sec ?>" <?= $editing['section'] == $sec ? 'selected' : '' ?>><?= ucfirst($sec) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Difficulty Level</label>
                                    <select name="difficulty" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                        <option value="easy" <?= $editing['difficulty'] == 'easy' ? 'selected' : '' ?>>Easy</option>
                                        <option value="medium" <?= $editing['difficulty'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="hard" <?= $editing['difficulty'] == 'hard' ? 'selected' : '' ?>>Hard</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-bottom:12px;">
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:2px 10px; border-radius:8px; border:1px solid #e2e8f0;">
                                    <span style="font-weight:800; color:var(--accent); font-size:12px;">A</span>
                                    <input type="text" name="option_a" value="<?= htmlspecialchars($editing['option_a']) ?>" style="width:100%; background:transparent; border:none; height:34px; font-size:12px;" required>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:2px 10px; border-radius:8px; border:1px solid #e2e8f0;">
                                    <span style="font-weight:800; color:var(--accent); font-size:12px;">B</span>
                                    <input type="text" name="option_b" value="<?= htmlspecialchars($editing['option_b']) ?>" style="width:100%; background:transparent; border:none; height:34px; font-size:12px;" required>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:2px 10px; border-radius:8px; border:1px solid #e2e8f0;">
                                    <span style="font-weight:800; color:var(--accent); font-size:12px;">C</span>
                                    <input type="text" name="option_c" value="<?= htmlspecialchars($editing['option_c']) ?>" style="width:100%; background:transparent; border:none; height:34px; font-size:12px;" required>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:2px 10px; border-radius:8px; border:1px solid #e2e8f0;">
                                    <span style="font-weight:800; color:var(--accent); font-size:12px;">D</span>
                                    <input type="text" name="option_d" value="<?= htmlspecialchars($editing['option_d']) ?>" style="width:100%; background:transparent; border:none; height:34px; font-size:12px;" required>
                                </div>
                            </div>

                            <div style="display:flex; gap:12px; align-items:flex-end; margin-bottom:18px;">
                                <div style="flex: 1;">
                                    <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Correct Answer</label>
                                    <select name="correct_opt" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                        <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                                            <option value="<?= $opt ?>" <?= $editing['correct_opt'] == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <label style="display:flex; align-items:center; gap:8px; height:38px; background:#f8fafc; border:1px solid #e2e8f0; padding:0 12px; border-radius:8px; cursor:pointer; margin:0; flex:1; justify-content:center;">
                                    <input type="checkbox" name="is_active" <?= $editing['is_active'] ? 'checked' : '' ?> style="width:15px; height:15px; accent-color:var(--accent);">
                                    <span style="font-weight:700; font-size:12px; color:#475569;">Active Status</span>
                                </label>
                            </div>

                            <button type="submit" name="update" style="width:100%; background:var(--side); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px;">Update Question</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="question-list" style="display:grid; gap:20px; padding-bottom: 50px;">
                <?php if (!$questions): ?>
                    <div style="text-align:center; padding:50px; background:white; border-radius:20px; border:1px dashed #cbd5e1;">
                        <i data-lucide="database-zap" size="48" style="color:#cbd5e1; margin-bottom:15px;"></i>
                        <p style="color:#64748b;">No questions found in this category.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($questions as $q): ?>
                    <div class="question-card" style="background:white; padding:25px; border-radius:20px; border:1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <div style="display:flex; gap:8px;">
                                <span style="background:#f1f5f9; color:#475569; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800;"><?= strtoupper($q['section']) ?></span>
                                <span style="
    background: <?= $q['difficulty'] == 'hard' ? '#fef2f2' : ($q['difficulty'] == 'medium' ? '#fffbeb' : '#ecfdf5') ?>; 
    color: <?= $q['difficulty'] == 'hard' ? '#ef4444' : ($q['difficulty'] == 'medium' ? '#f59e0b' : '#10b981') ?>; 
    padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800;">
                                    <?= strtoupper($q['difficulty']) ?>
                                </span>
                                <span class="status-badge <?= $q['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $q['is_active'] ? 'Active' : 'Disabled' ?>
                                </span>
                            </div>
                            <div style="font-size:12px; font-weight:600; color:#94a3b8;">ID: #<?= $q['id'] ?></div>
                        </div>

                        <h3 style="font-size:16px; color:var(--side); font-weight:700; line-height:1.6; margin-bottom:10px;">
                            <?= htmlspecialchars($q['question_txt']) ?>
                        </h3>

                        <div class="option-grid">
                            <div class="opt-item <?= ($q['correct_opt'] === 'A' ? 'is-correct' : '') ?>"><strong>A.</strong> <?= htmlspecialchars($q['option_a']) ?></div>
                            <div class="opt-item <?= ($q['correct_opt'] === 'B' ? 'is-correct' : '') ?>"><strong>B.</strong> <?= htmlspecialchars($q['option_b']) ?></div>
                            <div class="opt-item <?= ($q['correct_opt'] === 'C' ? 'is-correct' : '') ?>"><strong>C.</strong> <?= htmlspecialchars($q['option_c']) ?></div>
                            <div class="opt-item <?= ($q['correct_opt'] === 'D' ? 'is-correct' : '') ?>"><strong>D.</strong> <?= htmlspecialchars($q['option_d']) ?></div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:20px; border-top:1px solid #f1f5f9; margin-top:10px;">
                            <div style="display:flex; gap:15px;">
                                <a href="aptitude-question.php?edit=<?= $q['id'] ?>" class="action-btn btn-edit">
                                    <i data-lucide="pencil-line" size="16"></i> Edit
                                </a>
                                <form method="post" onsubmit="return confirmDelete(this);">
                                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                    <input type="hidden" name="delete" value="1">

                                    <button type="button" onclick="confirmDelete(this.form)" class="action-btn btn-delete">
                                        <i data-lucide="trash-2" size="16"></i> Delete
                                    </button>
                                </form>
                            </div>

                            <form method="post">
                                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                <button name="toggle" class="action-btn btn-toggle">
                                    <i data-lucide="<?= $q['is_active'] ? 'eye-off' : 'eye' ?>" size="16"></i>
                                    <?= $q['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <footer class="admin-footer-clean">
            <p>&copy; 2025 Entry2Profession. Internal Admin Control Panel.</p>
        </footer>
    </main>

    <script>
        lucide.createIcons();
    </script>
    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            // Jika ada query string 'add' dlm URL, kita buang supaya tak muncul balik bila reload
            if (window.location.search.includes('add=')) {
                window.location.href = 'aptitude-question.php';
            }
        }

        // Tutup modal kalau user klik luar dari kotak putih
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
                if (window.location.search.includes('edit=') || window.location.search.includes('add=')) {
                    window.location.href = 'aptitude-question.php';
                }
            }
        }
    </script>

    <script>
        // 1. Pop-up untuk Delete Confirmation
        function confirmDelete(form) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#f87171',
                confirmButtonText: 'Yes, delete it!',
                customClass: {
                    popup: 'swal-popup-custom'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Tunjuk loading sekejap sebelum submit
                    Swal.fire({
                        title: 'Deleting...',
                        timer: 1000,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit(); // Sekarang dia akan bawa sekali $_POST['delete']
                }
            });
            return false;
        }

        // 2. Pop-up untuk Success Message (Bila URL ada ?ok=1)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('ok')) {
            Swal.fire({
                icon: 'success',
                title: 'Great!',
                text: 'Changes saved successfully.',
                showConfirmButton: false,
                timer: 2000,
                customClass: {
                    popup: 'swal-popup-custom',
                    title: 'swal-title-custom'
                }
            }).then(() => {
                // Clean URL supaya tak pop-up lagi bila refresh
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        }
    </script>
    <script>
        function closeEditModal() {
            // 1. Tutup modal secara visual
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.classList.remove('active');
            }

            // 2. Buang parameter ?edit dari URL supaya bila refresh modal tak muncul balik
            const url = new URL(window.location);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url);

            // 3. (Optional) Kalau kau nak page tu refresh bersih terus:
            // window.location.href = window.location.pathname; 
        }
    </script>
</body>

</html>