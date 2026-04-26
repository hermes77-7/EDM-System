<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: /edm-system/auth/login.php");
    exit();
}

include_once __DIR__ . "/../config/db.php";

$userId = $_SESSION['user']['id'] ?? null;

if ($userId) {
    $stmt = $conn->prepare("SELECT id, name, email, role, is_active FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $freshUser = $result->fetch_assoc();

    if (!$freshUser || (int)$freshUser['is_active'] !== 1) {
        session_destroy();
        header("Location: /edm-system/auth/login.php?disabled=1");
        exit();
    }

    $_SESSION['user'] = $freshUser;
}