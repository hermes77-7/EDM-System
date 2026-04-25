<?php
session_start();
include("../config/db.php");

if (isset($_SESSION['user'])) {
    header("Location: /edm-system/index.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST["name"]);
    $email = $conn->real_escape_string($_POST["email"]);
    $password = $_POST["password"];

    // Check if email exists
    $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($check->num_rows > 0) {
        $error = "Email already exists";
    } else if ($password !== $_POST["confirm_password"]) {
    $error = "Passwords do not match";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (name, email, password, role, is_active)
                VALUES ('$name', '$email', '$hashedPassword', 'viewer', 1)";

        if ($conn->query($sql)) {
    header("Location: login.php");
    exit();
        }else {
            $error = "Something went wrong";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | EDM</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary-green: #1a4a2a;
    --accent-green: #2a6a3a;
    --glass-bg: rgba(255, 255, 255, 0.85);
    --text-main: #1f2937;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    display: flex;
    height: 100vh;
    background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), 
                url('../assets/images/Login-Background.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
}

/* LEFT SIDE */
.left {
    flex: 1.2;
    display: flex;
    align-items: flex-end;
    padding: 60px;
}

.left-content h1 {
    color: white;
    font-size: 3rem;
    margin-bottom: 10px;
}

.left-content p {
    color: rgba(255,255,255,0.9);
}

/* RIGHT SIDE */
.right {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(5px);
    background: rgba(255,255,255,0.05);
}

.login-box {
    background: var(--glass-bg);
    backdrop-filter: blur(15px);
    width: 100%;
    max-width: 420px;
    padding: 50px 40px;
    border-radius: 28px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.login-box h2 {
    color: var(--primary-green);
    text-align: center;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    margin-bottom: 25px;
    color: #6b7280;
}

/* messages */
.error {
    background: #fef2f2;
    color: #dc2626;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    text-align: center;
}

.success {
    background: #ecfdf5;
    color: #065f46;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    text-align: center;
}

/* inputs */
.input-group {
    margin-bottom: 18px;
}

.input-group input {
    width: 100%;
    padding: 14px;
    border-radius: 14px;
    border: 1px solid #ddd;
}

/* button */
button {
    width: 100%;
    padding: 14px;
    background: var(--primary-green);
    color: white;
    border: none;
    border-radius: 14px;
    cursor: pointer;
}

button:hover {
    background: var(--accent-green);
}

/* link */
.link {
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
}

.link a {
    color: var(--primary-green);
    text-decoration: none;
    font-weight: 600;
}
</style>
</head>

<body>

<div class="left">
    <div class="left-content">
        <h1>Join EDM</h1>
        <p>Create an account to manage documents securely.</p>
    </div>
</div>

<div class="right">
    <div class="login-box">
        <h2>Create Account</h2>
        <p class="subtitle">Sign up to get started</p>

        <?php if ($error) echo "<div class='error'>$error</div>"; ?>
        <?php if ($success) echo "<div class='success'>$success</div>"; ?>

        <form method="POST">
            <div class="input-group">
                <input type="text" name="name" placeholder="Full Name" required>
            </div>

            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="input-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            
            <button type="submit">Create Account</button>
        </form>

        <div class="link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</div>

</body>
</html>