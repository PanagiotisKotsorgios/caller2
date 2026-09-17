<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) redirect('login.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $username === '' || strlen($password) < 8) {
        $error = 'Fill all fields. Password must be at least 8 characters.';
    } else {
        $stmt = db()->prepare("INSERT INTO users (name, username, password_hash, role, commission_percent, active) VALUES (?, ?, ?, 'admin', 0, 1)");
        $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
        flash('success', 'Admin account created. You can now log in.');
        redirect('login.php');
    }
}

$pageTitle = 'First Setup';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <h1>First setup</h1>
    <p>Create the first administrator account. This page locks automatically after the first user is created.</p>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid single">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Admin name<input name="name" required></label>
        <label>Username<input name="username" autocomplete="username" required></label>
        <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
        <button class="btn primary" type="submit">Create admin</button>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
