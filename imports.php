<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_admin();
$pdo = db();

$batches = $pdo->query("SELECT b.*, importer.name importer_name, assignee.name assignee_name,
    COUNT(l.id) current_leads
    FROM lead_import_batches b
    LEFT JOIN users importer ON importer.id=b.imported_by
    LEFT JOIN users assignee ON assignee.id=b.assigned_to
    LEFT JOIN leads l ON l.import_batch_id=b.id
    GROUP BY b.id
    ORDER BY b.created_at DESC, b.id DESC
    LIMIT 250")->fetchAll();

$pageTitle = 'Import History';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Import history</h1><p>Track XLSX imports and remove an entire imported batch at once.</p></div>
    <div class="actions"><a class="btn" href="lead_cleanup.php">Lead deletion tools</a><a class="btn primary" href="import.php">+ Import XLSX</a></div>
</div>

<section class="panel no-pad"><div class="table-wrap"><table>
<thead><tr><th>Batch</th><th>File</th><th>Imported by</th><th>Initial assignment</th><th>Imported</th><th>Still present</th><th>Skipped</th><th>Date</th><th></th></tr></thead>
<tbody>
<?php if (!$batches): ?><tr><td colspan="9" class="muted center">No tracked XLSX imports yet. Older imports made before this update will not have a batch number.</td></tr><?php endif; ?>
<?php foreach ($batches as $b): ?>
<tr>
    <td>#<?= (int)$b['id'] ?></td>
    <td><strong><?= e($b['filename']) ?></strong></td>
    <td><?= e($b['importer_name'] ?? 'Unknown / removed user') ?></td>
    <td><?= e($b['assignee_name'] ?? 'Unassigned') ?></td>
    <td><?= (int)$b['imported_count'] ?></td>
    <td><strong><?= (int)$b['current_leads'] ?></strong></td>
    <td><?= (int)$b['skipped_count'] ?></td>
    <td><?= e($b['created_at']) ?></td>
    <td>
        <form method="post" action="import_delete.php" onsubmit="return confirm('Delete every remaining lead from import batch #<?= (int)$b['id'] ?>? This cannot be undone.')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
            <button class="btn danger small" type="submit">Delete batch</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></section>

<div class="info-box"><strong>Note:</strong> batch tracking starts with this CRM version. Leads imported before this update can still be deleted using <a href="lead_cleanup.php">Lead deletion tools</a> by registered user, assignment, or full-platform deletion.</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
