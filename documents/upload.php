<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: /edm-system/auth/login.php");
    exit();
}

$pageTitle = "Upload Document | EDM System";

$role = $_SESSION['user']['role'] ?? 'viewer';
if (!in_array($role, ['teacher', 'admin'], true)) {
    http_response_code(403);
    die("Access denied");
}

$error = "";

function buildFolderOptionsRecursive(array $foldersByParent, $parentId = null, $level = 0, $selectedId = null): string
{
    $html = '';

    if (!empty($foldersByParent[$parentId])) {
        foreach ($foldersByParent[$parentId] as $folder) {
            $folderId = (int)$folder['id'];
            $selected = ((string)$selectedId !== '' && (int)$selectedId === $folderId) ? ' selected' : '';
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);

            $html .= '<option value="' . $folderId . '"' . $selected . '>'
                . $indent . htmlspecialchars($folder['name']) . '</option>';

            $html .= buildFolderOptionsRecursive($foldersByParent, $folderId, $level + 1, $selectedId);
        }
    }

    return $html;
}

/**
 * Load folders for the dropdown
 */
$foldersResult = $conn->query("SELECT id, name, parent_id FROM folders ORDER BY name ASC, id ASC");
$foldersByParent = [];

if ($foldersResult) {
    while ($row = $foldersResult->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int)$row['parent_id'];
        $foldersByParent[$row['parent_id']][] = $row;
    }
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $folderId = $_POST["folder_id"] ?? "";
    $description = trim($_POST["description"] ?? "");
    $keywords = trim($_POST["keywords"] ?? "");
    $documentType = trim($_POST["document_type"] ?? "");
    $uploadedBy = (int)$_SESSION['user']['id'];

    if ($title === "") {
        $error = "Document title is required.";
    } elseif (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Please choose a valid file.";
    } else {
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'ppt', 'pptx'];
        $originalName = $_FILES["file"]["name"];
        $tmpName = $_FILES["file"]["tmp_name"];
        $fileSize = (int)$_FILES["file"]["size"];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            $error = "File type not allowed.";
        } else {
            $uploadDir = __DIR__ . "/../uploads";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $safeName = uniqid("doc_", true) . "." . $extension;
            $destination = $uploadDir . "/" . $safeName;
            $relativePath = "uploads/" . $safeName;

            if (!move_uploaded_file($tmpName, $destination)) {
                $error = "Could not save the uploaded file.";
            } else {
                $folderIdValue = ($folderId === "" || $folderId === "0") ? null : (int)$folderId;

                if ($folderIdValue === null) {
                    $stmt = $conn->prepare("
                        INSERT INTO documents
                        (title, file_path, uploaded_by, folder_id, original_name, mime_type, file_size, description, keywords, document_type)
                        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)
                    ");
                    $mimeType = $_FILES["file"]["type"] ?? '';
                    $stmt->bind_param(
                        "ssississs",
                        $title,
                        $relativePath,
                        $uploadedBy,
                        $originalName,
                        $mimeType,
                        $fileSize,
                        $description,
                        $keywords,
                        $documentType
                    );
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO documents
                        (title, file_path, uploaded_by, folder_id, original_name, mime_type, file_size, description, keywords, document_type)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $mimeType = $_FILES["file"]["type"] ?? '';
                    $stmt->bind_param(
                        "ssiississs",
                        $title,
                        $relativePath,
                        $uploadedBy,
                        $folderIdValue,
                        $originalName,
                        $mimeType,
                        $fileSize,
                        $description,
                        $keywords,
                        $documentType
                    );
                }

                if ($stmt->execute()) {
                    header("Location: /edm-system/documents/index.php?uploaded=1");
                    exit();
                } else {
                    $error = "Database insert failed.";
                }
            }
        }
    }
}

include("../includes/header.php");
?>

<style>
.upload-panel {
   
    padding: 28px;
}

.upload-head {
    margin-bottom: 22px;
}

.upload-head h2 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-green);
    margin-bottom: 6px;
}

.upload-head p {
    color: var(--text-muted);
}

.upload-form {
    display: grid;
    gap: 16px;
    max-width: 760px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 14px;
    color: var(--text-main);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid var(--border);
    outline: none;
    background: white;
    font-size: 14px;
    transition: 0.2s;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary-green);
    box-shadow: 0 0 0 3px rgba(11, 61, 46, 0.08);
}

.file-box {
    padding: 18px;
    border: 1px dashed #cfd8d3;
    border-radius: 14px;
    background: #f8faf9;
}

.file-box input[type="file"] {
    padding: 10px 0;
    border: none;
    background: transparent;
}

.help-text {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 6px;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.action-btn.secondary {
    background: #111;
}

.action-btn.secondary:hover {
    background: #000;
}

@media (max-width: 760px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-card">
    <div class="upload-head">
        <h2>Upload Document</h2>
        <p>Save a file into a folder </p>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="upload-panel">
        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <div class="form-group">
                <label for="title">Document Title</label>
                <input type="text" id="title" name="title" placeholder="Example: Meeting Minutes" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="folder_id">Folder</label>
                    <select id="folder_id" name="folder_id">
                        <option value="">Root folder</option>
                        <?php echo buildFolderOptionsRecursive($foldersByParent); ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="document_type">Document Type</label>
                    <select id="document_type" name="document_type">
                        <option value="">Select type</option>
                        <option value="report">Report</option>
                        <option value="memo">Memo</option>
                        <option value="note">Note</option>
                        <option value="form">Form</option>
                        <option value="presentation">Presentation</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="file">Choose File</label>
                <div class="file-box">
                    <input type="file" id="file" name="file" required>
                    <div class="help-text">
                        Allowed: PDF, DOC, DOCX, XLS, XLSX, TXT, PNG, JPG, JPEG, GIF, PPT, PPTX
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Short description of the document"></textarea>
            </div>

            <div class="form-group">
                <label for="keywords">Keywords</label>
                <input type="text" id="keywords" name="keywords" placeholder="Example: finance, 2026, invoice, meeting">
            </div>

            <div class="form-actions">
                <button type="submit" class="action-btn">
                    <i class="fa-solid fa-upload"></i>
                    Upload Document
                </button>

                <a href="/edm-system/documents/index.php" class="action-btn secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
