<?php
session_start();

if (isset($_SESSION['user'])) {
    header("Location: /edm-system/dashboard/index.php");
    exit();
}

$pageTitle = "EDM System | Electronic Document Management";

include("includes/landing_header.php");
?>

<!-- NAVBAR -->

<header class="navbar">

    <div class="logo">

        <div class="logo-box">
            <i class="fa-solid fa-folder-open"></i>
        </div>

        <div class="logo-text">
            <h1>EDM System</h1>
        </div>

    </div>

    <nav class="nav-links">
        <a href="#">Home</a>
        <a href="#features">Features</a>
        <a href="#">Security</a>
    </nav>

    <div class="nav-actions">

        <a href="/edm-system/auth/login.php" class="btn btn-outline">
            <i class="fa-solid fa-right-to-bracket"></i>
            Login
        </a>

        <a href="/edm-system/auth/register.php" class="btn btn-primary">
            <i class="fa-solid fa-user-plus"></i>
            Get Started
        </a>

    </div>

</header>

<!-- HERO -->

<section class="hero">

    <div class="hero-left">

        <div class="hero-badge">
            <i class="fa-solid fa-shield-halved"></i>
            Secure • Reliable • Efficient
        </div>

        <h1 class="hero-title">
            Smart Document <br>
            Management <span>System</span>
        </h1>

        <p class="hero-text">
            Organize, store and manage your documents securely in one place.
            Access information quickly, collaborate efficiently and ensure
            data integrity with a modern EDM platform.
        </p>

        <div class="hero-actions">

            <a href="/edm-system/auth/register.php"
               class="hero-btn hero-btn-primary">

                <i class="fa-solid fa-rocket"></i>
                Get Started

            </a>

            <a href="/edm-system/auth/login.php"
               class="hero-btn hero-btn-secondary">

                <i class="fa-solid fa-user"></i>
                Login

            </a>

        </div>

        <div class="hero-features">

            <div class="hero-feature">
                <i class="fa-solid fa-lock"></i>
                Secure Access
            </div>

            <div class="hero-feature">
                <i class="fa-solid fa-cloud"></i>
                Cloud Storage
            </div>

            <div class="hero-feature">
                <i class="fa-solid fa-users"></i>
                Role Based Access
            </div>

        </div>

    </div>

    <!-- RIGHT -->

    <div class="hero-right">

        
    </div>

</section>

<!-- FEATURES -->

<section class="features" id="features">

    <div class="section-title">

        <span>POWERFUL FEATURES</span>

        <h2>Everything you need to manage documents</h2>

        <p>
            A complete solution for modern document storage,
            organization, search and collaboration.
        </p>

    </div>

    <div class="feature-grid">

        <div class="feature-card">

            <div class="feature-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <h3>Easy Search</h3>

            <p>
                Find documents instantly using advanced search filters.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">
                <i class="fa-solid fa-folder"></i>
            </div>

            <h3>Folder Structure</h3>

            <p>
                Organize all files inside structured folders and categories.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">
                <i class="fa-solid fa-upload"></i>
            </div>

            <h3>Secure Upload</h3>

            <p>
                Upload and store multiple document formats safely.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">
                <i class="fa-solid fa-users"></i>
            </div>

            <h3>User Roles</h3>

            <p>
                Control permissions with role-based access management.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <h3>Data Protection</h3>

            <p>
                Enterprise-grade security for all your sensitive documents.
            </p>

        </div>

    </div>

</section>

<!-- FOOTER -->

<footer class="footer">

    <div>
        ©️ <?php echo date('Y'); ?> EDM System — All rights reserved.
    </div>

    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms</a>
        <a href="#">Support</a>
    </div>

</footer>

<?php include("includes/landing_footer.php"); ?>