<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}

function statuses(): array
{
    return [
        'new' => 'New',
        'no_answer' => 'No Answer',
        'contacted' => 'Contacted',
        'demo_sent' => 'Demo Sent',
        'follow_up' => 'Follow Up',
        'interested' => 'Interested',
        'won' => 'Won / Sold',
        'lost' => 'Lost',
        'not_interested' => 'Not Interested',
    ];
}

function service_types(): array
{
    return [
        'website' => 'Website',
        'eshop' => 'E-shop',
        'other' => 'Other',
    ];
}

function status_label(string $status): string
{
    return statuses()[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function service_label(string $type): string
{
    return service_types()[$type] ?? ucfirst($type);
}

function money($value): string
{
    return number_format((float)$value, 2, ',', '.') . ' €';
}

function status_row_class(string $status, bool $demoSent = false): string
{
    if ($status === 'won') return 'row-won';
    if (in_array($status, ['lost', 'not_interested'], true)) return 'row-lost';
    if ($status === 'demo_sent' || $demoSent) return 'row-demo';
    if ($status === 'interested') return 'row-interested';
    return '';
}

function build_query(array $changes = []): string
{
    $params = array_merge($_GET, $changes);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
    }
    return http_build_query($params);
}
