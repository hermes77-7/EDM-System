<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isAdmin()) {
    http_response_code(403);
    die("Access denied");
}

$pageTitle = "Admin Panel | EDM System";

function bind_stmt_params(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

/* Overall stats */
$totalUsers = 0;
$activeUsers = 0;
$teachers = 0;
$viewers = 0;

$totalRes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role <> 'admin'");
if ($totalRes) $totalUsers = (int)$totalRes->fetch_assoc()['total'];

$activeRes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role <> 'admin' AND is_active = 1");
if ($activeRes) $activeUsers = (int)$activeRes->fetch_assoc()['total'];

$teacherRes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'teacher'");
if ($teacherRes) $teachers = (int)$teacherRes->fetch_assoc()['total'];

$viewerRes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'viewer'");
if ($viewerRes) $viewers = (int)$viewerRes->fetch_assoc()['total'];

/* Filters */
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';

$where = ["role <> 'admin'"];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if (in_array($roleFilter, ['viewer', 'teacher'], true)) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

if ($statusFilter === 'enabled') {
    $where[] = "is_active = 1";
} elseif ($statusFilter === 'disabled') {
    $where[] = "is_active = 0";
}

$sql = "SELECT id, name, email, role, is_active FROM users WHERE " . implode(' AND ', $where) . " ORDER BY id DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error.");
}

bind_stmt_params($stmt, $types, $params);
$stmt->execute();
$users = $stmt->get_result();

include("../includes/header.php");
?>

