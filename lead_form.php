<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

if (!$id && $user['role'] !== 'admin') {
    http_response_code(403);
    exit('Only an administrator can create and assign new leads.');
}

$lead = [
    'id'=>0,'company_name'=>'','contact_name'=>'','phone'=>'','email'=>'','address'=>'','city'=>'','region'=>'','category'=>'','subcategory'=>'','source_comments'=>'',
    'service_type'=>'website','status'=>'new','demo_sent'=>0,'follow_up_date'=>'','estimated_value'=>'','sale_value'=>'','sale_date'=>'','commission_percent'=>'','notes'=>'','assigned_to'=>''
];
if($id){
    $stmt=$pdo->prepare('SELECT * FROM leads WHERE id=?'); $stmt->execute([$id]); $found=$stmt->fetch();
    if(!$found){http_response_code(404); exit('Lead not found.');}
    if(!can_access_lead($found,$user)){http_response_code(403); exit('Access denied.');}
    $lead=$found;
}
$callers = $user['role']==='admin' ? $pdo->query("SELECT id,name,commission_percent,standard_price FROM users WHERE role='caller' AND active=1 ORDER BY name")->fetchAll() : [];
$pageTitle = $id ? 'Edit Lead' : 'Add Lead';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1><?= $id?'Edit lead':'Add lead' ?></h1><p><?= $id?'Update contact, pipeline and sale details.':'Create a lead and assign it to a caller.' ?></p></div><a class="btn" href="leads.php">← Back</a></div>
<form method="post" action="lead_save.php" class="panel form-grid">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
    <label>Business / company *<input name="company_name" required value="<?= e($lead['company_name']) ?>"></label>
    <label>Contact person<input name="contact_name" value="<?= e($lead['contact_name']) ?>"></label>
    <label>Phone<input name="phone" value="<?= e($lead['phone']) ?>"></label>
    <label>Email<input type="email" name="email" value="<?= e($lead['email']) ?>"></label>
    <label>Address<input name="address" value="<?= e($lead['address']) ?>"></label>
    <label>City / area<input name="city" value="<?= e($lead['city']) ?>"></label>
    <label>Region / prefecture<input name="region" value="<?= e($lead['region']) ?>"></label>
    <label>Category<input name="category" value="<?= e($lead['category']) ?>"></label>
    <label>Subcategory<input name="subcategory" value="<?= e($lead['subcategory']) ?>"></label>
    <label>Source / import comments<input name="source_comments" value="<?= e($lead['source_comments']) ?>"></label>
    <label>Service<select name="service_type"><?php foreach(service_types() as $k=>$v):?><option value="<?= e($k) ?>" <?= $lead['service_type']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach;?></select></label>
    <label>Status<select name="status"><?php foreach(statuses() as $k=>$v):?><option value="<?= e($k) ?>" <?= $lead['status']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach;?></select></label>
    <label class="check-label"><input type="checkbox" name="demo_sent" value="1" <?= $lead['demo_sent']?'checked':'' ?>> Demo has been sent</label>
    <label>Follow-up date<input type="date" name="follow_up_date" value="<?= e((string)$lead['follow_up_date']) ?>"></label>
    <label>Estimated value (€)<input type="number" step="0.01" min="0" name="estimated_value" value="<?= e((string)$lead['estimated_value']) ?>"></label>
    <label>Final sale value (€)<input type="number" step="0.01" min="0" name="sale_value" value="<?= e((string)$lead['sale_value']) ?>"></label>
    <label>Sale date<input type="date" name="sale_date" value="<?= e((string)$lead['sale_date']) ?>"></label>
    <?php if($user['role']==='admin'): ?>
    <label>Assigned caller<select name="assigned_to"><option value="">Unassigned</option><?php foreach($callers as $c):?><option value="<?= (int)$c['id'] ?>" <?= (int)$lead['assigned_to']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?> · <?= e($c['commission_percent']) ?>% · standard <?= money($c['standard_price']) ?></option><?php endforeach;?></select></label>
    <label>Commission % for this sale<input type="number" name="commission_percent" step="0.01" min="0" max="100" value="<?= e((string)$lead['commission_percent']) ?>" placeholder="Blank = caller rate when sold"></label>
    <?php else: ?>
    <div class="info-box"><strong>Caller settings are admin-controlled.</strong><br>Commission: <?= e((string)$user['commission_percent']) ?>% · Standard price: <?= money($user['standard_price'] ?? 0) ?>.</div>
    <?php endif; ?>
    <label class="full">Notes<textarea name="notes" rows="7" placeholder="Call notes, objections, agreed price, next action..."><?= e($lead['notes']) ?></textarea></label>
    <div class="full form-actions"><button class="btn primary" type="submit">Save lead</button><?php if($id && $user['role']==='admin'):?><button class="btn danger" type="submit" formaction="lead_delete.php" onclick="return confirm('Delete this lead permanently?')">Delete</button><?php endif;?></div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
