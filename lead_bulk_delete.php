<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_admin();
verify_csrf();
$pdo = db();

$ids = $_POST['lead_ids'] ?? [];
$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), fn($id) => $id > 0)));
if (!$ids) {
    flash('error', 'Select at least one lead to delete.');
    redirect('leads.php');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
$stmt->execute($ids);

flash('success', $stmt->rowCount() . ' selected lead(s) permanently deleted.');
redirect('leads.php');
