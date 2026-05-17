<?php
include("../config/db.php");
include("../includes/functions.php");
include("../includes/header.php");

$latestDocs = [];

$result = $conn->query("
    SELECT d.id, d.title, d.file_path, d.created_at,
           d.visibility, d.uploaded_by,
           u.name AS uploaded_by_name
    FROM documents d
    LEFT JOIN users u ON u.id = d.uploaded_by
    ORDER BY d.created_at DESC
    LIMIT 10
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (function_exists('canViewDocument') && !canViewDocument($row)) {
            continue;
        }
        $latestDocs[] = $row;
    }
}
?>

<style>
.dashboard-card {
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.dashboard-card h3 {
    margin-bottom: 15px;
    font-size: 18px;
    font-weight: 800;
}

.latest-docs {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.doc-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid transparent;
    transition: 0.2s;
    gap: 12px;
}

.doc-item:hover {
    background: #f8faf9;
    border-color: #eaeeec;
}

.doc-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0; /* allow text to truncate */
}

.doc-title {
    font-weight: 600;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.doc-meta {
    font-size: 11px;
    color: #888;
    margin-top: 2px;
}

.doc-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.doc-actions a {
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 6px;
    background: #111;
    color: #fff;
    white-space: nowrap;
}

.doc-actions a:hover {
    background: #0b3d2e;
}

/* ── MOBILE ── */
@media (max-width: 600px) {
    .doc-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .doc-title {
        white-space: normal;
    }

    .doc-actions {
        width: 100%;
    }

    .doc-actions a {
        flex: 1;
        text-align: center;
        padding: 8px 10px;
    }

    .dashboard-card {
        padding: 14px 10px;
    }

    .dashboard-card h3 {
        font-size: 16px;
    }
}
</style>

<div class="card">
    <h2 class="page-title">Dashboard</h2>
    <p class="page-subtitle">
        Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong>.
        Select an option from the sidebar.
    </p>
</div>

<div class="dashboard-card">
    <h3>Latest Documents</h3>

    <?php if (!empty($latestDocs)): ?>
        <div class="latest-docs">
            <?php foreach ($latestDocs as $doc): ?>
                <div class="doc-item">
                    <div class="doc-info">
                        <div class="doc-title">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </div>
                        <div class="doc-meta">
                            <?php echo htmlspecialchars($doc['uploaded_by_name'] ?? 'System'); ?>
                            · <?php echo htmlspecialchars($doc['created_at']); ?>
                        </div>
                    </div>

                    <div class="doc-actions">
                        <a href="/edm-system/documents/view.php?id=<?php echo $doc['id']; ?>">View</a>
                        <a href="/edm-system/<?php echo htmlspecialchars($doc['file_path']); ?>" download>Download</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No documents available.</p>
    <?php endif; ?>
</div>

<?php include("../includes/footer.php"); ?>