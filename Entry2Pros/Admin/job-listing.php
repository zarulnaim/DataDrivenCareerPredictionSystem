<?php
// /Admin/job-listing.php
session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

require dirname(__DIR__) . '/Main/db.php';

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['user']['username'] ?? 'admin';
$errors = [];

/* ---------- Auto-expire Logic ---------- */
try {
    $pdo->exec("UPDATE job_listings SET is_active=0 WHERE is_active=1 AND valid_until < CURDATE()");
} catch (Throwable $e) {
}

/* ---------- Helpers ---------- */
function is_valid_date_yyyy_mm_dd(string $d): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}
function normalize_phone(string $p): string
{
    return trim(preg_replace('/[^\d\+\-\s\(\)]/', '', $p));
}

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create & Update Actions (Logic Kekal Sama)
    if (isset($_POST['create']) || isset($_POST['update'])) {
        $id            = (int)($_POST['id'] ?? 0);
        $career_name   = trim($_POST['career_name'] ?? '');
        $title         = trim($_POST['title'] ?? '');
        $company_name  = trim($_POST['company_name'] ?? '');
        $location      = trim($_POST['location'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $requirements  = trim($_POST['requirements'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $contact_phone = normalize_phone($_POST['contact_phone'] ?? '');
        $apply_url     = trim($_POST['apply_url'] ?? '');
        $valid_until   = trim($_POST['valid_until'] ?? '');
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if ($career_name === '' || $title === '' || $company_name === '' || $location === '' || $description === '') $errors[] = "Required fields missing.";
        if ($contact_email === '' || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid Email.";
        if ($valid_until === '' || !is_valid_date_yyyy_mm_dd($valid_until)) $errors[] = "Invalid Date.";

        if (!$errors) {
            if (isset($_POST['create'])) {
                $stmt = $pdo->prepare("INSERT INTO job_listings (career_name, title, company_name, location, description, requirements, contact_email, contact_phone, apply_url, valid_until, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$career_name, $title, $company_name, $location, $description, $requirements ?: null, $contact_email, $contact_phone, $apply_url ?: null, $valid_until, $is_active]);
            } else {
                $stmt = $pdo->prepare("UPDATE job_listings SET career_name=?, title=?, company_name=?, location=?, description=?, requirements=?, contact_email=?, contact_phone=?, apply_url=?, valid_until=?, is_active=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$career_name, $title, $company_name, $location, $description, $requirements ?: null, $contact_email, $contact_phone, $apply_url ?: null, $valid_until, $is_active, $id]);
            }
            header('Location: job-listing.php?ok=1');
            exit;
        }
    }

    if (isset($_POST['delete'])) {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM job_listings WHERE id=?")->execute([$id]);
        header('Location: job-listing.php?ok=1');
        exit;
    }

    if (isset($_POST['toggle'])) {
        $id = (int)($_POST['id'] ?? 0);
        $row = $pdo->prepare("SELECT is_active, valid_until FROM job_listings WHERE id=?");
        $row->execute([$id]);
        if ($j = $row->fetch()) {
            $isExpired = (strtotime(date('Y-m-d')) > strtotime($j['valid_until']));
            if ($isExpired) {
                $pdo->prepare("UPDATE job_listings SET is_active=0 WHERE id=?")->execute([$id]);
                header('Location: job-listing.php?ok=1&msg=expired');
                exit;
            }
            $new = $j['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE job_listings SET is_active=?, updated_at=NOW() WHERE id=?")->execute([$new, $id]);
            header('Location: job-listing.php?ok=1');
            exit;
        }
    }
}

/* ---------- Fetch Logic ---------- */
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM job_listings WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
$careerFilter = $_GET['career'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$careerOptions = $pdo->query("SELECT DISTINCT career_name FROM job_listings ORDER BY career_name ASC")->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT *, (CASE WHEN valid_until < CURDATE() THEN 1 ELSE 0 END) AS is_expired FROM job_listings WHERE 1=1";
$params = [];
if ($careerFilter !== '') {
    $sql .= " AND career_name = ?";
    $params[] = $careerFilter;
}
if ($search !== '') {
    $sql .= " AND (title LIKE ? OR company_name LIKE ? OR location LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($statusFilter === 'active') $sql .= " AND is_active=1 AND valid_until >= CURDATE()";
elseif ($statusFilter === 'inactive') $sql .= " AND is_active=0 AND valid_until >= CURDATE()";
elseif ($statusFilter === 'expired') $sql .= " AND valid_until < CURDATE()";

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Directory | Entry2Pros</title>
    <link rel="stylesheet" href="../Main/index.css">
    <link rel="stylesheet" href="../Main/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .job-grid-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .contact-pill {
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .contact-pill i {
            color: var(--accent);
            margin-right: 5px;
        }

        .job-desc-box {
            background: #fff;
            border-left: 4px solid var(--accent);
            padding: 15px;
            border-radius: 0 12px 12px 0;
            margin: 15px 0;
            font-size: 14px;
            color: #475569;
        }
    </style>
</head>

<body class="dashboard-body">

    <aside class="sidebar">
        <div class="sidebar-brand">Entry<span class="text-green">2</span>Pros</div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main Console</div>
            <a href="admin-dashboard.php" class="nav-link"><i data-lucide="layout-grid"></i> Overview</a>
            <div class="nav-label">Assessment Systems</div>
            <a href="aptitude-question.php" class="nav-link"><i data-lucide="brain"></i> Aptitude Bank</a>
            <a href="personality-question.php" class="nav-link"><i data-lucide="fingerprint"></i> Personality Bank</a>
            <div class="nav-label">Career Directory</div>
            <a href="job-listing.php" class="nav-link active"><i data-lucide="briefcase"></i> Job Postings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="/Main/logout.php" class="logout-action">
                <div class="logout-icon-box"><i data-lucide="log-out"></i></div><span>Terminate Session</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-left">
                <h1>Career <span class="text-green">Directory</span></h1>
                <p>Manage job opportunities and recruitment listings.</p>
            </div>
            <div class="header-right">
                <div class="admin-profile-card">
                    <div class="avatar-circle"><i data-lucide="shield-check"></i></div>
                    <div class="profile-details">
                        <span class="p-role">Logged in as</span>
                        <span class="p-name"><?= h($username) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <div class="top-action" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; background:white; padding:15px 25px; border-radius:15px; border:1px solid var(--border);">
                <button onclick="openModal('addModal')" class="btn-add-main" style="background:var(--accent); color:white; padding:12px 24px; border-radius:12px; border:none; font-weight:700; display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <i data-lucide="plus-circle" size="20"></i> Post New Job
                </button>

                <form method="get" style="display:flex; gap:10px;">
                    <select name="career" style="padding:10px; border-radius:10px; border:1px solid #e2e8f0;">
                        <option value="">All Careers</option>
                        <?php foreach ($careerOptions as $c): ?>
                            <option value="<?= h($c) ?>" <?= ($careerFilter === $c ? 'selected' : '') ?>><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" style="padding:10px; border-radius:10px; border:1px solid #e2e8f0;">
                        <option value="">All Status</option>
                        <option value="active" <?= ($statusFilter === 'active' ? 'selected' : '') ?>>Active</option>
                        <option value="expired" <?= ($statusFilter === 'expired' ? 'selected' : '') ?>>Expired</option>
                    </select>
                    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search job..." style="padding:10px; border-radius:10px; border:1px solid #e2e8f0; width:200px;">
                    <button type="submit" class="btn-filter" style="background:var(--side); color:white; border:none; padding:10px 20px; border-radius:10px; cursor:pointer;">Filter</button>
                </form>
            </div>

            <div id="addModal" class="modal-overlay <?= isset($_GET['add']) ? 'active' : '' ?>">
                <div class="modal-container" style="max-width: 700px; padding: 18px; border-radius: 12px;">
                    <button class="close-modal" onclick="closeModal('addModal')"><i data-lucide="x" size="18"></i></button>
                    <h3 style="margin-bottom:15px; font-size:18px; font-weight:800; color:var(--side);">Post Job Listing</h3>

                    <form method="post">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Career Category</label>
                                <input type="text" name="career_name" placeholder="e.g. IT" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Job Title</label>
                                <input type="text" name="title" placeholder="e.g. Developer" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Company</label>
                                <input type="text" name="company_name" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Location</label>
                                <input type="text" name="location" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                            </div>
                        </div>

                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Description</label>
                            <textarea name="description" rows="2" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px;" required></textarea>
                        </div>

                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Requirements</label>
                            <textarea name="requirements" rows="1" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px;"></textarea>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
                            <div><label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Email</label><input type="email" name="contact_email" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required></div>
                            <div><label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Phone</label><input type="text" name="contact_phone" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required></div>
                            <div><label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Apply URL</label><input type="url" name="apply_url" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required></div>
                        </div>

                        <div class="form-row-flex" style="margin-bottom:18px; display:flex; gap:12px; align-items:flex-end;">
                            <div style="flex: 1;">
                                <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Valid Until</label>
                                <input type="date" name="valid_until" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                            </div>
                            <label class="active-check-box" style="display:flex; align-items:center; gap:8px; height:38px; background:#f8fafc; border:1px solid #e2e8f0; padding:0 12px; border-radius:8px; cursor:pointer; margin:0; flex:1; justify-content:center;">
                                <input type="checkbox" name="is_active" checked style="width:15px; height:15px; accent-color:var(--accent);">
                                <span style="font-weight:700; font-size:12px; color:#475569;">Active</span>
                            </label>
                        </div>

                        <button type="submit" name="create" style="width:100%; background:var(--accent); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px;">Publish Listing</button>
                    </form>
                </div>
            </div>

            <?php if ($editing): ?>
                <div id="editModal" class="modal-overlay active">
                    <div class="modal-container" style="max-width: 700px; padding: 18px; border-radius: 12px; border-top: 5px solid var(--accent); max-height: 95vh; overflow-y: auto;">

                        <button type="button" class="close-modal" onclick="closeEditModal()">
                            <i data-lucide="x" size="18"></i>
                        </button>
                        </button>

                        <div style="margin-bottom:15px;">
                            <h3 style="font-size:18px; font-weight:800; color:var(--side); margin-bottom:2px;">Edit Job Information</h3>
                            <span style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:800;">RECORD ID: #<?= (int)$editing['id'] ?></span>
                        </div>

                        <form method="post">
                            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                                <div>
                                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Career Category</label>
                                    <input type="text" name="career_name" value="<?= h($editing['career_name']) ?>" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                                </div>
                                <div>
                                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Job Title</label>
                                    <input type="text" name="title" value="<?= h($editing['title']) ?>" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                                </div>
                                <div>
                                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Company</label>
                                    <input type="text" name="company_name" value="<?= h($editing['company_name']) ?>" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                                </div>
                                <div>
                                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Location</label>
                                    <input type="text" name="location" value="<?= h($editing['location']) ?>" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:13px;" required>
                                </div>
                            </div>

                            <div style="margin-bottom:10px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Description</label>
                                <textarea name="description" rows="2" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px;" required><?= h($editing['description']) ?></textarea>
                            </div>

                            <div style="margin-bottom:10px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Requirements</label>
                                <textarea name="requirements" rows="1" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; resize:none; font-size:13px;"><?= h($editing['requirements'] ?? '') ?></textarea>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
                                <div><label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Email</label><input type="email" name="contact_email" value="<?= h($editing['contact_email']) ?>" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required></div>
                                <div><label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Phone</label><input type="text" name="contact_phone" value="<?= h($editing['contact_phone']) ?>" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required></div>
                                <div><label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Apply URL</label><input type="url" name="apply_url" value="<?= h($editing['apply_url']) ?>" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required></div>
                            </div>

                            <div style="display:flex; gap:12px; align-items:flex-end; margin-bottom:18px;">
                                <div style="flex: 1;">
                                    <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Valid Until</label>
                                    <input type="date" name="valid_until" value="<?= h($editing['valid_until']) ?>" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; height:38px; font-size:12px;" required>
                                </div>
                                <label style="display:flex; align-items:center; gap:8px; height:38px; background:#f8fafc; border:1px solid #e2e8f0; padding:0 12px; border-radius:8px; cursor:pointer; margin:0; flex:1; justify-content:center;">
                                    <input type="checkbox" name="is_active" <?= ((int)$editing['is_active'] === 1) ? 'checked' : '' ?> style="width:15px; height:15px; accent-color:var(--side);">
                                    <span style="font-weight:700; font-size:12px; color:#475569;">Keep Active</span>
                                </label>
                            </div>

                            <button type="submit" name="update" style="width:100%; background:var(--side); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px; transition: opacity 0.2s;">Update Job Info</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <div class="job-list" style="display:grid; gap:20px; padding-bottom: 50px;">
                <?php if (!$jobs): ?>
                    <div style="text-align:center; padding:50px; background:white; border-radius:20px; border:1px dashed #cbd5e1;">
                        <i data-lucide="briefcase" size="48" style="color:#cbd5e1; margin-bottom:15px;"></i>
                        <p style="color:#64748b;">No job listings found.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($jobs as $j):
                    $isExpired = (int)$j['is_expired'];
                    $isActive = (int)$j['is_active'] && !$isExpired;
                ?>
                    <div class="question-card" style="background:white; padding:25px; border-radius:20px; border:1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px;">

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <div style="display:flex; gap:8px;">
                                <span style="background:#f1f5f9; color:#475569; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800;">
                                    <?= strtoupper(htmlspecialchars($j['career_name'])) ?>
                                </span>

                                <span style="background:#fffbeb; color:#f59e0b; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800;">
                                    <?= strtoupper(htmlspecialchars($j['location'])) ?>
                                </span>

                                <span style="background:#ecfdf5; color:#10b981; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800;">
                                    UNTIL: <?= date('d M Y', strtotime($j['valid_until'])) ?>
                                </span>

                                <span class="status-badge <?= $isActive ? 'status-active' : 'status-inactive' ?>">
                                    <?= $isExpired ? 'EXPIRED' : ($isActive ? 'ACTIVE' : 'DISABLED') ?>
                                </span>
                            </div>

                            <div style="font-size:12px; font-weight:600; color:#94a3b8;">ID: #<?= $j['id'] ?></div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <h3 style="font-size:16px; color:var(--side); font-weight:700; line-height:1.4; margin-bottom:4px;">
                                <?= htmlspecialchars($j['title']) ?>
                            </h3>
                            <p style="color:var(--accent); font-weight:600; font-size:14px;">
                                <?= htmlspecialchars($j['company_name']) ?>
                            </p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 20px; padding: 15px; background: #fbfcfd; border-radius: 12px; border: 1px solid #f1f5f9;">
                            <div>
                                <h4 style="font-size: 10px; text-transform: uppercase; color: #94a3b8; font-weight: 800; margin-bottom: 8px; letter-spacing: 0.5px;">Description</h4>
                                <div style="font-size:13px; line-height:1.5; color:#475569; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= nl2br(htmlspecialchars($j['description'])) ?>
                                </div>
                            </div>

                            <div>
                                <h4 style="font-size: 10px; text-transform: uppercase; color: #94a3b8; font-weight: 800; margin-bottom: 8px; letter-spacing: 0.5px;">Requirements</h4>
                                <div style="font-size:13px; line-height:1.5; color:#475569; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= isset($j['requirements']) ? nl2br(htmlspecialchars($j['requirements'])) : "Refer to description." ?>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:20px; border-top:1px solid #f1f5f9;">
                            <div style="display:flex; gap:15px;">
                                <a href="job-listing.php?edit=<?= $j['id'] ?>" class="action-btn btn-edit">
                                    <i data-lucide="pencil-line" size="16"></i> Edit
                                </a>
                                <form method="post" onsubmit="return confirmDelete(this);">
                                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                    <input type="hidden" name="delete" value="1">
                                    <button type="button" onclick="confirmDelete(this.form)" class="action-btn btn-delete">
                                        <i data-lucide="trash-2" size="16"></i> Delete
                                    </button>
                                </form>
                            </div>

                            <form method="post">
                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                <button name="toggle" class="action-btn btn-toggle" <?= $isExpired ? 'disabled' : '' ?>>
                                    <i data-lucide="<?= $isActive ? 'eye-off' : 'eye' ?>" size="16"></i>
                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
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

        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            if (window.location.search.includes('edit=')) window.location.href = 'job-listing.php';
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
                if (result.isConfirmed) form.submit();
            });
            return false;
        }
        <?php if (isset($_GET['ok'])): ?>
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
            });
        <?php endif; ?>
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