<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: /edm-system/auth/login.php");
    exit();
}

$pageTitle = "Document Repository | EDM System";
include("../includes/header.php");

/**
 * DATA ACQUISITION
 */
$foldersResult = $conn->query("
    SELECT id, name, parent_id, visibility 
    FROM folders 
    ORDER BY name ASC
");

$foldersByParent = [];

if ($foldersResult) {
    while ($row = $foldersResult->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int)$row['parent_id'];
        $foldersByParent[$row['parent_id']][] = $row;
    }
}

$docsResult = $conn->query("
    SELECT d.id, d.title, d.folder_id, d.visibility, d.original_name, d.file_path, d.created_at,
           u.name AS uploaded_by_name
    FROM documents d
    LEFT JOIN users u ON u.id = d.uploaded_by
    ORDER BY d.created_at DESC
");

$docsByFolder = [];

if ($docsResult) {
    while ($row = $docsResult->fetch_assoc()) {
        $folderId = $row['folder_id'] === null ? null : (int)$row['folder_id'];
        $row['id'] = (int)$row['id'];
        $docsByFolder[$folderId][] = $row;
    }
}

/**
 * TREE RENDERER
 */
function renderFolderTree(array $foldersByParent, array $docsByFolder, $parentId = null, $level = 0): string
{
    $html = '';

    if (isset($foldersByParent[$parentId])) {
        foreach ($foldersByParent[$parentId] as $folder) {

        if (!canViewVisibility($folder['visibility'])) {
            continue;
            }

            $folderId = (int)$folder['id'];

            $html .= '<div class="tree-node" style="--level: ' . $level . '">';
            $html .= '  <details class="folder-details" ' . ($level < 1 ? 'open' : '') . '>';
            $html .= '      <summary class="node-row folder-row">';
            $html .= '          <div class="node-content">';
            $html .= '              <div class="folder-icon-box"><i class="fa-solid fa-folder"></i></div>';
            $html .= '              <div>';
            $html .= '                  <div class="node-title">' . htmlspecialchars($folder['name']) . '</div>';
            $html .= '              </div>';
            $html .= '          </div>';

            $html .= '          <div class="node-actions" onclick="event.stopPropagation();">';
            if (canManageFolders()) {
                $html .= '              <button type="button" class="action-circle" onclick="toggleSubFolderForm(' . $folderId . ')" title="New Subfolder"><i class="fa-solid fa-plus"></i></button>';
                $html .= '              <a class="action-circle" href="/edm-system/folders/edit.php?id=' . $folderId . '" title="Edit"><i class="fa-solid fa-pen-nib"></i></a>';
                $html .= '              <form method="POST" action="/edm-system/folders/delete.php" style="display:inline;" onsubmit="return confirm(\'Delete this folder and all its contents?\');">';
                $html .= '                  <input type="hidden" name="id" value="' . $folderId . '">';
                $html .= '                  <button type="submit" class="action-circle del" title="Delete"><i class="fa-solid fa-trash-can"></i></button>';
                $html .= '              </form>';
            }
            $html .= '          </div>';
            $html .= '      </summary>';

            if (canManageFolders()) {
                $html .= '      <div id="subfolder-form-' . $folderId . '" class="inline-form-wrap" style="display:none;">';
                $html .= '          <form method="POST" action="/edm-system/folders/create.php" class="stream-form">';
                $html .= '              <input type="hidden" name="parent_id" value="' . $folderId . '">';
                $html .= '              <input type="text" name="name" placeholder="Name sub-directory..." required>';
                $html .= '              <button type="submit">Create</button>';
                $html .= '          </form>';
                $html .= '      </div>';
            }

            $html .= '      <div class="node-children">';
            $html .= renderFolderTree($foldersByParent, $docsByFolder, $folderId, $level + 1);
            $html .= '      </div>';

            $html .= '  </details>';
            $html .= '</div>';
        }
    }

    if (isset($docsByFolder[$parentId])) {
        foreach ($docsByFolder[$parentId] as $doc) {

        if (!canViewVisibility($doc['visibility'])) {
            continue;
        }
            $docId = (int)$doc['id'];

            $html .= '<div class="node-row file-row" style="--level: ' . $level . '">';
            $html .= '  <div class="node-content">';
            $html .= '      <div class="file-indicator"></div>';
            $html .= '      <div>';
            $html .= '          <div class="node-title">' . htmlspecialchars($doc['title']) . '</div>';
            $html .= '          <div class="node-meta">' . htmlspecialchars($doc['uploaded_by_name'] ?? 'System') . ' // ' . htmlspecialchars($doc['created_at']) . '</div>';
            $html .= '      </div>';
            $html .= '  </div>';
$html .= '<div class="node-actions">';

/* VIEW (NEW) */
$html .= '  <a href="/edm-system/documents/view.php?id=' . $doc['id'] . '" 
                class="action-circle" title="View">
                <i class="fa-solid fa-eye"></i>
            </a>';

/* DOWNLOAD */
$html .= '  <a href="' . htmlspecialchars($doc['file_path']) . '" 
                class="action-circle" title="Download" download>
                <i class="fa-solid fa-download"></i>
            </a>';

/* EDIT */
if (canEditDocument($doc)) {
    $html .= '  <a href="/edm-system/documents/edit.php?id=' . $doc['id'] . '" 
                    class="action-circle" title="Edit">
                    <i class="fa-solid fa-pen-nib"></i>
                </a>';
}

/* DELETE */
if (canDeleteDocument($doc)) {
    $html .= '  <form method="POST" action="/edm-system/documents/delete.php" 
                        style="display:inline;" 
                        onsubmit="return confirm(\'Delete this file?\');">
                    <input type="hidden" name="id" value="' . $doc['id'] . '">
                    <button type="submit" class="action-circle del" title="Delete">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </form>';
}

$html .= '</div>';
        }
    }

    return $html;
}
?>

<style>
/* YOUR DESIGN LEFT UNTOUCHED */
:root {
    --forest: #0b3d2e;
    --deep-black: #111;
    --border: #eaeeec;
    --bg-soft: #f8faf9;
}

.vault-header {
    padding: 50px 0 30px;
    border-left: 5px solid var(--forest);
    padding-left: 30px;
    margin-bottom: 40px;
}

.vault-header h2 {
    font-size: 2.8rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: -2px;
    color: var(--deep-black);
    line-height: 0.9;
    margin: 0;
}

.vault-header p {
    font-family: monospace;
    font-size: 11px;
    color: var(--forest);
    margin-top: 10px;
    letter-spacing: 1px;
}

.tree-stream {
    background: #fff;
    border-radius: 24px;
    padding: 25px;
    border: 1px solid var(--border);
}

.node-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    margin-bottom: 6px;
    border-radius: 14px;
    background: #fff;
    margin-left: calc(var(--level) * 35px);
    position: relative;
    border: 1px solid transparent;
}

