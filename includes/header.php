<?php
$user = current_user();
$pageTitle = $pageTitle ?? app_name();
$flashes = pull_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(app_name()) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body>
<header class="topbar">
    <div class="brand"><a href="dashboard.php"><?= e(app_name()) ?></a></div>
    <?php if ($user): ?>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="leads.php">Leads</a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="users.php">Team</a>
            <a href="reports.php">Reports</a>
        <?php endif; ?>
        <a href="profile.php">My Account</a>
        <a href="logout.php">Logout</a>
    </nav>
    <div class="user-pill"><?= e($user['name']) ?> · <?= e(ucfirst($user['role'])) ?></div>
    <?php endif; ?>
</header>
<main class="container">
<?php foreach ($flashes as $flash): ?>
    <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
