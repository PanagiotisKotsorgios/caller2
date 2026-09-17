<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_all') {
        if (trim($_POST['confirmation'] ?? '') !== 'DELETE ALL') {
            flash('error', 'Type DELETE ALL exactly to confirm full lead deletion.');
            redirect('lead_cleanup.php');
        }
        $pdo->beginTransaction();
        try {
            $deleted = (int)$pdo->exec('DELETE FROM leads');
            $pdo->exec('DELETE FROM lead_import_batches');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        try { $pdo->exec('ALTER TABLE leads AUTO_INCREMENT = 1'); } catch (Throwable $e) {}
        try { $pdo->exec('ALTER TABLE lead_import_batches AUTO_INCREMENT = 1'); } catch (Throwable $e) {}
        flash('success', $deleted . ' lead(s) deleted. All XLSX import history was also cleared. Team accounts and settings were kept.');
        redirect('lead_cleanup.php');
    }

    if ($action === 'delete_imported') {
        if (trim($_POST['confirmation'] ?? '') !== 'DELETE IMPORTS') {
            flash('error', 'Type DELETE IMPORTS exactly to confirm deletion of tracked imported leads.');
            redirect('lead_cleanup.php');
        }
        $pdo->beginTransaction();
        try {
            $deleted = (int)$pdo->exec('DELETE FROM leads WHERE import_batch_id IS NOT NULL');
            $pdo->exec('DELETE FROM lead_import_batches');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        flash('success', $deleted . ' tracked imported lead(s) deleted. Manually created leads were kept.');
        redirect('lead_cleanup.php');
    }

    if ($action === 'delete_registered_by') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0 || trim($_POST['confirmation'] ?? '') !== 'DELETE') {
            flash('error', 'Choose a user and type DELETE to confirm.');
            redirect('lead_cleanup.php');
        }
        $u = $pdo->prepare('SELECT name FROM users WHERE id=?');
        $u->execute([$userId]);
        $name = $u->fetchColumn();
        if (!$name) {
            flash('error', 'User not found.');
            redirect('lead_cleanup.php');
        }
        $s = $pdo->prepare('DELETE FROM leads WHERE created_by=?');
        $s->execute([$userId]);
        flash('success', $s->rowCount() . ' lead(s) registered by ' . $name . ' permanently deleted.');
        redirect('lead_cleanup.php');
    }

    if ($action === 'delete_assigned_to') {
        $target = $_POST['assigned_to'] ?? '';
        if (trim($_POST['confirmation'] ?? '') !== 'DELETE') {
            flash('error', 'Type DELETE to confirm.');
            redirect('lead_cleanup.php');
        }
        if ($target === 'unassigned') {
            $deleted = (int)$pdo->exec('DELETE FROM leads WHERE assigned_to IS NULL');
            flash('success', $deleted . ' unassigned lead(s) permanently deleted.');
            redirect('lead_cleanup.php');
        }
        $callerId = (int)$target;
        $u = $pdo->prepare("SELECT name FROM users WHERE id=? AND role='caller'");
        $u->execute([$callerId]);
        $name = $u->fetchColumn();
        if (!$name) {
            flash('error', 'Caller not found.');
            redirect('lead_cleanup.php');
        }
        $s = $pdo->prepare('DELETE FROM leads WHERE assigned_to=?');
        $s->execute([$callerId]);
        flash('success', $s->rowCount() . ' lead(s) assigned to ' . $name . ' permanently deleted.');
        redirect('lead_cleanup.php');
    }

    flash('error', 'Invalid deletion action.');
    redirect('lead_cleanup.php');
}

