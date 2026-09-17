<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$service = trim($_GET['service'] ?? '');
$callerRaw = $_GET['caller'] ?? '';
$caller = $callerRaw === 'unassigned' ? 'unassigned' : (int)$callerRaw;
$registeredBy = (int)($_GET['registered_by'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($user['role'] !== 'admin') {
    $where[] = 'l.assigned_to = :current_user';
    $params[':current_user'] = $user['id'];
}
if ($q !== '') {
    $where[] = '(l.company_name LIKE :q OR l.contact_name LIKE :q OR l.phone LIKE :q OR l.email LIKE :q OR l.address LIKE :q OR l.city LIKE :q OR l.region LIKE :q OR l.category LIKE :q OR l.subcategory LIKE :q OR l.source_comments LIKE :q)';
    $params[':q'] = "%{$q}%";
}
if ($status !== '') { $where[] = 'l.status = :status'; $params[':status'] = $status; }
if ($service !== '') { $where[] = 'l.service_type = :service'; $params[':service'] = $service; }
if ($user['role'] === 'admin' && $caller === 'unassigned') $where[] = 'l.assigned_to IS NULL';
elseif ($user['role'] === 'admin' && is_int($caller) && $caller > 0) { $where[] = 'l.assigned_to = :caller'; $params[':caller'] = $caller; }
if ($user['role'] === 'admin' && $registeredBy > 0) { $where[] = 'l.created_by = :registered_by'; $params[':registered_by'] = $registeredBy; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads l {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT l.*,u.name caller_name,u.commission_percent caller_commission,u.standard_price caller_standard_price,creator.name creator_name,b.filename import_filename FROM leads l LEFT JOIN users u ON u.id=l.assigned_to LEFT JOIN users creator ON creator.id=l.created_by LEFT JOIN lead_import_batches b ON b.id=l.import_batch_id {$whereSql} ORDER BY l.updated_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$leads = $stmt->fetchAll();

$callers = [];
$creators = [];
if ($user['role'] === 'admin') {
    $callers = $pdo->query("SELECT id,name,commission_percent,standard_price FROM users WHERE role='caller' AND active=1 ORDER BY name")->fetchAll();
    $creators = $pdo->query("SELECT id,name,role,active FROM users ORDER BY role='admin' DESC,name")->fetchAll();
}

$pageTitle = 'Leads';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Leads</h1><p><?= $total ?> matching lead<?= $total===1?'':'s' ?><?= $user['role']!=='admin'?' · only leads assigned to you':'' ?></p></div>
    <div class="actions"><a class="btn" href="export.php?type=leads&<?= e(build_query(['page'=>null])) ?>">Export CSV</a><?php if($user['role']==='admin'): ?><a class="btn" href="imports.php">Import history</a><a class="btn danger" href="lead_cleanup.php">Delete tools</a><?php endif; ?><a class="btn" href="import.php">Import XLSX</a><a class="btn primary" href="lead_form.php">+ Add lead</a></div>
</div>

<form class="filters" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="Search business, phone, city, category...">
    <select name="status"><option value="">All statuses</option><?php foreach(statuses() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $status===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select name="service"><option value="">All services</option><?php foreach(service_types() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $service===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <?php if ($user['role']==='admin'): ?><select name="caller"><option value="">All callers</option><option value="unassigned" <?= $caller==='unassigned'?'selected':'' ?>>Unassigned</option><?php foreach($callers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $caller===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><select name="registered_by"><option value="">Registered by anyone</option><?php foreach($creators as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $registeredBy===(int)$c['id']?'selected':'' ?>>Registered by <?= e($c['name']) ?> (<?= e($c['role']) ?>)</option><?php endforeach; ?></select><?php endif; ?>
    <button class="btn" type="submit">Filter</button><a class="btn ghost" href="leads.php">Reset</a>
</form>

<div class="legend"><span class="legend-green">Won</span><span class="legend-red">Lost / Not interested</span><span class="legend-yellow">Demo sent</span><span class="legend-blue">Interested</span></div>

<?php if($user['role']==='admin'): ?>
<form method="post" action="bulk_assign.php" id="bulkForm">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="bulk-bar">
    <strong>Selected leads:</strong>
    <select name="assigned_to"><option value="__keep__">Keep assignment</option><option value="">Unassign</option><?php foreach($callers as $c):?><option value="<?=(int)$c['id']?>">Assign to <?=e($c['name'])?></option><?php endforeach;?></select>
    <select name="bulk_status"><option value="__keep__">Keep status</option><?php foreach(statuses() as $k=>$v):?><option value="<?=e($k)?>">Set <?=e($v)?></option><?php endforeach;?></select>
    <select name="bulk_service"><option value="__keep__">Keep service</option><?php foreach(service_types() as $k=>$v):?><option value="<?=e($k)?>">Set <?=e($v)?></option><?php endforeach;?></select>
    <button class="btn primary" type="submit">Apply to selected</button>
    <button class="btn danger" type="submit" formaction="lead_bulk_delete.php" onclick="return confirm('Permanently delete all selected leads? This cannot be undone.')">Delete selected</button>
</div>
<?php endif; ?>
<section class="panel no-pad"><div class="table-wrap"><table>
<thead><tr><?php if($user['role']==='admin'):?><th><input type="checkbox" id="selectAll" aria-label="Select all visible leads"></th><?php endif;?><th>ID</th><th>Business / Contact</th><th>Category / Location</th><th>Service</th><th>Status</th><th>Demo</th><?php if($user['role']==='admin'):?><th>Caller</th><th>Registered by</th><?php endif;?><th>Follow-up</th><th>Sale</th><th>Commission</th><th></th></tr></thead>
<tbody>
<?php if(!$leads): ?><tr><td colspan="13" class="muted center">No leads found.</td></tr><?php endif; ?>
<?php foreach($leads as $lead):
    $rate = $lead['commission_percent'] !== null ? (float)$lead['commission_percent'] : (float)($lead['caller_commission'] ?? 0);
    $commission = $lead['status']==='won' ? ((float)$lead['sale_value'] * $rate / 100) : 0;
?>
<tr class="<?= e(status_row_class($lead['status'], (bool)$lead['demo_sent'])) ?>">
    <?php if($user['role']==='admin'):?><td><input class="lead-check" type="checkbox" name="lead_ids[]" value="<?=(int)$lead['id']?>"></td><?php endif;?>
    <td>#<?= (int)$lead['id'] ?></td>
    <td><strong><?= e($lead['company_name']) ?></strong><br><small><?= e($lead['contact_name']) ?><?= $lead['phone'] ? ' · '.e($lead['phone']) : '' ?></small></td>
    <td><small><?= e($lead['subcategory'] ?: $lead['category'] ?: '—') ?><br><?= e($lead['city'] ?: '—') ?><?= $lead['region'] ? ' · '.e($lead['region']) : '' ?></small></td>
    <td><?= e(service_label($lead['service_type'])) ?></td>
    <td><span class="badge status-<?= e($lead['status']) ?>"><?= e(status_label($lead['status'])) ?></span></td>
    <td><?= $lead['demo_sent'] ? '✓' : '—' ?></td>
    <?php if($user['role']==='admin'):?><td><?= e($lead['caller_name'] ?? 'Unassigned') ?></td><td><?= e($lead['creator_name'] ?? 'Unknown') ?><?php if($lead['import_batch_id']): ?><br><small>Import #<?= (int)$lead['import_batch_id'] ?><?= $lead['import_filename'] ? ' · '.e($lead['import_filename']) : '' ?></small><?php endif; ?></td><?php endif;?>
    <td><?= e($lead['follow_up_date'] ?: '—') ?></td>
    <td><?= $lead['sale_value'] !== null ? money($lead['sale_value']) : '—' ?></td>
    <td><?= $lead['status']==='won' ? money($commission).' <small>('.e((string)$rate).'%)</small>' : '—' ?></td>
    <td><a class="btn small" href="lead_form.php?id=<?= (int)$lead['id'] ?>">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></section>
<?php if($user['role']==='admin'): ?></form>
<script>
const selectAll=document.getElementById('selectAll');
if(selectAll){selectAll.addEventListener('change',()=>document.querySelectorAll('.lead-check').forEach(c=>c.checked=selectAll.checked));}
</script>
<?php endif; ?>

<?php if($totalPages>1): ?><div class="pagination"><?php for($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?><a class="<?= $i===$page?'active':'' ?>" href="?<?= e(build_query(['page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
