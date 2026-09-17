<?php
require __DIR__ . '/config.php'; require __DIR__ . '/includes/functions.php'; require __DIR__ . '/includes/auth.php';
require_admin(); $pdo=db();
$users=$pdo->query("SELECT u.*, COUNT(l.id) lead_count, COALESCE(SUM(l.status='won'),0) won_count FROM users u LEFT JOIN leads l ON l.assigned_to=u.id GROUP BY u.id ORDER BY FIELD(u.role,'admin','caller'),u.name")->fetchAll();
$pageTitle='Team'; include __DIR__.'/includes/header.php';
?>
<div class="page-head"><div><h1>Team</h1><p>Admin-only caller pricing, commission rates and account access.</p></div><a class="btn primary" href="user_form.php">+ Add caller</a></div>
<section class="panel no-pad"><div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Commission</th><th>Standard price</th><th>Leads</th><th>Won</th><th>Status</th><th></th></tr></thead>
<tbody><?php foreach($users as $u):?><tr><td><strong><?=e($u['name'])?></strong></td><td><?=e($u['username'])?></td><td><?=e(ucfirst($u['role']))?></td><td><?= $u['role']==='caller'?e($u['commission_percent']).'%':'—'?></td><td><?= $u['role']==='caller'?money($u['standard_price']):'—'?></td><td><?=(int)$u['lead_count']?></td><td><?=(int)$u['won_count']?></td><td><?= $u['active']?'<span class="badge status-won">Active</span>':'<span class="badge status-lost">Disabled</span>'?></td><td><a class="btn small" href="user_form.php?id=<?=(int)$u['id']?>">Edit</a></td></tr><?php endforeach;?></tbody>
</table></div></section>
<?php include __DIR__.'/includes/footer.php';?>
