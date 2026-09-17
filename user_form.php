<?php
require __DIR__.'/config.php'; require __DIR__.'/includes/functions.php'; require __DIR__.'/includes/auth.php';
$current=require_admin(); $pdo=db(); $id=(int)($_GET['id']??0);
$member=['id'=>0,'name'=>'','username'=>'','role'=>'caller','commission_percent'=>'10.00','standard_price'=>'0.00','active'=>1];
if($id){$s=$pdo->prepare('SELECT id,name,username,role,commission_percent,standard_price,active FROM users WHERE id=?');$s->execute([$id]);$member=$s->fetch();if(!$member){exit('User not found.');}}
$pageTitle=$id?'Edit Team Member':'Add Caller'; include __DIR__.'/includes/header.php';
?>
<div class="page-head"><div><h1><?=$id?'Edit team member':'Add caller'?></h1><p>Only administrators can set a caller's commission and standard selling price.</p></div><a class="btn" href="users.php">← Back</a></div>
<form class="panel form-grid" method="post" action="user_save.php">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$member['id']?>">
<label>Name *<input name="name" value="<?=e($member['name'])?>" required></label>
<label>Username *<input name="username" value="<?=e($member['username'])?>" required></label>
<label>Role<select name="role"><option value="caller" <?=$member['role']==='caller'?'selected':''?>>Caller</option><option value="admin" <?=$member['role']==='admin'?'selected':''?>>Admin</option></select></label>
<label>Commission %<input type="number" name="commission_percent" min="0" max="100" step="0.01" value="<?=e($member['commission_percent'])?>"></label>
<label>Standard price (€)<input type="number" name="standard_price" min="0" step="0.01" value="<?=e($member['standard_price'])?>" placeholder="e.g. 350.00"></label>
<label>Password <?=$id?'(leave blank to keep current)':'*'?><input type="password" name="password" <?=$id?'':'required minlength="8"'?> autocomplete="new-password"></label>
<label class="check-label"><input type="checkbox" name="active" value="1" <?=$member['active']?'checked':''?>> Active account</label>
<div class="full form-actions"><button class="btn primary" type="submit">Save member</button></div>
</form>
<?php include __DIR__.'/includes/footer.php';?>
