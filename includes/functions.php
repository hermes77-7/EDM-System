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