<style>
    .admin-hero {
        background: var(--primary-green);
        color: white;
        padding: 40px;
        border-radius: 8px;
        margin-bottom: 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
    }

    .hero-text h2 {
        font-size: 32px;
        font-weight: 800;
        letter-spacing: -1px;
    }

    .hero-text p {
        opacity: 0.75;
        font-size: 15px;
        margin-top: 6px;
    }

    .hud-display {
        display: flex;
        gap: 22px;
        flex-wrap: wrap;
        background: rgba(0, 0, 0, 0.16);
        padding: 18px 24px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .hud-item {
        text-align: center;
        min-width: 78px;
    }

    .hud-val {
        display: block;
        font-size: 24px;
        font-weight: 800;
        color: white;
    }

    .hud-lbl {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: rgba(255,255,255,0.6);
    }

    .stream-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-bottom: 20px;
        padding: 0 4px;
        flex-wrap: wrap;
    }

    .toolbar-left {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .toolbar-left span {
        font-weight: 700;
        color: #6b7280;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .toolbar-left small {
        color: #6b7280;
    }

    .create-trigger {
        background: #111;
        color: white;
        padding: 12px 20px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: 0.25s;
        border: none;
    }

    .create-trigger:hover {
        transform: translateY(-2px);
        background: #000;
    }

    .filter-bar {
        background: white;
        border: 1px solid var(--border);
        border-radius: 2px ;
        border-color: black ;
        padding: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr auto;
        gap: 12px;
        margin-bottom: 18px;
    }

    .filter-field input,
    .filter-field select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 12px;
        outline: none;
        background: #fff;
        color: var(--text-main);
    }

    .filter-field input:focus,
    .filter-field select:focus {
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(11, 61, 46, 0.08);
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: stretch;
    }

    .filter-btn {
        border: none;
        border-radius: 12px;
        padding: 0 18px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        height: 46px;
    }

    .filter-btn.primary {
        background: var(--primary-green);
        color: white;
    }

    .filter-btn.secondary {
        background: #f3f4f6;
        color: #111;
    }

    .user-stream {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .stream-item {
        display: grid;
        grid-template-columns: 1.1fr 1.5fr .8fr 1fr 180px;
        align-items: center;
        gap: 12px;
        padding: 18px 20px;
        background: white;
        border-radius: 16px;
        transition: 0.25s ease;
        border: 1px solid transparent;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
    }

    .stream-item:hover {
        border-color: var(--primary-green);
        transform: translateY(-1px);
    }

    .u-name {
        font-weight: 700;
        color: #111;
        font-size: 16px;
    }

    .u-email {
        color: #6b7280;
        font-size: 14px;
    }

    .u-role-tag {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--primary-green);
        background: #f0f4f2;
        padding: 5px 12px;
        border-radius: 999px;
        width: fit-content;
    }

    .u-status-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .status-indicator.on {
        background: #10b981;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
    }

    .status-indicator.off {
        background: #ef4444;
    }

    .btn-group {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-action {
        width: 40px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: 0.2s;
        font-size: 15px;
    }

    .btn-edit {
        background: #f5f7f6;
        color: #111;
    }

    .btn-edit:hover {
        background: #111;
        color: white;
    }

    .btn-toggle {
        background: #f5f7f6;
        color: var(--primary-green);
    }

    .btn-toggle:hover {
        background: var(--primary-green);
        color: white;
    }

    .btn-delete {
        background: #fff1f2;
        color: #e11d48;
    }

    .btn-delete:hover {
        background: #e11d48;
        color: white;
    }

    @media (max-width: 1100px) {
        .stream-item {
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn-group {
            justify-content: flex-start;
        }

        .filter-bar {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 700px) {
        .filter-bar {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-container">
    <header class="admin-hero">
        <div class="hero-text">
            <h2>User Management</h2>
        </div>

        <div class="hud-display">
            <div class="hud-item">
                <span class="hud-lbl">Active</span>
                <span class="hud-val"><?php echo (int)$activeUsers; ?></span>
            </div>
            <div class="hud-item">
                <span class="hud-lbl">Teachers</span>
                <span class="hud-val"><?php echo (int)$teachers; ?></span>
            </div>
            <div class="hud-item">
                <span class="hud-lbl">Viewers</span>
                <span class="hud-val"><?php echo (int)$viewers; ?></span>
            </div>
        </div>
    </header>

    <div class="stream-toolbar">
        <div class="toolbar-left">
            <span>User Registry</span>
            <small><?php echo (int)$totalUsers; ?> non-admin account(s) found</small>
        </div>

        <a href="/edm-system/users/create.php" class="create-trigger">
            <i class="fa-solid fa-plus"></i> New User
        </a>
    </div>

    <form method="GET" class="filter-bar">
        <div class="filter-field">
            <input
                type="text"
                name="search"
                placeholder="Search by name or email"
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>

        <div class="filter-field">
            <select name="role">
                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                <option value="viewer" <?php echo $roleFilter === 'viewer' ? 'selected' : ''; ?>>Viewer</option>
                <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
            </select>
        </div>

        <div class="filter-field">
            <select name="status">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="enabled" <?php echo $statusFilter === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                <option value="disabled" <?php echo $statusFilter === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="filter-btn primary">
                <i class="fa-solid fa-filter"></i> Search
            </button>
            <a href="/edm-system/users/index.php" class="filter-btn secondary">
                <i class="fa-solid fa-rotate-right"></i> Reset
            </a>
        </div>
    </form>

    <div class="user-stream">
        <?php if ($users && $users->num_rows > 0): ?>
            <?php while ($row = $users->fetch_assoc()): ?>
                <div class="stream-item">
                    <div class="u-name"><?php echo htmlspecialchars($row['name']); ?></div>

                    <div class="u-email"><?php echo htmlspecialchars($row['email']); ?></div>

                    <div>
                        <span class="u-role-tag"><?php echo htmlspecialchars($row['role']); ?></span>
                    </div>

                    <div class="u-status-pill">
                        <span class="status-indicator <?php echo ((int)$row['is_active'] === 1) ? 'on' : 'off'; ?>"></span>
                        <span style="color: <?php echo ((int)$row['is_active'] === 1) ? '#111' : '#888'; ?>">
                            <?php echo ((int)$row['is_active'] === 1) ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>

                    <div class="btn-group">
                        <a title="Edit Profile" href="/edm-system/users/edit.php?id=<?php echo (int)$row['id']; ?>" class="btn-action btn-edit">
                            <i class="fa-solid fa-pen-nib"></i>
                        </a>

                        <form method="POST" action="/edm-system/users/toggle_status.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" class="btn-action btn-toggle" title="Switch Status">
                                <i class="fa-solid fa-power-off"></i>
                            </button>
                        </form>

                        <form method="POST" action="/edm-system/users/delete.php" style="display:inline;" onsubmit="return confirm('Permanent delete?');">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" class="btn-action btn-delete" title="Delete Account">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:60px; color:#888;">
                No users found for the current filters.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include("../includes/footer.php"); ?>