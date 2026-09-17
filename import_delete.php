<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_admin();
verify_csrf();
$pdo = db();
$batchId = (int)($_POST['batch_id'] ?? 0);
if ($batchId <= 0) {
    flash('error', 'Invalid import batch.');
    redirect('imports.php');
}

$stmt = $pdo->prepare('SELECT id, filename FROM lead_import_batches WHERE id=?');
$stmt->execute([$batchId]);
$batch = $stmt->fetch();
if (!$batch) {
    flash('error', 'Import batch not found.');
    redirect('imports.php');
}

$pdo->beginTransaction();
try {
    $deleteLeads = $pdo->prepare('DELETE FROM leads WHERE import_batch_id=?');
    $deleteLeads->execute([$batchId]);
    $deleted = $deleteLeads->rowCount();

    $deleteBatch = $pdo->prepare('DELETE FROM lead_import_batches WHERE id=?');
    $deleteBatch->execute([$batchId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

flash('success', $deleted . ' lead(s) from import "' . $batch['filename'] . '" permanently deleted.');
redirect('imports.php');
