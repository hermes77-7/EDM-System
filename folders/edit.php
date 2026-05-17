<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!canManageFolders()) {
    http_response_code(403);
    die("Access denied");
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$error = "";

if ($id <= 0) {
    die("Invalid folder.");
}

$stmt = $conn->prepare("SELECT id, name, parent_id FROM folders WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$folder = $result->fetch_assoc();

if (!$folder) {
    die("Folder not found.");
}

$foldersResult = $conn->query("SELECT id, name, parent_id FROM folders ORDER BY name ASC");
$folders = [];
if ($foldersResult) {
    while ($row = $foldersResult->fetch_assoc()) {
        $folders[] = $row;
    }
}

$foldersResult = $conn->query("SELECT id, name, parent_id FROM folders ORDER BY name ASC");
$foldersByParent = [];

if ($foldersResult) {
    while ($row = $foldersResult->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int)$row['parent_id'];
        $foldersByParent[$row['parent_id']][] = $row;
    }
}

function isDescendant(array $folders, $possibleParentId, $folderId) {
    $map = [];
    foreach ($folders as $f) {
        $map[(int)$f['id']] = $f['parent_id'] === null ? null : (int)$f['parent_id'];
    }

    while ($possibleParentId !== null) {
        if ((int)$possibleParentId === (int)$folderId) {
            return true;
        }
        $possibleParentId = $map[(int)$possibleParentId] ?? null;
    }
    return false;
}


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


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $parentId = $_POST["parent_id"] ?? "";

    if ($name === "") {
        $error = "Folder name is required.";
    } else {
        $parentIdValue = ($parentId === "" || $parentId === "0") ? null : (int)$parentId;

        if ($parentIdValue !== null && (int)$parentIdValue === (int)$id) {
            $error = "A folder cannot be its own parent.";
        } elseif ($parentIdValue !== null && isDescendant($folders, $parentIdValue, $id)) {
            $error = "You cannot move a folder inside one of its subfolders.";
        } else {
            if ($parentIdValue === null) {
                $update = $conn->prepare("UPDATE folders SET name = ?, parent_id = NULL WHERE id = ?");
                $update->bind_param("si", $name, $id);
            } else {
                $update = $conn->prepare("UPDATE folders SET name = ?, parent_id = ? WHERE id = ?");
                $update->bind_param("sii", $name, $parentIdValue, $id);
            }

            if ($update->execute()) {
                header("Location: /edm-system/documents/index.php");
                exit();
            } else {
                $error = "Could not update folder.";
            }
        }
    }
}

$pageTitle = "Edit Folder | EDM System";
include("../includes/header.php");
?>

<div class="page-card">
    <h2 class="page-title">Edit Folder</h2>
    <p class="page-subtitle">Rename or move the folder.</p>

    <?php if ($error): ?>
        <div style="margin-top:16px; padding:14px; border-radius:12px; background:#fef2f2; color:#b91c1c;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="margin-top:22px; display:grid; gap:16px; max-width:520px;">
        <div>
            <label style="display:block; margin-bottom:8px; font-weight:600;">Folder Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($folder['name']); ?>"
                   style="width:100%; padding:14px 16px; border:1px solid var(--border); border-radius:14px;">
        </div>

        <div>
            <label style="display:block; margin-bottom:8px; font-weight:600;">Parent Folder</label>
            <select name="parent_id"
                    style="width:100%; padding:14px 16px; border:1px solid var(--border); border-radius:14px; background:white;">
                <option value="">Root folder</option>
                <?php 
                // Quick safety check: if the function doesn't exist, it won't crash the entire page layout
                if (function_exists('buildFolderOptions')) {
                    echo buildFolderOptions($foldersByParent, null, 0, $doc['folder_id'], $id);
                } else {
                    echo '<option value="" disabled>Error: Folder helper function missing</option>';
                }
                ?>
            </select>
        </div>

        <!-- Buttons Container with Explicit Formatting -->
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px; visibility:visible !important; display:flex !important;">
            <button type="submit" class="action-btn" 
                    style="background-color: #0b3d2e; color: #ffffff; padding: 12px 24px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Changes
            </button>
            
            <a href="/edm-system/documents/index.php" class="action-btn secondary" 
               style="background-color: #f3f4f6; color: #1f2937; padding: 12px 24px; border: 1px solid #d1d5db; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php include("../includes/footer.php"); ?>