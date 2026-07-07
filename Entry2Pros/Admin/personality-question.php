<?php
// /Admin/personality-question.php
session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}
require dirname(__DIR__) . '/Main/db.php';

$username = $_SESSION['user']['username'] ?? 'admin';
$errors = [];
$validSections = ['openness', 'conscientiousness', 'extraversion', 'agreeableness', 'neuroticism'];

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Create
    if (isset($_POST['create'])) {
        $statement_txt  = trim($_POST['statement_txt'] ?? '');
        $section        = $_POST['section'] ?? '';
        $reverse_scored = isset($_POST['reverse_scored']) ? 1 : 0;
        $is_active      = isset($_POST['is_active']) ? 1 : 0;

        if ($statement_txt === '') $errors[] = "Please fill the statement text.";
        if (!in_array($section, $validSections, true)) $errors[] = "Please choose a valid section.";

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO personality_questions (statement_txt, section, reverse_scored, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$statement_txt, $section, $reverse_scored, $is_active]);
            header('Location: personality-question.php?ok=1');
            exit;
        }
    }

    // Update
    if (isset($_POST['update'])) {
        $id             = (int)($_POST['id'] ?? 0);
        $statement_txt  = trim($_POST['statement_txt'] ?? '');
        $section        = $_POST['section'] ?? '';
        $reverse_scored = isset($_POST['reverse_scored']) ? 1 : 0;
        $is_active      = isset($_POST['is_active']) ? 1 : 0;

        if (!$errors) {
            $stmt = $pdo->prepare("UPDATE personality_questions SET statement_txt=?, section=?, reverse_scored=?, is_active=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$statement_txt, $section, $reverse_scored, $is_active, $id]);
            header('Location: personality-question.php?ok=1');
            exit;
        }
    }

    // Delete
    if (isset($_POST['delete'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM personality_questions WHERE id=?");
            $stmt->execute([$id]);
            header('Location: personality-question.php?ok=1');
            exit;
        }
    }

    // Toggle active
    if (isset($_POST['toggle'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $row = $pdo->prepare("SELECT is_active FROM personality_questions WHERE id=?");
            $row->execute([$id]);
            if ($q = $row->fetch()) {
                $new = $q['is_active'] ? 0 : 1;
                $pdo->prepare("UPDATE personality_questions SET is_active=?, updated_at=NOW() WHERE id=?")->execute([$new, $id]);
                header('Location: personality-question.php?ok=1');
                exit;
            }
        }
    }
}

/* ---------- Fetch Logic ---------- */
$editing = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM personality_questions WHERE id=?");
    $stmt->execute([$eid]);
    $editing = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
$sectionFilter = $_GET['section'] ?? '';

$sql = "SELECT * FROM personality_questions WHERE 1=1";
$params = [];
if ($sectionFilter !== '' && in_array($sectionFilter, $validSections)) {
    $sql .= " AND section = ?";
    $params[] = $sectionFilter;
}
if ($search !== '') {
    $sql .= " AND statement_txt LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$statements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personality Bank | Entry2Pros</title>
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
            <a href="aptitude-question.php" class="nav-link"><i data-lucide="brain"></i> Aptitude Bank</a>
            <a href="personality-question.php" class="nav-link active"><i data-lucide="fingerprint"></i> Personality Bank</a>
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
                <h1>Personality <span class="text-green">Bank</span></h1>
                <p>Manage trait-based assessment statements.</p>
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
                    <i data-lucide="plus-circle" size="20"></i> Add Statement
                </button>

                <form method="get" style="display:flex; gap:12px;">
                    <select name="section" style="padding:10px; border-radius:10px; border:1px solid #e2e8f0; font-family:inherit;">
                        <option value="">All Traits</option>
                        <?php foreach ($validSections as $sec): ?>
                            <option value="<?= $sec ?>" <?= ($sectionFilter === $sec ? 'selected' : '') ?>><?= ucfirst($sec) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search statement..." style="padding:10px; border-radius:10px; border:1px solid #e2e8f0; width:280px; font-family:inherit;">
                    <button type="submit" class="btn-filter" style="background:var(--side); color:white; border:none; padding:10px 20px; border-radius:10px; cursor:pointer;">Filter</button>
                </form>
            </div>

            <div id="addModal" class="modal-overlay <?= isset($_GET['add']) ? 'active' : '' ?>">
                <div class="modal-container" style="max-width: 650px; padding: 18px; border-radius: 12px;">
                    <button class="close-modal" onclick="closeModal('addModal')"><i data-lucide="x" size="18"></i></button>
                    <h3 style="margin-bottom:15px; font-size:18px; font-weight:800; color:var(--side);">Create New Statement</h3>

                    <form method="post" action="personality-question.php">
                        <div style="margin-bottom:12px;">
                            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Statement Text</label>
                            <textarea name="statement_txt" rows="2" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px;" placeholder="e.g., I am the life of the party" required></textarea>
                        </div>

                        <div style="display: flex; gap: 10px; align-items: flex-end; margin-bottom:18px;">
                            <div style="flex: 1.2;">
                                <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Trait Section</label>
                                <select name="section" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                    <?php foreach ($validSections as $sec): ?>
                                        <option value="<?= $sec ?>"><?= ucfirst($sec) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <label style="flex: 1; display: flex; align-items: center; gap: 8px; background: #fff1f2; padding: 0 10px; border-radius: 8px; border: 1px solid #fecdd3; height: 38px; cursor: pointer; margin:0;">
                                <input type="checkbox" name="reverse_scored" style="width:15px; height:15px; accent-color: #ef4444;">
                                <span style="font-weight:700; font-size:11px; color: #be123c; white-space:nowrap;">Reverse Scored</span>
                            </label>

                            <label style="flex: 1; display: flex; align-items: center; gap: 8px; background: #f8fafc; padding: 0 10px; border-radius: 8px; border: 1px solid #e2e8f0; height: 38px; cursor: pointer; margin:0;">
                                <input type="checkbox" name="is_active" checked style="width:15px; height:15px; accent-color: var(--accent);">
                                <span style="font-weight:700; font-size:11px; color: var(--side); white-space:nowrap;">Active</span>
                            </label>
                        </div>

                        <button type="submit" name="create" style="width:100%; background:var(--accent); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px;">Publish Statement</button>
                    </form>
                </div>
            </div>

            <?php if ($editing): ?>
                <div id="editModal" class="modal-overlay active">
                    <div class="modal-container" style="max-width: 650px; padding: 18px; border-radius: 12px; border-top: 5px solid var(--accent); position: relative;">
                        <button type="button" class="close-modal" onclick="closeEditModal()">
                            <i data-lucide="x" size="18"></i>
                        </button>
                        </button>

                        <div style="margin-bottom:15px;">
                            <h3 style="font-size:18px; font-weight:800; color:var(--side); margin-bottom:2px;">Edit Statement Info</h3>
                            <span style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:800;">RECORD ID: #<?= (int)$editing['id'] ?></span>
                        </div>

                        <form method="post" action="personality-question.php">
                            <input type="hidden" name="id" value="<?= $editing['id'] ?>">

                            <div style="margin-bottom:12px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Statement Text</label>
                                <textarea name="statement_txt" rows="2" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px; line-height:1.5;" required><?= htmlspecialchars($editing['statement_txt']) ?></textarea>
                            </div>

                            <div style="display: flex; gap: 10px; align-items: flex-end; margin-bottom:18px;">
                                <div style="flex: 1.2;">
                                    <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Trait Section</label>
                                    <select name="section" style="width:100%; padding:0 10px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                        <?php foreach ($validSections as $sec): ?>
                                            <option value="<?= $sec ?>" <?= ($editing['section'] === $sec ? 'selected' : '') ?>><?= ucfirst($sec) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <label style="flex: 1; display: flex; align-items: center; gap: 8px; background: #fff1f2; padding: 0 10px; border-radius: 8px; border: 1px solid #fecdd3; height: 38px; cursor: pointer; margin:0;">
                                    <input type="checkbox" name="reverse_scored" <?= $editing['reverse_scored'] ? 'checked' : '' ?> style="width:15px; height:15px; accent-color: #ef4444;">
                                    <span style="font-weight:700; font-size:11px; color: #be123c;">Reverse</span>
                                </label>

                                <label style="flex: 1; display: flex; align-items: center; gap: 8px; background: #f8fafc; padding: 0 10px; border-radius: 8px; border: 1px solid #e2e8f0; height: 38px; cursor: pointer; margin:0;">
                                    <input type="checkbox" name="is_active" <?= $editing['is_active'] ? 'checked' : '' ?> style="width:15px; height:15px; accent-color: var(--accent);">
                                    <span style="font-weight:700; font-size:11px; color: var(--side);">Active</span>
                                </label>
                            </div>

                            <button type="submit" name="update" style="width:100%; background:var(--side); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px;">Update Statement</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="question-list" style="display:grid; gap:20px; padding-bottom: 50px;">
                <?php if (!$statements): ?>
                    <div style="text-align:center; padding:50px; background:white; border-radius:20px; border:1px dashed #cbd5e1;">
                        <i data-lucide="database-zap" size="48" style="color:#cbd5e1; margin-bottom:15px;"></i>
                        <p style="color:#64748b;">No personality statements found.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($statements as $s): ?>
                    <div class="question-card" style="background:white; padding:25px; border-radius:20px; border:1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <div style="display:flex; gap:8px;">
                                <span style="background:#f1f5f9; color:#475569; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800;"><?= strtoupper($s['section']) ?></span>
                                <?php if ($s['reverse_scored']): ?>
                                    <span style="background:#fff1f2; color:#e11d48; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800;">REVERSE</span>
                                <?php endif; ?>
                                <span class="status-badge <?= $s['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $s['is_active'] ? 'Active' : 'Disabled' ?>
                                </span>
                            </div>
                            <div style="font-size:12px; font-weight:600; color:#94a3b8;">ID: #<?= $s['id'] ?></div>
                        </div>

                        <h3 style="font-size:18px; color:var(--side); font-weight:700; line-height:1.5; margin-bottom:20px;">
                            "<?= htmlspecialchars($s['statement_txt']) ?>"
                        </h3>

                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:15px; border-top:1px solid #f1f5f9;">
                            <div style="display:flex; gap:15px;">
                                <a href="personality-question.php?edit=<?= $s['id'] ?>" class="action-btn btn-edit">
                                    <i data-lucide="pencil-line" size="16"></i> Edit
                                </a>
                                <form method="post" onsubmit="return confirmDelete(this);">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <input type="hidden" name="delete" value="1">
                                    <button type="button" onclick="confirmDelete(this.form)" class="action-btn btn-delete">
                                        <i data-lucide="trash-2" size="16"></i> Delete
                                    </button>
                                </form>
                            </div>

                            <form method="post">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button name="toggle" class="action-btn btn-toggle">
                                    <i data-lucide="<?= $s['is_active'] ? 'eye-off' : 'eye' ?>" size="16"></i>
                                    <?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>
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
            if (window.location.search.includes('add=')) {
                window.location.href = 'personality-question.php';
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
                if (window.location.search.includes('edit=') || window.location.search.includes('add=')) {
                    window.location.href = 'personality-question.php';
                }
            }
        }

        function confirmDelete(form) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#f87171',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
            return false;
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('ok')) {
            Swal.fire({
                icon: 'success',
                title: 'Great!',
                text: 'Changes saved successfully.',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
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