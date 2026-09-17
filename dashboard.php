<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$user = require_login();
$pdo = db();
$scopeSql = $user['role'] === 'admin' ? '1=1' : 'l.assigned_to = :uid';
$params = $user['role'] === 'admin' ? [] : [':uid' => $user['id']];

$stmt = $pdo->prepare("SELECT
    COUNT(*) total_leads,
    SUM(l.status = 'won') won_leads,
    SUM(l.demo_sent = 1 OR l.status = 'demo_sent') demos,
    SUM(l.follow_up_date IS NOT NULL AND l.follow_up_date <= CURDATE() AND l.status NOT IN ('won','lost','not_interested')) followups_due,
    COALESCE(SUM(CASE WHEN l.status='won' THEN l.sale_value ELSE 0 END),0) revenue
    FROM leads l WHERE {$scopeSql}");
$stmt->execute($params);
$stats = $stmt->fetch();

$commissionSql = "SELECT COALESCE(SUM(CASE WHEN l.status='won' THEN COALESCE(l.sale_value,0) * COALESCE(l.commission_percent,u.commission_percent,0) / 100 ELSE 0 END),0)
                  FROM leads l LEFT JOIN users u ON u.id=l.assigned_to WHERE {$scopeSql}";
$stmt = $pdo->prepare($commissionSql);
$stmt->execute($params);
$totalCommission = (float)$stmt->fetchColumn();

$recentSql = "SELECT l.*, u.name caller_name FROM leads l LEFT JOIN users u ON u.id=l.assigned_to WHERE {$scopeSql} ORDER BY l.updated_at DESC LIMIT 10";
$stmt = $pdo->prepare($recentSql);
$stmt->execute($params);
$recent = $stmt->fetchAll();

$callerStats = [];
if ($user['role'] === 'admin') {
    $callerStats = $pdo->query("SELECT u.id,u.name,u.commission_percent,u.standard_price,
        COUNT(l.id) total_leads,
        (SELECT COUNT(*) FROM leads cr WHERE cr.created_by=u.id) registered_leads,
        COALESCE(SUM(l.status='won'),0) won,
        COALESCE(SUM(CASE WHEN l.status='won' THEN l.sale_value ELSE 0 END),0) revenue,
        COALESCE(SUM(CASE WHEN l.status='won' THEN COALESCE(l.sale_value,0)*COALESCE(l.commission_percent,u.commission_percent,0)/100 ELSE 0 END),0) commission
        FROM users u LEFT JOIN leads l ON l.assigned_to=u.id
        WHERE u.role='caller' AND u.active=1
        GROUP BY u.id ORDER BY revenue DESC, u.name")->fetchAll();
}

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Dashboard</h1><p><?= $user['role']==='admin' ? 'Team overview' : 'Your lead and sales overview' ?></p></div>
    <div class="form-actions"><a class="btn" href="import.php">Import XLSX</a><a class="btn primary" href="lead_form.php">+ Add lead</a></div>
</div>

<div class="stats-grid">
    <div class="stat"><span>Total leads</span><strong><?= (int)$stats['total_leads'] ?></strong></div>
    <div class="stat green"><span>Won / sold</span><strong><?= (int)$stats['won_leads'] ?></strong></div>
    <div class="stat amber"><span>Demos sent</span><strong><?= (int)$stats['demos'] ?></strong></div>
    <div class="stat red"><span>Follow-ups due</span><strong><?= (int)$stats['followups_due'] ?></strong></div>
    <div class="stat"><span>Sales revenue</span><strong><?= money($stats['revenue']) ?></strong></div>
    <div class="stat"><span><?= $user['role']==='admin' ? 'Commissions owed' : 'Your commission' ?></span><strong><?= money($totalCommission) ?></strong></div>
</div>

<?php if ($user['role'] === 'admin'): ?>
<section class="panel">
    <div class="panel-head"><h2>Caller performance</h2><a href="reports.php">Monthly report →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Caller</th><th>Assigned leads</th><th>Registered by caller</th><th>Won</th><th>Revenue</th><th>Default %</th><th>Standard price</th><th>Commission</th></tr></thead>
        <tbody>
        <?php if (!$callerStats): ?><tr><td colspan="8" class="muted">No callers yet.</td></tr><?php endif; ?>
        <?php foreach ($callerStats as $c): ?>
            <tr><td><strong><?= e($c['name']) ?></strong></td><td><?= (int)$c['total_leads'] ?></td><td><?= (int)$c['registered_leads'] ?></td><td><?= (int)$c['won'] ?></td><td><?= money($c['revenue']) ?></td><td><?= e($c['commission_percent']) ?>%</td><td><?= money($c['standard_price']) ?></td><td><strong><?= money($c['commission']) ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head"><h2>Recently updated leads</h2><a href="leads.php">View all →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Business</th><th>Service</th><th>Status</th><?php if ($user['role']==='admin'): ?><th>Caller</th><?php endif; ?><th>Follow-up</th><th>Value</th><th></th></tr></thead>
        <tbody>
        <?php if (!$recent): ?><tr><td colspan="7" class="muted">No leads yet.</td></tr><?php endif; ?>
        <?php foreach ($recent as $lead): ?>
            <tr class="<?= e(status_row_class($lead['status'], (bool)$lead['demo_sent'])) ?>">
                <td><strong><?= e($lead['company_name']) ?></strong><br><small><?= e($lead['phone']) ?></small></td>
                <td><?= e(service_label($lead['service_type'])) ?></td>
                <td><span class="badge status-<?= e($lead['status']) ?>"><?= e(status_label($lead['status'])) ?></span></td>
                <?php if ($user['role']==='admin'): ?><td><?= e($lead['caller_name'] ?? 'Unassigned') ?></td><?php endif; ?>
                <td><?= e($lead['follow_up_date'] ?: '—') ?></td>
                <td><?= $lead['sale_value'] !== null ? money($lead['sale_value']) : '—' ?></td>
                <td><a class="btn small" href="lead_form.php?id=<?= (int)$lead['id'] ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
