<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: /edm-system/auth/login.php");
    exit();
}

/**
 * Checks if the current user has folder management privileges (admin or teacher).
 */
if (!function_exists('canManageFolders')) {
    function canManageFolders() {
        return isAdmin() || isTeacher();
    }
}

/**
 * Checks if the current user is allowed to view a given document
 * based on its visibility setting.
 */
if (!function_exists('canViewDocument')) {
    function canViewDocument(array $doc): bool {
        if (function_exists('canViewVisibility') && array_key_exists('visibility', $doc)) {
            return canViewVisibility($doc['visibility']);
        }
        return true;
    }
}

/**
 * Checks if the current user can edit a given document.
 * Admins can edit any document; other users can only edit their own uploads.
 */
if (!function_exists('canEditDocument')) {
    function canEditDocument(array $doc): bool {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        $role       = $_SESSION['user']['role'] ?? '';
        $userId     = (int)($_SESSION['user']['id'] ?? 0);
        $uploadedBy = (int)($doc['uploaded_by'] ?? 0);

        return $role === 'admin' || $uploadedBy === $userId;
    }
}

/**
 * Checks if the current user can delete a given document.
 * Delegates to canEditDocument — same permission rules apply.
 */
if (!function_exists('canDeleteDocument')) {
    function canDeleteDocument(array $doc): bool {
        return canEditDocument($doc);
    }
}

/**
 * Binds an array of parameters to a prepared MySQLi statement.
 * Needed because mysqli_stmt::bind_param() requires parameters by reference.
 */
