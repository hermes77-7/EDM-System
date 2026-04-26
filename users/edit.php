<?php
include("../includes/auth.php");
include("../includes/functions.php");
include("../config/db.php");

if (!isAdmin()) {
    http_response_code(403);
    die("Access denied");
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$error = "";

if ($id <= 0) {
    die("Invalid user.");
}

$stmt = $conn->prepare("SELECT id, name, email, role, is_active FROM users WHERE id = ? AND role <> 'admin'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "viewer";
    $isActive = isset($_POST["is_active"]) ? 1 : 0;
    $password = trim($_POST["password"] ?? "");

    if ($name === "" || $email === "") {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (!in_array($role, ["viewer", "teacher"], true)) {
        $error = "Invalid role selected.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $check->bind_param("si", $email, $id);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            if ($password !== "") {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, password = ? WHERE id = ?");
                $update->bind_param("sssisi", $name, $email, $role, $isActive, $hashedPassword, $id);
            } else {
                $update = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                $update->bind_param("sssii", $name, $email, $role, $isActive, $id);
            }

            if ($update->execute()) {
                header("Location: /edm-system/users/index.php");
                exit();
            } else {
                $error = "Could not update user.";
            }
        }
    }
}

$pageTitle = "Edit User | EDM System";
include("../includes/header.php");
?>

<div class="page-card">
    <h2 class="page-title">Edit User</h2>
    <p class="page-subtitle">Update the account details below.</p>

    <?php if ($error): ?>
        <div style="margin-top:16px; padding:14px; border-radius:12px; background:#fef2f2; color:#b91c1c;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="margin-top:20px; display:grid; gap:16px; max-width:520px;">
        <div>
            <label style="display:block; margin-bottom:8px; font-weight:600;">Full Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>"
                   style="width:100%; padding:14px 16px; border:1px solid var(--border); border-radius:14px;">
        </div>

        <div>
            <label style="display:block; margin-bottom:8px; font-weight:600;">Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>"
                   style="width:100%; padding:14px 16px; border:1px solid var(--border); border-radius:14px;">
        </div>

        <div>
            <label style="display:block; margin-bottom:8px; font-weight:600;">Role</label>
            <select name="role"
                    style="width:100%; padding:14px 16px; border:1px solid var(--border); border-radius:14px; background:white;">
                <option value="viewer" <?php echo $user['role'] === 'viewer' ? 'selected' : ''; ?>>Viewer</option>
                <option value="teacher" <?php echo $user['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
            </select>
        </div>

        <div>
            <label style="display:block; margin-bottom:8px; font-weight:600;">New Password</label>
            <input type="password" name="password" placeholder="Leave blank to keep current password"
                   style="width:100%; padding:14px 16px; border:1px solid var(--border); border-radius:14px;">
        </div>

        <label style="display:flex; align-items:center; gap:10px; font-weight:600;">
            <input type="checkbox" name="is_active" <?php echo ((int)$user['is_active'] === 1) ? 'checked' : ''; ?>>
            Active account
        </label>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="action-btn">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Changes
            </button>
            <a href="/edm-system/users/index.php" class="action-btn" style="background:#111827;">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php include("../includes/footer.php"); ?>