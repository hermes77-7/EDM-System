<?php
include("includes/auth.php");
$pageTitle = "Dashboard | EDM System";
include("includes/header.php");
?>

<div class="card">
    <h2 class="page-title">Dashboard</h2>
    <p class="page-subtitle">
        Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong>. 
        Select an option from the sidebar to manage or view your secure documents.
    </p>
</div>

<?php include("includes/footer.php"); ?>