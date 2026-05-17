<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: /edm-system/auth/login.php");
    exit();
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

$pageTitle = "Deep Search | EDM System";

$uploadedDate = trim($_GET['date_uploaded'] ?? '');
$uploadedBy   = ($_GET['uploaded_by'] ?? '') !== '' ? (int)$_GET['uploaded_by'] : '';
$title        = trim($_GET['title'] ?? '');
$docType      = trim($_GET['document_type'] ?? '');
$description  = trim($_GET['description'] ?? '');
$keywords     = trim($_GET['keywords'] ?? '');

$hasSearch =
    $uploadedDate !== '' ||
    $uploadedBy !== '' ||
    $title !== '' ||
    $docType !== '' ||
    $description !== '' ||
    $keywords !== '';

/* Load users for the uploader dropdown */
$users = [];
$usersResult = $conn->query("SELECT id, name, email FROM users ORDER BY name ASC");
if ($usersResult) {
    while ($row = $usersResult->fetch_assoc()) {
        $users[] = $row;
    }
}

/* Load document types for the type dropdown */
$docTypes = [];
$typesResult = $conn->query("
    SELECT DISTINCT document_type
    FROM documents
    WHERE document_type IS NOT NULL AND document_type <> ''
    ORDER BY document_type ASC
");
if ($typesResult) {
    while ($row = $typesResult->fetch_assoc()) {
        $docTypes[] = $row['document_type'];
    }
}

$results = [];
$totalResults = 0;

if ($hasSearch) {
    $where = [];
    $params = [];
    $types = '';

    if ($uploadedDate !== '') {
        $where[] = "DATE(d.created_at) = ?";
        $params[] = $uploadedDate;
        $types .= 's';
    }

    if ($uploadedBy !== '') {
        $where[] = "d.uploaded_by = ?";
        $params[] = $uploadedBy;
        $types .= 'i';
    }

    if ($title !== '') {
        $where[] = "d.title LIKE ?";
        $params[] = '%' . $title . '%';
        $types .= 's';
    }

    if ($docType !== '') {
        $where[] = "d.document_type = ?";
        $params[] = $docType;
        $types .= 's';
    }

    if ($description !== '') {
        $where[] = "d.description LIKE ?";
        $params[] = '%' . $description . '%';
        $types .= 's';
    }

    if ($keywords !== '') {
        $where[] = "d.keywords LIKE ?";
        $params[] = '%' . $keywords . '%';
        $types .= 's';
    }

    $sql = "
        SELECT d.id, d.title, d.file_path, d.created_at, d.description, d.keywords,
               d.document_type, d.visibility, d.uploaded_by, d.folder_id,
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

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if (!empty($params)) {
            edm_bind_params($stmt, $types, $params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!canViewDocument($row)) {
                    continue;
                }

                $results[] = $row;
                $totalResults++;
            }
        }
    }
}

include("../includes/header.php");
?>

<style>
.search-page-card {
    
    padding: 28px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.search-page-title {
    font-size: 28px;
    font-weight: 800;
    color: var(--primary-green);
    margin-bottom: 6px;
}

.search-page-subtitle {
    color: var(--text-muted);
    margin-bottom: 22px;
    font-size: 15px;
}

.search-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 18px;
}

.search-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-main);
}

.search-group input,
.search-group select {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid var(--border);
    outline: none;
    background: white;
    font-size: 14px;
}

.search-group input:focus,
.search-group select:focus {
    border-color: var(--primary-green);
    box-shadow: 0 0 0 3px rgba(11, 61, 46, 0.08);
}

.search-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.results-header {
    margin: 24px 0 14px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.results-count {
    font-size: 13px;
    color: var(--text-muted);
    font-family: monospace;
}

.result-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.result-item {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04);
}

.result-item:hover {
    background: #f8faf9;
}

.result-left {
    min-width: 0;
}

.result-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--deep-black);
    margin-bottom: 4px;
}

.result-meta {
    font-size: 12px;
    color: #6b7280;
    line-height: 1.6;
}

.result-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.result-btn {
    text-decoration: none;
    background: var(--deep-black);
    color: white;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.result-btn:hover {
    background: var(--forest);
}

.empty-box {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 22px;
    color: var(--text-muted);
}

@media (max-width: 760px) {
    .search-grid {
        grid-template-columns: 1fr;
    }

    .result-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .result-actions {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>

<div class="page-card">
    <div class="search-page-card">
        <div class="search-page-title">Deep Search</div>
        <div class="search-page-subtitle">
            Search documents using any combination of metadata fields.
        </div>

        <form method="GET">
            <div class="search-grid">
                <div class="search-group">
                    <label for="date_uploaded">Date Uploaded</label>
                    <input type="date" id="date_uploaded" name="date_uploaded" value="<?php echo htmlspecialchars($uploadedDate); ?>">
                </div>

                <div class="search-group">
                    <label for="uploaded_by">Uploaded By</label>
                    <select id="uploaded_by" name="uploaded_by">
                        <option value="">Any user</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo (int)$user['id']; ?>" <?php echo ($uploadedBy !== '' && (int)$uploadedBy === (int)$user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" placeholder="Document title">
                </div>

                <div class="search-group">
                    <label for="document_type">Type</label>
                    <select id="document_type" name="document_type">
                        <option value="">Any type</option>
                        <?php foreach ($docTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($docType === $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($description); ?>" placeholder="Description text">
                </div>

                <div class="search-group">
                    <label for="keywords">Keywords</label>
                    <input type="text" id="keywords" name="keywords" value="<?php echo htmlspecialchars($keywords); ?>" placeholder="Keywords">
                </div>
            </div>

            <div class="search-actions">
                <button type="submit" class="action-btn">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search
                </button>

                <a href="/edm-system/documents/index.php" class="action-btn secondary">
                    Back to documents
                </a>
            </div>
        </form>

        <div class="results-header">
            <div class="results-count">
                <?php if ($hasSearch): ?>
                    <?php echo (int)$totalResults; ?> result(s) found
                <?php else: ?>
                    Fill at least one field and search.
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hasSearch): ?>
            <?php if (!empty($results)): ?>
                <div class="result-list">
                    <?php foreach ($results as $doc): ?>
                        <div class="result-item">
                            <div class="result-left">
                                <div class="result-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                                <div class="result-meta">
                                    Uploaded by: <?php echo htmlspecialchars($doc['uploaded_by_name'] ?? 'System'); ?><br>
                                    Date: <?php echo htmlspecialchars($doc['created_at']); ?><br>
                                    Folder: <?php echo htmlspecialchars($doc['folder_name'] ?? 'Root'); ?><br>
                                    Type: <?php echo htmlspecialchars($doc['document_type'] ?? 'N/A'); ?>
                                </div>
                            </div>

                            <div class="result-actions">
                                <a class="result-btn" href="/edm-system/documents/view.php?id=<?php echo (int)$doc['id']; ?>">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                                <a class="result-btn" href="/edm-system/<?php echo htmlspecialchars($doc['file_path']); ?>" download>
                                    <i class="fa-solid fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-box">
                    No matching documents found.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-box">
                Use any field above to start searching.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include("../includes/footer.php"); ?>