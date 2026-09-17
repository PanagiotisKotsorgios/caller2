<?php
require __DIR__.'/config.php'; require __DIR__.'/includes/functions.php'; require __DIR__.'/includes/auth.php';
$current=require_admin(); verify_csrf(); $pdo=db(); $id=(int)($_POST['id']??0);
$name=trim($_POST['name']??''); $username=trim($_POST['username']??''); $role=in_array($_POST['role']??'',['admin','caller'],true)?$_POST['role']:'caller';
$commission=max(0,min(100,(float)($_POST['commission_percent']??0))); $active=isset($_POST['active'])?1:0; $password=$_POST['password']??'';
if($name===''||$username===''){flash('error','Name and username are required.');redirect($id?'user_form.php?id='.$id:'user_form.php');}
if($id===(int)$current['id'] && !$active){flash('error','You cannot disable your own logged-in account.');redirect('user_form.php?id='.$id);}
try{
 if($id){
   if($password!==''){$s=$pdo->prepare('UPDATE users SET name=?,username=?,role=?,commission_percent=?,active=?,password_hash=? WHERE id=?');$s->execute([$name,$username,$role,$commission,$active,password_hash($password,PASSWORD_DEFAULT),$id]);}
   else{$s=$pdo->prepare('UPDATE users SET name=?,username=?,role=?,commission_percent=?,active=? WHERE id=?');$s->execute([$name,$username,$role,$commission,$active,$id]);}
   flash('success','Team member updated.');
 }else{
   if(strlen($password)<8){flash('error','Password must be at least 8 characters.');redirect('user_form.php');}
   $s=$pdo->prepare('INSERT INTO users (name,username,password_hash,role,commission_percent,active) VALUES (?,?,?,?,?,?)');$s->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT),$role,$commission,$active]);
   flash('success','Team member created.');
 }
}catch(PDOException $e){if($e->getCode()==='23000')flash('error','That username is already used.');else throw $e;}
redirect('users.php');