if (!function_exists('edm_bind_params')) {
    function edm_bind_params(mysqli_stmt $stmt, string $types, array &$params): void {
        $refs   = [];
        $refs[] = $types;
        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

// ── Search state 

$searchMode     = ($_GET['mode'] ?? 'normal') === 'deep' ? 'deep' : 'normal';

$normalName     = trim($_GET['name'] ?? '');
$normalFolderId = ($_GET['folder_id'] ?? '') !== '' ? (int)$_GET['folder_id'] : '';
$normalDocType  = trim($_GET['document_type'] ?? '');
$deepQuery      = trim($_GET['q'] ?? '');

$searching = false;

$pageTitle = "Document Repository | EDM System";

// Load folders

$foldersResult  = $conn->query("SELECT id, name, parent_id FROM folders ORDER BY name ASC, id ASC");
$foldersByParent = [];
$allFolders      = [];

if ($foldersResult) {
    while ($row = $foldersResult->fetch_assoc()) {
        $row['id']        = (int)$row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int)$row['parent_id'];
        $foldersByParent[$row['parent_id']][] = $row;
        $allFolders[] = $row;
    }
}

// Distinct document types for the filter dropdown 

$docTypes    = [];
$typesResult = $conn->query(
    "SELECT DISTINCT document_type FROM documents
     WHERE document_type IS NOT NULL AND document_type <> ''
     ORDER BY document_type ASC"
);
if ($typesResult) {
    while ($row = $typesResult->fetch_assoc()) {
        $docTypes[] = $row['document_type'];
    }
}

// ── Build document query 

$where  = [];
$params = [];
$types  = '';

if ($searchMode === 'deep') {
    if ($deepQuery !== '') {
        $searching = true;
        $like      = '%' . $deepQuery . '%';

        $where[]  = "(d.title LIKE ? OR d.document_type LIKE ? OR d.keywords LIKE ? OR d.description LIKE ? OR d.original_name LIKE ? OR f.name LIKE ?)";
        $params   = [$like, $like, $like, $like, $like, $like];
        $types    = 'ssssss';
    }
} else {
    if ($normalName !== '') {
        $searching = true;
        $where[]   = "d.title LIKE ?";
        $params[]  = '%' . $normalName . '%';
        $types    .= 's';
    }

    if ($normalFolderId !== '') {
        $searching = true;
        $where[]   = "d.folder_id = ?";
        $params[]  = $normalFolderId;
        $types    .= 'i';
    }

    if ($normalDocType !== '') {
        $searching = true;
        $where[]   = "d.document_type = ?";
        $params[]  = $normalDocType;
        $types    .= 's';
    }
}

$sql = "
    SELECT d.id, d.title, d.folder_id, d.original_name, d.file_path, d.created_at,
           d.description, d.keywords, d.document_type, d.visibility, d.uploaded_by,
           u.name AS uploaded_by_name,
           f.name AS folder_name
    FROM documents d
    LEFT JOIN users u ON u.id = d.uploaded_by
    LEFT JOIN folders f ON f.id = d.folder_id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY d.created_at DESC";

$stmt       = $conn->prepare($sql);
$docsResult = false;

if ($stmt) {
    if (!empty($params)) {
        edm_bind_params($stmt, $types, $params);
    }
    $stmt->execute();
    $docsResult = $stmt->get_result();
}

// Group visible documents by folder 

$docsByFolder     = [];
$totalVisibleDocs = 0;

if ($docsResult) {
    while ($row = $docsResult->fetch_assoc()) {
        if (!canViewDocument($row)) {
            continue;
        }

        $row['id']        = (int)$row['id'];
        $row['folder_id'] = $row['folder_id'] === null ? null : (int)$row['folder_id'];

        $folderKey = $row['folder_id'];
        $docsByFolder[$folderKey][] = $row;
        $totalVisibleDocs++;
    }
}

/**
 * Recursively checks whether a folder (or any of its descendants) contains
 * at least one visible document. Used to hide empty branches during search.
 */
if (!function_exists('edm_folder_has_content')) {
    function edm_folder_has_content(array $foldersByParent, array $docsByFolder, $parentId = null): bool
    {
        if (!empty($docsByFolder[$parentId])) {
            return true;
        }

        if (empty($foldersByParent[$parentId])) {
            return false;
        }

        foreach ($foldersByParent[$parentId] as $folder) {
            $folderId = (int)$folder['id'];
            if (edm_folder_has_content($foldersByParent, $docsByFolder, $folderId)) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Recursively renders the folder tree and its documents as HTML.
 * Folders are rendered as <details> elements; documents appear inline beneath
 * their parent folder. During search, empty branches are hidden automatically.
 */
if (!function_exists('edm_render_folder_tree')) {
    function edm_render_folder_tree(
        array $foldersByParent,
        array $docsByFolder,
        $parentId    = null,
        int   $level = 0,
        bool  $searching = false
    ): string {
        $html = '';

        if (!empty($foldersByParent[$parentId])) {
            foreach ($foldersByParent[$parentId] as $folder) {
                $folderId = (int)$folder['id'];

                if ($searching && !edm_folder_has_content($foldersByParent, $docsByFolder, $folderId)) {
                    continue;
                }

                $openAttr = $searching ? ' open' : ($level < 1 ? ' open' : '');

                $html .= '<div class="tree-node" style="--level: ' . $level . '">';
                $html .= '  <details class="folder-details"' . $openAttr . '>';
                $html .= '      <summary class="node-row folder-row">';
                $html .= '          <div class="node-content">';
                $html .= '              <div class="folder-icon-box"><i class="fa-solid fa-folder"></i></div>';
                $html .= '              <div>';
                $html .= '                  <div class="node-title">' . htmlspecialchars($folder['name']) . '</div>';
                $html .= '                  <div class="node-meta">Directory // L' . $level . '</div>';
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
                    $html .= '              <button type="button" onclick="toggleSubFolderForm(' . $folderId . ')" style="background:none; color:#999; font-size:11px; border:none; cursor:pointer;">Cancel</button>';
                    $html .= '          </form>';
                    $html .= '      </div>';
                }

                $html .= '      <div class="node-children">';
                $html .= edm_render_folder_tree($foldersByParent, $docsByFolder, $folderId, $level + 1, $searching);
                $html .= '      </div>';

                $html .= '  </details>';
                $html .= '</div>';
            }
        }

        if (!empty($docsByFolder[$parentId])) {
            foreach ($docsByFolder[$parentId] as $doc) {
                $folderLabel = htmlspecialchars($doc['folder_name'] ?? 'Root');

                $html .= '<div class="node-row file-row" style="--level: ' . $level . '">';
                $html .= '  <div class="node-content">';
                $html .= '      <div class="file-indicator"></div>';
                $html .= '      <div>';
                $html .= '          <div class="node-title">' . htmlspecialchars($doc['title']) . '</div>';
                $html .= '          <div class="node-meta">Folder: ' . $folderLabel . ' // ' . htmlspecialchars($doc['uploaded_by_name'] ?? 'System') . ' // ' . htmlspecialchars($doc['created_at']) . '</div>';
                $html .= '      </div>';
                $html .= '  </div>';

                $html .= '  <div class="node-actions">';

                $html .= '  <a href="/edm-system/documents/view.php?id=' . $doc['id'] . '" 
                    class="action-circle" title="View">
                    <i class="fa-solid fa-eye"></i>
                    </a>';

                $html .= '      <a href="/edm-system/' . htmlspecialchars($doc['file_path']) . '" class="action-circle" title="Download" download>';
                $html .= '          <i class="fa-solid fa-download"></i>';
                $html .= '      </a>';

                if (canEditDocument($doc)) {
                    $html .= '      <a href="/edm-system/documents/edit.php?id=' . $doc['id'] . '" class="action-circle" title="Edit">';
                    $html .= '          <i class="fa-solid fa-pen-nib"></i>';
                    $html .= '      </a>';
                }

                if (canDeleteDocument($doc)) {
                    $html .= '      <form method="POST" action="/edm-system/documents/delete.php" style="display:inline;" onsubmit="return confirm(\'Delete this file?\');">';
                    $html .= '          <input type="hidden" name="id" value="' . $doc['id'] . '">';
                    $html .= '          <button type="submit" class="action-circle del" title="Delete">';
                    $html .= '              <i class="fa-solid fa-trash-can"></i>';
                    $html .= '          </button>';
                    $html .= '      </form>';
                }

                $html .= '  </div>';
                $html .= '</div>';
            }
        }

        return $html;
    }
}

$treeHtml = edm_render_folder_tree($foldersByParent, $docsByFolder, null, 0, $searching);

include("../includes/header.php");
?>

<style>
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
        margin-bottom: 22px;
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

    .top-actions {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .btn-init {
        background: var(--deep-black);
        color: #fff;
        padding: 14px 28px;
        border-radius: 10px;
        font-weight: 800;
        font-size: 12px;
        border: none;
        cursor: pointer;
        text-transform: uppercase;
    }

    .btn-init:hover {
        background: var(--forest);
    }

    .search-panel {
        background: #fff;
        border: 1px solid #000000;
        border-radius: 50px;
        padding: 16px;
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        margin-bottom: 12px;
    }

    .search-field {
        flex: 1;
        min-width: 200px;
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--bg-soft);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 0 14px;
        height: 50px;
    }

    .search-field i {
        color: var(--forest);
    }

    .search-field input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        font-size: 14px;
    }

    /* Styles the folder and type dropdowns to match the text inputs */
    .search-field select {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        font-size: 14px;
        appearance: none;
        cursor: pointer;
    }

    /* Show/hide fields depending on active search mode — set server-side on load */
    .search-field[data-mode="deep"] {
        display: <?php echo $searchMode === 'deep' ? 'flex' : 'none'; ?>;
    }

    .search-field[data-mode="normal"] {
        display: <?php echo $searchMode === 'normal' ? 'flex' : 'none'; ?>;
    }

    .mode-toggle,
    .search-submit,
    .search-clear {
        height: 50px;
        border-radius: 12px;
        padding: 0 18px;
        font-weight: 800;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: .04em;
        border: none;
    }

    .mode-toggle {
        background: #111;
        color: #fff;
    }

    .mode-toggle.deep {
        background: var(--forest);
    }

    .search-submit {
        background: var(--forest);
        color: #fff;
    }

    /* Clear button: subtle by default, turns red on hover */
    .search-clear {
        background: #fff;
        color: #6b7280;
        border: 1px solid var(--border);
        /* Hidden until at least one filter is active */
        display: none;
    }

    .search-clear:hover {
        border-color: #e11d48;
        color: #e11d48;
    }

    .search-meta {
        margin: 0 0 18px;
        color: #6b7280;
        font-size: 12px;
        font-family: monospace;
        letter-spacing: .5px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .tree-stream {
        border-radius: 24px;
        padding: 25px;
    }

    .node-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 20px;
        margin-bottom: 6px;
        border-radius: 7px;
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

    .node-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .folder-icon-box {
        width: 44px;
        height: 44px;
        background: var(--forest);
        color: #fff;
        display: grid;
        place-items: center;
        border-radius: 12px;
        font-size: 16px;
    }

    .file-indicator {
        width: 4px;
        height: 26px;
        background: var(--deep-black);
        border-radius: 4px;
    }

    .node-title {
        font-weight: 700;
        font-size: 15px;
        color: var(--deep-black);
    }

    .node-meta {
        font-size: 10px;
        color: #999;
        text-transform: uppercase;
        font-weight: 600;
        margin-top: 2px;
    }

    .node-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

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
        transition: background 0.2s;
        text-decoration: none;
        font-size: 14px;
    }

    .action-circle:hover {
        background: var(--forest);
    }

    .action-circle.del:hover {
        background: #e11d48;
    }

    .folder-details summary {
        list-style: none;
        outline: none;
        cursor: pointer;
    }

    .folder-details summary::-webkit-details-marker {
        display: none;
    }

    .stream-form {
        background: var(--bg-soft);
        padding: 12px;
        border-radius: 12px;
        display: flex;
        gap: 10px;
        margin: 5px 0 15px calc((var(--level) + 1) * 35px);
        max-width: 420px;
        border: 1px solid var(--border);
    }

    .stream-form input {
        border: 1px solid var(--border);
        outline: none;
        flex: 1;
        border-radius: 8px;
        padding-left: 12px;
        font-size: 13px;
        height: 38px;
    }

    .stream-form button {
        background: var(--forest);
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        height: 38px;
    }

    .tree-empty {
        padding: 24px;
        color: #6b7280;
        text-align: center;
    }

    @media (max-width: 760px) {
        .node-row {
            align-items: flex-start;
            flex-direction: column;
            gap: 12px;
        }

        .node-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .stream-form {
            margin-left: 0;
            flex-wrap: wrap;
        }

        .search-panel {
            flex-direction: column;
            align-items: stretch;
        }

        .search-field {
            min-width: 0;
        }
    }
</style>

<div class="admin-container">
    <header class="vault-header">
        <h2>Document<br>Vault</h2>
        <p>Search and browse your folders and documents.</p>
    </header>

    <div class="top-actions">
        <?php if (canManageFolders()): ?>
            <button type="button" onclick="toggleRootFolderForm()" class="btn-init">
                <i class="fa-solid fa-folder-plus"></i> Add Root Folder
            </button>
        <?php endif; ?>
    </div>

    <form method="GET" id="searchForm" class="search-panel" style="flex:1;">
            <input type="hidden" name="mode" id="searchMode" value="<?php echo htmlspecialchars($searchMode); ?>">

            <!-- Deep search: single free-text field matching all document fields -->
            <div class="search-field" data-mode="deep">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                    type="text"
                    name="q"
                    value="<?php echo htmlspecialchars($deepQuery); ?>"
                    placeholder="Type a word or phrase..."
                >
            </div>

            <!-- Normal search: document name text filter -->
            <div class="search-field" data-mode="normal">
                <i class="fa-solid fa-file-lines"></i>
                <input
                    type="text"
                    name="name"
                    value="<?php echo htmlspecialchars($normalName); ?>"
                    placeholder="Document name"
                >
            </div>

            <!-- Normal search: folder filter dropdown -->
            <div class="search-field" data-mode="normal">
                <i class="fa-solid fa-folder"></i>
                <select name="folder_id">
                    <option value="">All folders</option>
                    <?php foreach ($allFolders as $folder): ?>
                        <option
                            value="<?php echo (int)$folder['id']; ?>"
                            <?php echo ($normalFolderId !== '' && (int)$normalFolderId === (int)$folder['id']) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($folder['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Normal search: document type filter dropdown -->
            <div class="search-field" data-mode="normal">
                <i class="fa-solid fa-tags"></i>
                <select name="document_type">
                    <option value="">All types</option>
                    <?php foreach ($docTypes as $type): ?>
                        <option
                            value="<?php echo htmlspecialchars($type); ?>"
                            <?php echo ($normalDocType === $type) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Toggles between normal (guided filters) and deep (free-text) search modes -->
            <button type="button" id="modeToggleBtn" class="mode-toggle <?php echo $searchMode === 'deep' ? 'deep' : ''; ?>" onclick="toggleSearchMode()">
                <?php echo $searchMode === 'deep' ? 'Deep Search' : 'Normal Search'; ?>
            </button>

            <!-- Resets all filter inputs and submits; only visible when a filter is active -->
            <button type="button" id="clearBtn" class="search-clear" onclick="clearFilters()">
                <i class="fa-solid fa-xmark"></i> Clear
            </button>

            <!-- Explicit submit — no auto-search, user triggers results manually -->
            <button type="submit" class="search-submit">
                <i class="fa-solid fa-magnifying-glass"></i> Filter
            </button>
        </form>

    <div class="search-meta">
        <span>
            <?php if ($searching): ?>
                <?php echo (int)$totalVisibleDocs; ?> result(s) found in <?php echo strtoupper($searchMode); ?> mode.
            <?php else: ?>
                Browse all folders and documents. Toggle deep search for broader matching.
            <?php endif; ?>
        </span>
        <span>
            Normal search: name, folder, type &nbsp;·&nbsp; Deep search: title, type, folder, keywords, description, filename
        </span>
    </div>

    <?php if (canManageFolders()): ?>
        <div id="root-folder-form" style="display:none; margin-bottom: 25px;">
            <form method="POST" action="/edm-system/folders/create.php" class="stream-form" style="margin-left:0;">
                <input type="hidden" name="parent_id" value="">
                <input type="text" name="name" placeholder="New folder designation..." required>
                <button type="submit">Create</button>
                <button type="button" onclick="toggleRootFolderForm()" style="background:none; color:#999; font-size:11px; border:none; cursor:pointer;">Cancel</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="tree-stream">
        <?php if (!empty($treeHtml)): ?>
            <?php echo $treeHtml; ?>
        <?php else: ?>
            <div class="tree-empty">
                <?php echo $searching ? 'No matching documents found.' : 'No folders or documents yet.'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    /**
     * Toggles the root folder creation form open or closed.
     */
    function toggleRootFolderForm() {
        const box = document.getElementById('root-folder-form');
        if (!box) return;
        box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
    }

    /**
     * Toggles the subfolder creation form for a specific folder open or closed.
     * @param {number} folderId - The ID of the parent folder.
     */
    function toggleSubFolderForm(folderId) {
        const box = document.getElementById('subfolder-form-' + folderId);
        if (!box) return;
        box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
    }

    /**
     * Switches between normal (guided filters) and deep (free-text) search modes.
     * Updates the toggle button label, shows/hides the relevant fields,
     * and updates the Clear button visibility. Does NOT auto-submit —
     * the user must click Filter to apply.
     */
    function toggleSearchMode() {
        const modeInput = document.getElementById('searchMode');
        const button    = document.getElementById('modeToggleBtn');

        if (!modeInput || !button) return;

        modeInput.value = (modeInput.value === 'normal') ? 'deep' : 'normal';

        button.textContent = (modeInput.value === 'deep') ? 'Deep Search' : 'Normal Search';
        button.classList.toggle('deep', modeInput.value === 'deep');

        updateFieldVisibility(modeInput.value);
        updateClearBtn();
    }

    /**
     * Shows fields belonging to the active search mode and hides the others.
     * @param {string} mode - Either 'normal' or 'deep'.
     */
    function updateFieldVisibility(mode) {
        document.querySelectorAll('[data-mode="normal"]').forEach(el => {
            el.style.display = mode === 'normal' ? 'flex' : 'none';
        });
        document.querySelectorAll('[data-mode="deep"]').forEach(el => {
            el.style.display = mode === 'deep' ? 'flex' : 'none';
        });
    }

    /**
     * Shows the Clear button if any filter input has a non-empty value,
     * hides it otherwise. Called on page load and whenever an input changes.
     */
    function updateClearBtn() {
        const q       = document.querySelector('[name="q"]')?.value ?? '';
        const name    = document.querySelector('[name="name"]')?.value ?? '';
        const folder  = document.querySelector('[name="folder_id"]')?.value ?? '';
        const docType = document.querySelector('[name="document_type"]')?.value ?? '';

        const hasFilters = q || name || folder || docType;
        const btn = document.getElementById('clearBtn');
        if (btn) btn.style.display = hasFilters ? 'block' : 'none';
    }

    /**
     * Clears all filter inputs back to their empty/default state
     * and submits the form to return to the full unfiltered document tree.
     */
    function clearFilters() {
        const form = document.getElementById('searchForm');
        if (!form) return;

        form.querySelector('[name="q"]').value             = '';
        form.querySelector('[name="name"]').value          = '';
        form.querySelector('[name="folder_id"]').value     = '';
        form.querySelector('[name="document_type"]').value = '';

        form.submit();
    }

    // On page load: sync field visibility and show Clear if filters are already active.
    document.addEventListener('DOMContentLoaded', function () {
        const mode = document.getElementById('searchMode')?.value || 'normal';
        updateFieldVisibility(mode);
        updateClearBtn();

        // Wire updateClearBtn to all filter inputs so the button appears as the user types/selects.
        document.querySelectorAll('[name="q"], [name="name"], [name="folder_id"], [name="document_type"]')
            .forEach(el => el.addEventListener('input', updateClearBtn));
        document.querySelectorAll('[name="folder_id"], [name="document_type"]')
            .forEach(el => el.addEventListener('change', updateClearBtn));
    });
</script>

<?php include("../includes/footer.php"); ?>