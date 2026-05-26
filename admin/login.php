<?php
session_start();
include '../config/db.php';

if (isset($_SESSION['admin_id'])) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $admin = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM admin_users WHERE username='$username' AND is_active=1 LIMIT 1"
    ));

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Frank Gadgets</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { -webkit-font-smoothing: antialiased; }
body { font-family: 'Inter', sans-serif; }
.form-input {
    width: 100%; border: 1.5px solid #e5e7eb; border-radius: 10px;
    padding: 11px 14px; font-size: 14px; outline: none;
    transition: border-color 0.2s; font-family: inherit;
}
.form-input:focus { border-color: #0071e3; }
</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">

<div class="w-full max-w-sm">
    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="flex items-center justify-center gap-2 mb-2">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center overflow-hidden p-1 shadow">
                <img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
            </div>
            <span class="font-black text-xl text-gray-900">Frank Gadgets</span>
        </div>
        <p class="text-gray-500 text-sm">Admin Panel</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-8">
        <h1 class="text-xl font-bold mb-6 text-gray-900">Sign In</h1>

        <?php if($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-5 text-sm text-red-600 font-medium">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Username</label>
                <input type="text" name="username" class="form-input"
                    value="<?php echo htmlspecialchars($_POST['username']??''); ?>"
                    placeholder="admin" required autofocus>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white font-semibold py-3 rounded-xl hover:bg-blue-700 transition-colors text-sm mt-2">
                Sign In →
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">
        <a href="../index.php" class="hover:text-blue-600 transition-colors">← Back to store</a>
    </p>
</div>

</body>
</html>