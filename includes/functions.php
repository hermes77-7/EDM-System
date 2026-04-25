<?php

function isAdmin() {
    return $_SESSION['user']['role'] === 'admin';
}

function isTeacher() {
    return $_SESSION['user']['role'] === 'teacher';
}

function isViewer() {
    return $_SESSION['user']['role'] === 'viewer';
}
?>