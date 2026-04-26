<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loggeduser = $_SESSION['user'] ?? null;
$pageTitle = $pageTitle ?? 'EDM System';

function canUploadDocs() {
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['teacher', 'admin'], true);
}

function isAdminUser() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary-green: #0b3d2e; /* Dark Forest Green */
            --sidebar-black: #111111; /* Black */
            --bg-grey: #f5f7f6;       /* Grey Background */
            --white: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            
            --sidebar-width: 280px;
            --header-height: 75px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-grey);
            color: var(--text-main);
        }

        /* --- HEADER (Dark Forest Green) --- */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: var(--primary-green);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand-mark {
            font-size: 24px;
            background: rgba(255,255,255,0.1);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .brand-text h1 {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }

        .brand-text p {
            font-size: 11px;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .logout-btn {
            background: var(--white);
            color: var(--primary-green);
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #e8f3ef;
            transform: translateY(-1px);
        }

        /* --- SIDEBAR (Right Side - Black) --- */
        .sidebar {
            position: fixed;
            right: 0;
            top: var(--header-height);
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: var(--sidebar-black);
            color: var(--white);
            padding: 30px 20px;
            overflow-y: auto;
        }

        .sidebar-section h3 {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
            padding-left: 10px;
        }

        .nav-link {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 14px 16px;
            margin-bottom: 8px;
            border-radius: 12px;
            color: #999;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link i {
            font-size: 18px;
            margin-top: 2px;
        }

        .nav-link span {
            font-size: 14px;
            font-weight: 600;
            display: block;
            color: #eee;
        }

        .nav-link small {
            font-size: 11px;
            display: block;
            opacity: 0.5;
            font-weight: 400;
        }

        .nav-link:hover {
            background: #1a1a1a;
            color: var(--white);
        }

        /* --- CONTENT AREA --- */
        .content {
            margin-right: var(--sidebar-width);
            padding: calc(var(--header-height) + 40px) 40px 40px;
        }

        .card {
            background: var(--white);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 16px;
            line-height: 1.6;
        }

        .user-meta {
            text-align: right;
            margin-right: 20px;
            line-height: 1.4;
        }

        .user-meta strong { font-size: 14px; display: block; }
        .user-meta span { font-size: 12px; opacity: 0.8; }

        .action-btn {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .action-btn:hover {
            background: var(--accent-green);
            transform: translateY(-1px);
        }

        .action-btn.secondary {
            background: #111;
        }

        .action-btn.secondary:hover {
            background: #000;
        }

    </style>
</head>
<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-mark">
            <i class="fa-solid fa-folder-open"></i>
        </div>
        <div class="brand-text">
            <h1>EDM System</h1>
            <p>Document Management</p>
        </div>
    </div>

    <div style="display:flex; align-items:center;">
        <div class="user-meta">
            <strong><?php echo htmlspecialchars($loggeduser['name'] ?? 'Guest'); ?></strong>
            <span><?php echo htmlspecialchars($loggeduser['email'] ?? 'Not logged in'); ?></span>
        </div>
        <a class="logout-btn" href="/edm-system/auth/logout.php">
            <i class="fa-solid fa-power-off"></i>
            Logout
        </a>
    </div>
</header>

<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-section">
            <h3>MAIN MENU</h3>
            <a class="nav-link" href="/edm-system/index.php">
                <div>
                    <span>Dashboard</span>
                    <small>View recent activity</small>
                </div>
            </a>

            <a class="nav-link" href="/edm-system/documents/index.php">
                <div>
                    <span>Documents</span>
                    <small>Browse all files</small>
                </div>
            </a>

            <?php if (canUploadDocs()): ?>
            <a class="nav-link" href="/edm-system/documents/upload.php">
                <div>
                    <span>Upload</span>
                    <small>Upload Documents</small>
                </div>
            </a>
            <?php endif; ?>

            <?php if (isAdminUser()): ?>
            <a class="nav-link" href="/edm-system/users/index.php">
                <div>
                    <span>Admin Panel</span>
                    <small>User Control</small>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <div class="sidebar-section" style="margin-top: 40px;">
            <h3>SECURITY</h3>
            <div class="nav-link" style="cursor: default; opacity: 0.7;">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <span>Secure Mode</span>
                    <small>Active permissions</small>
                </div>
            </div>
        </div>
    </aside>

    <main class="content">
        <div class="content-inner">