<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

$id = (int)($_GET['id'] ?? 0);

$res = $conn->query("
    SELECT d.*, u.name AS uploaded_by_name 
    FROM documents d
    LEFT JOIN users u ON u.id = d.uploaded_by
    WHERE d.id = $id
");

if (!$res || $res->num_rows === 0) {
    die("Document not found");
}

$doc = $res->fetch_assoc();

/* visibility check (if you implemented it) */
if (!canViewVisibility($doc['visibility'] ?? 'public')) {
    die("Access denied");
}

include("../includes/header.php");
?>

<div class="page-card">
    <h2><?php echo htmlspecialchars($doc['title']); ?></h2>

    <div style="margin:15px 0; color:#666;">
        Uploaded by: <strong><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></strong><br>
        Date: <?php echo $doc['created_at']; ?><br>
        Type: <?php echo htmlspecialchars($doc['document_type'] ?? 'N/A'); ?><br>
        Size: <?php echo round(($doc['file_size'] ?? 0)/1024,2); ?> KB<br>
        Visibility: <?php echo htmlspecialchars($doc['visibility'] ?? 'public'); ?>
    </div>

    <?php if (!empty($doc['description'])): ?>
        <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($doc['description'])); ?></p>
    <?php endif; ?>

    <?php if (!empty($doc['keywords'])): ?>
        <p><strong>Keywords:</strong> <?php echo htmlspecialchars($doc['keywords']); ?></p>
    <?php endif; ?>

    <hr style="margin:25px 0;">

    <!-- FILE PREVIEW -->
    <?php
    $file = htmlspecialchars($doc['file_path']);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    ?>

    <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
        <img src="/edm-system/<?php echo $file; ?>" style="max-width:100%; border-radius:10px;">
    
    <?php elseif ($ext === 'pdf'): ?>
        <iframe src="/edm-system/<?php echo $file; ?>" width="100%" height="600px"></iframe>
    
    <?php else: ?>
        <p>Preview not available. You can download the file.</p>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="/edm-system/<?php echo $file; ?>" class="action-btn">
            <i class="fa-solid fa-download"></i> Download
        </a>
    </div>
</div>

<?php include("../includes/footer.php"); ?>