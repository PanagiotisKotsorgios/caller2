<?php
require __DIR__.'/config.php'; require __DIR__.'/includes/functions.php'; require __DIR__.'/includes/auth.php';
require_admin(); $pdo=db();
$month=$_GET['month']??date('Y-m'); if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');
$start=$month.'-01'; $end=date('Y-m-d',strtotime($start.' +1 month'));
$stmt=$pdo->prepare("SELECT u.id,u.name,u.commission_percent,u.standard_price,
 COUNT(l.id) sold_count,
 COALESCE(SUM(l.sale_value),0) revenue,
 COALESCE(SUM(l.sale_value*COALESCE(l.commission_percent,u.commission_percent,0)/100),0) owed
 FROM users u LEFT JOIN leads l ON l.assigned_to=u.id AND l.status='won' AND l.sale_date>=? AND l.sale_date<?
 WHERE u.role='caller' GROUP BY u.id ORDER BY owed DESC,u.name");
$stmt->execute([$start,$end]); $rows=$stmt->fetchAll();
$totalRevenue=array_sum(array_map(fn($r)=>(float)$r['revenue'],$rows)); $totalOwed=array_sum(array_map(fn($r)=>(float)$r['owed'],$rows));
$details=$pdo->prepare("SELECT l.*,u.name caller_name,u.commission_percent caller_default FROM leads l JOIN users u ON u.id=l.assigned_to WHERE l.status='won' AND l.sale_date>=? AND l.sale_date<? ORDER BY u.name,l.sale_date,l.id");$details->execute([$start,$end]);$sales=$details->fetchAll();
$pageTitle='Monthly Reports'; include __DIR__.'/includes/header.php';
?>
<div class="page-head"><div><h1>Monthly commissions</h1><p>Admin-only payout calculation for completed sales.</p></div><a class="btn primary" href="export.php?type=payouts&month=<?=e($month)?>">Export monthly payout CSV</a></div>
<form class="filters compact" method="get"><label>Month<input type="month" name="month" value="<?=e($month)?>"></label><button class="btn" type="submit">Load report</button></form>
<div class="stats-grid small-grid"><div class="stat"><span>Month revenue</span><strong><?=money($totalRevenue)?></strong></div><div class="stat"><span>Total commissions owed</span><strong><?=money($totalOwed)?></strong></div><div class="stat"><span>Completed sales</span><strong><?=array_sum(array_map(fn($r)=>(int)$r['sold_count'],$rows))?></strong></div></div>
<section class="panel"><div class="panel-head"><h2>Caller payout summary · <?=e(date('F Y',strtotime($start)))?></h2></div><div class="table-wrap"><table><thead><tr><th>Caller</th><th>Sold clients</th><th>Sales value</th><th>Commission %</th><th>Standard price</th><th>Amount owed</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=e($r['name'])?></strong></td><td><?=(int)$r['sold_count']?></td><td><?=money($r['revenue'])?></td><td><?=e($r['commission_percent'])?>%</td><td><?=money($r['standard_price'])?></td><td><strong><?=money($r['owed'])?></strong></td></tr><?php endforeach;?></tbody><tfoot><tr><th>Total</th><th></th><th><?=money($totalRevenue)?></th><th></th><th></th><th><?=money($totalOwed)?></th></tr></tfoot></table></div></section>
<section class="panel"><div class="panel-head"><h2>Sales included</h2></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Caller</th><th>Client</th><th>Service</th><th>Sale value</th><th>Rate used</th><th>Commission</th></tr></thead><tbody><?php if(!$sales):?><tr><td colspan="7" class="muted">No completed sales for this month.</td></tr><?php endif;?><?php foreach($sales as $s):$rate=$s['commission_percent']!==null?(float)$s['commission_percent']:(float)$s['caller_default'];?><tr><td><?=e($s['sale_date'])?></td><td><?=e($s['caller_name'])?></td><td><strong><?=e($s['company_name'])?></strong></td><td><?=e(service_label($s['service_type']))?></td><td><?=money($s['sale_value'])?></td><td><?=e((string)$rate)?>%</td><td><strong><?=money((float)$s['sale_value']*$rate/100)?></strong></td></tr><?php endforeach;?></tbody></table></div></section>
<?php include __DIR__.'/includes/footer.php';?>
