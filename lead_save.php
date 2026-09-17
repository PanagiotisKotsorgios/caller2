<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';
$user=require_login(); verify_csrf(); $pdo=db();
$id=(int)($_POST['id']??0);
$existing=null;
if($id){$s=$pdo->prepare('SELECT * FROM leads WHERE id=?');$s->execute([$id]);$existing=$s->fetch();if(!$existing){exit('Lead not found.');}if(!can_access_lead($existing,$user)){http_response_code(403);exit('Access denied.');}}
$company=trim($_POST['company_name']??''); if($company===''){flash('error','Business name is required.');redirect($id?'lead_form.php?id='.$id:'lead_form.php');}
$status=$_POST['status']??'new'; if(!isset(statuses()[$status]))$status='new';
$service=$_POST['service_type']??'website'; if(!isset(service_types()[$service]))$service='website';
$assigned = $user['role']==='admin' ? ((int)($_POST['assigned_to']??0) ?: null) : (int)$user['id'];
$commission = null;
if($user['role']==='admin' && ($_POST['commission_percent']??'')!=='') $commission=max(0,min(100,(float)$_POST['commission_percent']));
elseif($existing && $existing['commission_percent']!==null) $commission=(float)$existing['commission_percent'];

// Snapshot caller commission when the lead is won, unless admin explicitly set one.
if($status==='won' && $commission===null && $assigned){
    $s=$pdo->prepare('SELECT commission_percent FROM users WHERE id=?');$s->execute([$assigned]);$commission=(float)$s->fetchColumn();
}
$saleDate = trim($_POST['sale_date']??'') ?: null;
if($status==='won' && !$saleDate) $saleDate = date('Y-m-d');

$data=[
    trim($_POST['contact_name']??'')?:null, trim($_POST['phone']??'')?:null, trim($_POST['email']??'')?:null, trim($_POST['city']??'')?:null,
    $service,$status,isset($_POST['demo_sent'])?1:0,trim($_POST['follow_up_date']??'')?:null,
    ($_POST['estimated_value']??'')!==''?(float)$_POST['estimated_value']:null,($_POST['sale_value']??'')!==''?(float)$_POST['sale_value']:null,
    $saleDate,$commission,trim($_POST['notes']??'')?:null,$assigned
];
if($id){
    $sql='UPDATE leads SET company_name=?,contact_name=?,phone=?,email=?,city=?,service_type=?,status=?,demo_sent=?,follow_up_date=?,estimated_value=?,sale_value=?,sale_date=?,commission_percent=?,notes=?,assigned_to=? WHERE id=?';
    $stmt=$pdo->prepare($sql);$stmt->execute(array_merge([$company],$data,[$id]));
    flash('success','Lead updated.');
}else{
    $sql='INSERT INTO leads (company_name,contact_name,phone,email,city,service_type,status,demo_sent,follow_up_date,estimated_value,sale_value,sale_date,commission_percent,notes,assigned_to,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $stmt=$pdo->prepare($sql);$stmt->execute(array_merge([$company],$data,[$user['id']]));
    flash('success','Lead created.');
}
redirect('leads.php');
