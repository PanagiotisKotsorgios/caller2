<?php
require __DIR__ . '/config.php';
try {
    db()->query('SELECT 1');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false]);
}
