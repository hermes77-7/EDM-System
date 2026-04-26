<?php
include("../includes/auth.php");
$pageTitle = "Dashboard | EDM System";
include("../includes/header.php");
include("../includes/functions.php");

if (!canUpload()) {
    die("Access denied");
}
?>



<div class="card">
    <h2 class="page-title">Upload</h2>
    <p class="page-subtitle">
       uploads page
    </p>
</div>

<?php include("../includes/footer.php"); ?>