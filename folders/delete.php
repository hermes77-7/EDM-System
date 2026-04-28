<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!canManageFolders()) {
    http_response_code(403);
    die("Access denied");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /edm-system/documents/index.php");
    exit();
}

$id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

if ($id <= 0) {
    die("Invalid folder.");
}

$stmt = $conn->prepare("SELECT id FROM folders WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Folder not found.");
}

$delete = $conn->prepare("DELETE FROM folders WHERE id = ?");
$delete->bind_param("i", $id);
$delete->execute();

header("Location: /edm-system/documents/index.php");
exit();