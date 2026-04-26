<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isAdmin()) {
    die("Access denied");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $role = $_POST["role"];

    if ($name === "" || $email === "" || $password === "") {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email.";
    } else {
        // Check duplicate
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $name, $email, $hashed, $role);

            if ($stmt->execute()) {
                header("Location: /edm-system/users/index.php");
                exit();
            } else {
                $error = "Failed to create user.";
            }
        }
    }
}

$pageTitle = "Create User";
include("../includes/header.php");
?>

<div class="page-card">
    <h2 class="page-title">Create New User</h2>
    <p class="page-subtitle">Add a new user to the system</p>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <style>
        .form-grid {
            display: grid;
            gap: 18px;
            margin-top: 25px;
            max-width: 520px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: white;
            font-size: 14px;
            transition: 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(11, 61, 46, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
    </style>

    <form method="POST" class="form-grid">

        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role">
                <option value="viewer">Viewer</option>
                <option value="teacher">Teacher</option>
            </select>
        </div>

        <div class="form-actions">
            <button class="action-btn" type="submit">
                <i class="fa-solid fa-user-plus"></i>
                Create User
            </button>

            <a href="/edm-system/users/index.php" class="action-btn secondary">
                Cancel
            </a>
        </div>

    </form>
</div>

<?php include("../includes/footer.php"); ?>