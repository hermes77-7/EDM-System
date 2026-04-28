<?php
include("../includes/auth.php");
include("../config/db.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$id = (int)($_POST['id'] ?? 0);

$res = $conn->query("SELECT uploaded_by, file_path FROM documents WHERE id = $id");

if (!$res || $res->num_rows === 0) {
    die("Document not found");
}

$doc = $res->fetch_assoc();

$userId = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

if ($role !== 'admin' && $doc['uploaded_by'] != $userId) {
    die("Unauthorized");
}

/* delete file from disk */
$filePath = __DIR__ . "/../" . $doc['file_path'];
if (file_exists($filePath)) {
    unlink($filePath);
}

/* delete from db */
$conn->query("DELETE FROM documents WHERE id = $id");

header("Location: /edm-system/documents/index.php");
exit();