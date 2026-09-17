<?php
require __DIR__.'/config.php'; require __DIR__.'/includes/functions.php'; require __DIR__.'/includes/auth.php';
$user=require_login(); $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $old=$_POST['old_password']??''; $new=$_POST['new_password']??'';
 $s=db()->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([$user['id']]);$hash=$s->fetchColumn();
 if(!password_verify($old,$hash))$error='Current password is incorrect.';
 elseif(strlen($new)<8)$error='New password must be at least 8 characters.';
 else{$s=db()->prepare('UPDATE users SET password_hash=? WHERE id=?');$s->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);flash('success','Password changed.');redirect('profile.php');}
}
$pageTitle='My Account'; include __DIR__.'/includes/header.php';
?>
<div class="page-head"><div><h1>My Account</h1><p><?=e($user['name'])?> · <?=e(ucfirst($user['role']))?><?php if($user['role']==='caller'):?> · <?=e($user['commission_percent'])?>% default commission<?php endif;?></p></div></div>
<form class="panel form-grid single narrow" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><?php if($error):?><div class="flash error"><?=e($error)?></div><?php endif;?><label>Current password<input type="password" name="old_password" required></label><label>New password<input type="password" name="new_password" minlength="8" required></label><button class="btn primary" type="submit">Change password</button></form>
<?php include __DIR__.'/includes/footer.php';?>
