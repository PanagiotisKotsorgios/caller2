<?php

declare(strict_types=1);

function current_user(): ?array
{
    static $cached = false;
    static $user = null;

    if ($cached) return $user;
    $cached = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) return null;

    $stmt = db()->prepare('SELECT id, name, username, role, commission_percent, active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || !(int)$row['active']) {
        unset($_SESSION['user_id']);
        return null;
    }

    $user = $row;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) redirect('login.php');
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Admin access required.');
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function can_access_lead(array $lead, array $user): bool
{
    return $user['role'] === 'admin' || (int)$lead['assigned_to'] === (int)$user['id'];
}
