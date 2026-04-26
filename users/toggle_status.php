<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isAdmin()) {
    http_response_code(403);
    die("Access denied");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /edm-system/users/index.php");
    exit();
}

$id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

if ($id <= 0) {
    die("Invalid user.");
}

$stmt = $conn->prepare("SELECT id, role, is_active FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

if ($user["role"] === "admin") {
    die("Admin users cannot be modified here.");
}

$newStatus = ((int)$user["is_active"] === 1) ? 0 : 1;

$update = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
$update->bind_param("ii", $newStatus, $id);
$update->execute();

header("Location: /edm-system/users/index.php");
exit();