.node-row::before {
    content: "";
    position: absolute;
    left: -18px;
    top: -12px;
    bottom: 50%;
    width: 18px;
    border-left: 2px solid var(--border);
    border-bottom: 2px solid var(--border);
    border-bottom-left-radius: 10px;
}

.tree-node[style*="--level: 0"] > .folder-details > .node-row::before { display: none; }

.node-row:hover {
    background: var(--bg-soft);
    border-color: var(--border);
}

.node-content { display: flex; align-items: center; gap: 16px; }

.folder-icon-box {
    width: 44px;
    height: 44px;
    background: var(--forest);
    color: #fff;
    display: grid;
    place-items: center;
    border-radius: 12px;
}

.file-indicator {
    width: 4px;
    height: 26px;
    background: var(--deep-black);
    border-radius: 4px;
}

.node-title { font-weight: 700; font-size: 15px; }
.node-meta { font-size: 10px; color: #999; text-transform: uppercase; }

.node-actions { display: flex; gap: 10px; }

.action-circle {
    width: 38px;
    height: 38px;
    background: var(--deep-black);
    color: #fff;
    border-radius: 50%;
    display: grid;
    place-items: center;
    border: none;
    cursor: pointer;
    text-decoration: none;
}

.action-circle:hover { background: var(--forest); }
.action-circle.del:hover { background: #e11d48; }

.stream-form {
    background: var(--bg-soft);
    padding: 12px;
    border-radius: 12px;
    display: flex;
    gap: 10px;
    margin: 5px 0 15px calc((var(--level) + 1) * 35px);
    border: 1px solid var(--border);
}

.stream-form input { flex:1; border-radius:8px; padding-left:10px; }

.btn-init {
    background: var(--deep-black);
    color: #fff;
    padding: 14px 28px;
    border-radius: 10px;
    font-weight: 800;
    border: none;
    cursor: pointer;
}
</style>

<div class="admin-container">
    <header class="vault-header">
        <h2>Document<br>Vault</h2>

        <?php if (canManageFolders()): ?>
            <button type="button" onclick="toggleRootFolderForm()" class="btn-init" style="margin-top:20px;">
                <i class="fa-solid fa-folder-plus"></i> Add Root Folder
            </button>
        <?php endif; ?>
    </header>

    <?php if (canManageFolders()): ?>
        <div id="root-folder-form" style="display:none; margin-bottom:25px;">
            <form method="POST" action="/edm-system/folders/create.php" class="stream-form" style="margin-left:0;">
                <input type="hidden" name="parent_id" value="">
                <input type="text" name="name" placeholder="New folder designation..." required>
                <button type="submit">Create</button>
                <button type="button" onclick="toggleRootFolderForm()">Cancel</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="tree-stream">
        <?php echo renderFolderTree($foldersByParent, $docsByFolder, null); ?>
    </div>
</div>

<script>
function toggleRootFolderForm() {
    const box = document.getElementById('root-folder-form');
    if (!box) return;
    box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
}

function toggleSubFolderForm(folderId) {
    const box = document.getElementById('subfolder-form-' + folderId);
    if (!box) return;
    box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
}
</script>

<?php include("../includes/footer.php"); ?>