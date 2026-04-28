<?php

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function getUser() {
    return $_SESSION['user'] ?? null;
}

function getUserRole() {
    return $_SESSION['user']['role'] ?? null;
}

function isAdmin() {
    return getUserRole() === 'admin';
}

function isTeacher() {
    return getUserRole() === 'teacher';
}

function isViewer() {
    return getUserRole() === 'viewer';
}

function canUpload() {
    return isAdmin() || isTeacher();
}

function canManageUsers() {
    return isAdmin();
}

function canManageFolders() {
    return isAdmin();
}



function canViewVisibility($visibility) {
    if (!isset($_SESSION['user'])) return false;

    $role = $_SESSION['user']['role'];

    if ($visibility === 'public') return true;
    if ($visibility === 'teacher' && in_array($role, ['teacher','admin'])) return true;
    if ($visibility === 'admin' && $role === 'admin') return true;

    return false;
}

function canEditDocument($doc) {
    if (!isset($_SESSION['user'])) return false;

    $userId = $_SESSION['user']['id'];
    $role = $_SESSION['user']['role'];

    return ($role === 'admin' || $doc['uploaded_by'] == $userId);
}

function canDeleteDocument($doc) {
    return canEditDocument($doc); // same rule
}