$total = (int)$pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
$trackedImported = (int)$pdo->query('SELECT COUNT(*) FROM leads WHERE import_batch_id IS NOT NULL')->fetchColumn();
$unassigned = (int)$pdo->query('SELECT COUNT(*) FROM leads WHERE assigned_to IS NULL')->fetchColumn();
$users = $pdo->query("SELECT u.id,u.name,u.role,u.active,
    (SELECT COUNT(*) FROM leads l WHERE l.created_by=u.id) registered_count
    FROM users u ORDER BY u.role='admin' DESC,u.name")->fetchAll();
$callers = $pdo->query("SELECT u.id,u.name,u.active,
    (SELECT COUNT(*) FROM leads l WHERE l.assigned_to=u.id) assigned_count
    FROM users u WHERE u.role='caller' ORDER BY u.name")->fetchAll();

$pageTitle = 'Lead Deletion Tools';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Lead deletion tools</h1><p>Admin-only permanent deletion for selected groups, imports, or the complete lead database.</p></div>
    <div class="actions"><a class="btn" href="imports.php">Import history</a><a class="btn" href="leads.php">← Leads</a></div>
</div>

<div class="stats-grid small-grid">
    <div class="stat"><span>Total leads</span><strong><?= $total ?></strong></div>
    <div class="stat amber"><span>Tracked XLSX imports</span><strong><?= $trackedImported ?></strong></div>
    <div class="stat"><span>Unassigned</span><strong><?= $unassigned ?></strong></div>
</div>

<section class="panel danger-zone">
    <div class="panel-head"><h2>Delete all tracked XLSX imports</h2></div>
    <p class="muted">Deletes every lead imported through XLSX since batch tracking was added. Manually created leads remain.</p>
    <form method="post" class="form-grid narrow" onsubmit="return confirm('Permanently delete all tracked XLSX-imported leads?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_imported">
        <label>Type <strong>DELETE IMPORTS</strong><input name="confirmation" autocomplete="off" required></label>
        <div class="form-actions"><button class="btn danger" type="submit">Delete <?= $trackedImported ?> imported leads</button></div>
    </form>
</section>

<section class="panel danger-zone">
    <div class="panel-head"><h2>Delete leads registered by a user</h2></div>
    <p class="muted">Useful for removing everything entered or imported by one caller/admin. This includes both manual and imported leads created by that user.</p>
    <form method="post" class="form-grid narrow" onsubmit="return confirm('Delete every lead registered by this user?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_registered_by">
        <label>User<select name="user_id" required><option value="">Choose user</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> · <?= e($u['role']) ?> · <?= (int)$u['registered_count'] ?> leads<?= !$u['active']?' · inactive':'' ?></option><?php endforeach; ?></select></label>
        <label>Type <strong>DELETE</strong><input name="confirmation" autocomplete="off" required></label>
        <div class="form-actions"><button class="btn danger" type="submit">Delete registered leads</button></div>
    </form>
</section>

<section class="panel danger-zone">
    <div class="panel-head"><h2>Delete by current assignment</h2></div>
    <p class="muted">Deletes all leads currently assigned to a caller, or all unassigned leads.</p>
    <form method="post" class="form-grid narrow" onsubmit="return confirm('Delete every lead in this assignment group?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_assigned_to">
        <label>Assignment<select name="assigned_to" required><option value="unassigned">Unassigned · <?= $unassigned ?> leads</option><?php foreach($callers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> · <?= (int)$c['assigned_count'] ?> leads<?= !$c['active']?' · inactive':'' ?></option><?php endforeach; ?></select></label>
        <label>Type <strong>DELETE</strong><input name="confirmation" autocomplete="off" required></label>
        <div class="form-actions"><button class="btn danger" type="submit">Delete assignment group</button></div>
    </form>
</section>

<section class="panel danger-zone danger-zone-strong">
    <div class="panel-head"><h2>Delete ALL leads from the platform</h2></div>
    <p><strong>This removes every lead from every caller and admin.</strong> It also clears XLSX import history. User accounts, caller commission percentages, standard prices and login settings remain.</p>
    <form method="post" class="form-grid narrow" onsubmit="return confirm('FINAL WARNING: delete ALL leads from the CRM? This cannot be undone.')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_all">
        <label>Type <strong>DELETE ALL</strong><input name="confirmation" autocomplete="off" required></label>
        <div class="form-actions"><button class="btn danger" type="submit">Delete all <?= $total ?> leads</button></div>
    </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
