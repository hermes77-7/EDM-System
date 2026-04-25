<?php
session_start();

if (isset($_SESSION['user'])) {
    header("Location: /edm-system/index.php");
    exit();
}

include("../config/db.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE email = '$email' AND is_active = 1";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            $_SESSION["user"] = $user;
            header("Location: ../index.php");
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found or disabled";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDM Login | Secure Access</title>
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
            /* Using a high-quality placeholder if your local image is missing */
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), 
                        url('../assets/images/Login-Background.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            overflow: hidden;
        }

        /* LEFT SIDE - Branding */
        .left {
            flex: 1.2;
            display: flex;
            align-items: flex-end;
            padding: 60px;
            z-index: 2;
        }

        .left-content {
            animation: fadeInUp 1s ease-out;
        }

        .left-content h1 {
            color: white;
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .left-content p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            max-width: 400px;
            line-height: 1.6;
        }

        /* RIGHT SIDE - Form Section */
        .right {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            backdrop-filter: blur(5px);
            background: rgba(255, 255, 255, 0.05);
        }

        .login-box {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            width: 100%;
            max-width: 420px;
            padding: 50px 40px;
            border-radius: 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInRight 0.8s ease-out;
        }

        .login-box h2 {
            color: var(--primary-green);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* ERROR MESSAGE */
        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #fee2e2;
            text-align: center;
        }

        .input-group {
            margin-bottom: 22px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #4b5563;
            margin-left: 4px;
        }

        .input-group input {
            width: 100%;
            padding: 15px 20px;
            font-size: 15px;
            border: 1.5px solid #e5e7eb;
            border-radius: 16px;
            outline: none;
            background: white;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(26, 74, 42, 0.1);
            transform: translateY(-1px);
        }

        button[type="submit"] {
            background: var(--primary-green);
            color: white;
            border: none;
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(26, 74, 42, 0.2);
        }

        button[type="submit"]:hover {
            background: var(--accent-green);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 74, 42, 0.3);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

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
        <h1>EDM System</h1>
        <p>Electrinic Document Management System.</p>
    </div>
</div>

<div class="right">
    <div class="login-box">
        <h2>Welcome Back</h2>
        <p class="subtitle">Please enter your details to sign in</p>

        <?php if (isset($error) && $error) echo "<div class='error'>$error</div>"; ?>

        <form method="POST">
            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="name@company.com" required>
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit">Sign In</button>

        </form>

        <div class="link">
             Don’t have an account? <a href="register.php">Sign up</a>
            </div>

    </div>
</div>

</body>
</html>
