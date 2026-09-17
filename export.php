<?php
require __DIR__.'/config.php'; require __DIR__.'/includes/functions.php'; require __DIR__.'/includes/auth.php';
$user=require_login(); $pdo=db(); $type=$_GET['type']??'leads';
header('Content-Type: text/csv; charset=UTF-8'); echo "\xEF\xBB\xBF";
$out=fopen('php://output','w');
if($type==='payouts'){
 if($user['role']!=='admin'){http_response_code(403);exit;}
 $month=$_GET['month']??date('Y-m'); if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m'); $start=$month.'-01';$end=date('Y-m-d',strtotime($start.' +1 month'));
 header('Content-Disposition: attachment; filename="caller-payouts-'.$month.'.csv"');
 fputcsv($out,['Caller','Sold clients','Sales value EUR','Amount owed EUR']);
 $s=$pdo->prepare("SELECT u.name,COUNT(l.id) sold_count,COALESCE(SUM(l.sale_value),0) revenue,COALESCE(SUM(l.sale_value*COALESCE(l.commission_percent,u.commission_percent,0)/100),0) owed FROM users u LEFT JOIN leads l ON l.assigned_to=u.id AND l.status='won' AND l.sale_date>=? AND l.sale_date<? WHERE u.role='caller' GROUP BY u.id ORDER BY u.name");$s->execute([$start,$end]);
 foreach($s as $r)fputcsv($out,[$r['name'],$r['sold_count'],number_format((float)$r['revenue'],2,'.',''),number_format((float)$r['owed'],2,'.','')]);
 fclose($out);exit;
}

$q=trim($_GET['q']??'');$status=trim($_GET['status']??'');$service=trim($_GET['service']??'');$caller=(int)($_GET['caller']??0);$where=[];$params=[];
if($user['role']!=='admin'){$where[]='l.assigned_to=:uid';$params[':uid']=$user['id'];}
if($q!==''){$where[]='(l.company_name LIKE :q OR l.contact_name LIKE :q OR l.phone LIKE :q OR l.email LIKE :q OR l.city LIKE :q)';$params[':q']="%{$q}%";}
if($status!==''){$where[]='l.status=:status';$params[':status']=$status;}if($service!==''){$where[]='l.service_type=:service';$params[':service']=$service;}if($user['role']==='admin'&&$caller>0){$where[]='l.assigned_to=:caller';$params[':caller']=$caller;}
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
header('Content-Disposition: attachment; filename="leads-'.date('Y-m-d').'.csv"');
fputcsv($out,['ID','Company','Contact','Phone','Email','City','Service','Status','Demo sent','Follow up','Estimated EUR','Sale EUR','Sale date','Caller','Commission %','Notes']);
$s=$pdo->prepare("SELECT l.*,u.name caller_name,u.commission_percent caller_default FROM leads l LEFT JOIN users u ON u.id=l.assigned_to {$whereSql} ORDER BY l.updated_at DESC");$s->execute($params);
foreach($s as $r){$rate=$r['commission_percent']!==null?$r['commission_percent']:$r['caller_default'];fputcsv($out,[$r['id'],$r['company_name'],$r['contact_name'],$r['phone'],$r['email'],$r['city'],service_label($r['service_type']),status_label($r['status']),$r['demo_sent']?'Yes':'No',$r['follow_up_date'],$r['estimated_value'],$r['sale_value'],$r['sale_date'],$r['caller_name'],$rate,$r['notes']]);}
fclose($out);
