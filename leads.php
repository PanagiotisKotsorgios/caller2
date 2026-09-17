<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$service = trim($_GET['service'] ?? '');
$caller = (int)($_GET['caller'] ?? 0);
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
    $where[] = '(l.company_name LIKE :q OR l.contact_name LIKE :q OR l.phone LIKE :q OR l.email LIKE :q OR l.city LIKE :q)';
    $params[':q'] = "%{$q}%";
}
if ($status !== '') { $where[] = 'l.status = :status'; $params[':status'] = $status; }
if ($service !== '') { $where[] = 'l.service_type = :service'; $params[':service'] = $service; }
if ($user['role'] === 'admin' && $caller > 0) { $where[] = 'l.assigned_to = :caller'; $params[':caller'] = $caller; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads l {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT l.*,u.name caller_name,u.commission_percent caller_commission FROM leads l LEFT JOIN users u ON u.id=l.assigned_to {$whereSql} ORDER BY l.updated_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$leads = $stmt->fetchAll();

$callers = [];
if ($user['role'] === 'admin') $callers = $pdo->query("SELECT id,name FROM users WHERE role='caller' AND active=1 ORDER BY name")->fetchAll();

$pageTitle = 'Leads';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Leads</h1><p><?= $total ?> matching lead<?= $total===1?'':'s' ?></p></div>
    <div class="actions"><a class="btn" href="export.php?type=leads&<?= e(build_query(['page'=>null])) ?>">Export CSV</a><a class="btn primary" href="lead_form.php">+ Add lead</a></div>
</div>

<form class="filters" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="Search business, phone, email, city...">
    <select name="status"><option value="">All statuses</option><?php foreach(statuses() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $status===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select name="service"><option value="">All services</option><?php foreach(service_types() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $service===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <?php if ($user['role']==='admin'): ?><select name="caller"><option value="">All callers</option><?php foreach($callers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $caller===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <button class="btn" type="submit">Filter</button>
    <a class="btn ghost" href="leads.php">Reset</a>
</form>

<div class="legend"><span class="legend-green">Won</span><span class="legend-red">Lost / Not interested</span><span class="legend-yellow">Demo sent</span><span class="legend-blue">Interested</span></div>

<section class="panel no-pad">
<div class="table-wrap"><table>
<thead><tr><th>ID</th><th>Business / Contact</th><th>Service</th><th>Status</th><th>Demo</th><?php if($user['role']==='admin'):?><th>Caller</th><?php endif;?><th>Follow-up</th><th>Sale</th><th>Commission</th><th>Updated</th><th></th></tr></thead>
<tbody>
<?php if(!$leads): ?><tr><td colspan="11" class="muted center">No leads found.</td></tr><?php endif; ?>
<?php foreach($leads as $lead):
    $rate = $lead['commission_percent'] !== null ? (float)$lead['commission_percent'] : (float)($lead['caller_commission'] ?? 0);
    $commission = $lead['status']==='won' ? ((float)$lead['sale_value'] * $rate / 100) : 0;
?>
<tr class="<?= e(status_row_class($lead['status'], (bool)$lead['demo_sent'])) ?>">
    <td>#<?= (int)$lead['id'] ?></td>
    <td><strong><?= e($lead['company_name']) ?></strong><br><small><?= e($lead['contact_name']) ?><?= $lead['phone'] ? ' · '.e($lead['phone']) : '' ?><?= $lead['city'] ? ' · '.e($lead['city']) : '' ?></small></td>
    <td><?= e(service_label($lead['service_type'])) ?></td>
    <td><span class="badge status-<?= e($lead['status']) ?>"><?= e(status_label($lead['status'])) ?></span></td>
    <td><?= $lead['demo_sent'] ? '✓' : '—' ?></td>
    <?php if($user['role']==='admin'):?><td><?= e($lead['caller_name'] ?? 'Unassigned') ?></td><?php endif;?>
    <td><?= e($lead['follow_up_date'] ?: '—') ?></td>
    <td><?= $lead['sale_value'] !== null ? money($lead['sale_value']) : '—' ?></td>
    <td><?= $lead['status']==='won' ? money($commission).' <small>('.e((string)$rate).'%)</small>' : '—' ?></td>
    <td><small><?= e(date('d/m/Y H:i', strtotime($lead['updated_at']))) ?></small></td>
    <td><a class="btn small" href="lead_form.php?id=<?= (int)$lead['id'] ?>">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
</section>

<?php if($totalPages>1): ?><div class="pagination">
<?php for($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?><a class="<?= $i===$page?'active':'' ?>" href="?<?= e(build_query(['page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?>
</div><?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
