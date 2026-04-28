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

$name = trim($_POST["name"] ?? "");
$parentId = $_POST["parent_id"] ?? "";

if ($name === "") {
    header("Location: /edm-system/documents/index.php?folder_error=1");
    exit();
}

$createdBy = (int)$_SESSION['user']['id'];
$parentIdValue = ($parentId === "" || $parentId === "0") ? null : (int)$parentId;

if ($parentIdValue === null) {
    $stmt = $conn->prepare("INSERT INTO folders (name, parent_id, created_by) VALUES (?, NULL, ?)");
    $stmt->bind_param("si", $name, $createdBy);
} else {
    $stmt = $conn->prepare("INSERT INTO folders (name, parent_id, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $name, $parentIdValue, $createdBy);
}

$stmt->execute();

header("Location: /edm-system/documents/index.php");
exit();