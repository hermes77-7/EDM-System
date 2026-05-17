<?php
// 1. Core includes (No HTML output)
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

// 2. Authentication check (Must happen before header.php)
if (!isset($_SESSION['user'])) {
    header("Location: /edm-system/auth/login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$res = $conn->query("SELECT * FROM documents WHERE id = $id");

if (!$res || $res->num_rows === 0) {
    die("Document not found");
}

$doc = $res->fetch_assoc();

/* PERMISSION CHECK */
if (!canEditDocument($doc)) {
    http_response_code(403);
    die("Unauthorized");
}

/* LOAD FOLDERS */
$foldersResult = $conn->query("SELECT id, name, parent_id FROM folders ORDER BY name ASC");
$foldersByParent = [];

if ($foldersResult) {
    while ($row = $foldersResult->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int)$row['parent_id'];
        $foldersByParent[$row['parent_id']][] = $row;
    }
}

/* BUILD FOLDER DROPDOWN */
function buildFolderOptions(
    $foldersByParent,
    $parentId = null,
    $level = 0,
    $selectedId = null,
    $excludeId = null
) {
    $html = '';

    if (!empty($foldersByParent[$parentId])) {

        foreach ($foldersByParent[$parentId] as $folder) {

            $id = (int)$folder['id'];

            // Prevent selecting itself as parent
            if ($excludeId !== null && $id === (int)$excludeId) {
                continue;
            }

            $selected = ($selectedId == $id) ? 'selected' : '';

            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);

            $html .= '<option value="' . $id . '" ' . $selected . '>'
                  . $indent . htmlspecialchars($folder['name'])
                  . '</option>';

            $html .= buildFolderOptions(
                $foldersByParent,
                $id,
                $level + 1,
                $selectedId,
                $excludeId
            );
        }
    }

    return $html;
}

/* HANDLE UPDATE (Processes safely before any HTML is sent) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $keywords = trim($_POST['keywords']);
    $folderId = $_POST['folder_id'] === '' ? null : (int)$_POST['folder_id'];

    $visibility = $doc['visibility'];

    if ($_SESSION['user']['role'] === 'admin') {
        $visibility = $_POST['visibility'] ?? 'public';
    }

    $stmt = $conn->prepare("
        UPDATE documents 
        SET title=?, description=?, keywords=?, folder_id=?, visibility=? 
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssisi",
        $title,
        $description,
        $keywords,
        $folderId,
        $visibility,
        $id
    );

    if ($stmt->execute()) {
        header("Location: /edm-system/documents/index.php?updated=1");
        exit();
    }
}

// 3. Set page variables and finally include the visual header
$pageTitle = "Edit Document | EDM System";
include("../includes/header.php"); 
?>

<style>
.edit-container {
    max-width: 800px;
}

.edit-card {

    padding: 30px;
}

.edit-title {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary-green);
    margin-bottom: 5px;
}

.edit-sub {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 13px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    font-size: 14px;
    outline: none;
    transition: 0.2s;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary-green);
    box-shadow: 0 0 0 3px rgba(11, 61, 46, 0.08);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.btn-primary {
    background: var(--primary-green);
    color: white;
    border: none;
    padding: 12px 18px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary:hover {
    background: #145c43;
}

.btn-secondary {
    background: #111;
    color: white;
    padding: 12px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
}

.btn-secondary:hover {
    background: #000;
}
</style>

<div class="edit-container">
    <div class="edit-card">

        <div class="edit-title">Edit Document</div>
        <div class="edit-sub">Modify metadata and location</div>

        <form method="POST">

            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($doc['title']); ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description"><?php echo htmlspecialchars($doc['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Keywords</label>
                <input type="text" name="keywords" value="<?php echo htmlspecialchars($doc['keywords']); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Folder (Location)</label>
                    <select name="folder_id">
                        <option value="">Root</option>
                        <?php echo buildFolderOptions($foldersByParent, null, 0, $doc['folder_id']); ?>
                    </select>
                </div>

                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <div class="form-group">
                    <label>Visibility</label>
                    <select name="visibility">
                        <option value="public" <?php if($doc['visibility']=='public') echo 'selected'; ?>>Public</option>
                        <option value="teacher" <?php if($doc['visibility']=='teacher') echo 'selected'; ?>>Teachers</option>
                        <option value="admin" <?php if($doc['visibility']=='admin') echo 'selected'; ?>>Admins</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>

                <a href="/edm-system/documents/index.php" class="btn-secondary">
                    Cancel
                </a>
            </div>

        </form>

    </div>
</div>

<?php include("../includes/footer.php"); ?>