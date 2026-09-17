<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

if (current_user()) redirect('dashboard.php');

$count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count === 0) redirect('setup.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        redirect('dashboard.php');
    }
    $error = 'Invalid username or password.';
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <h1><?= e(app_name()) ?></h1>
    <p>Sign in to manage leads, sales and caller commissions.</p>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid single">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Username<input name="username" autocomplete="username" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn primary" type="submit">Login</button>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
