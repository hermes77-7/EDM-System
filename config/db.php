<?php
$conn = new mysqli("localhost", "root", "", "edm_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>