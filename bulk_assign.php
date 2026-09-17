<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';
$admin = require_admin();
verify_csrf();
$pdo = db();

$ids = $_POST['lead_ids'] ?? [];
$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), fn($id) => $id > 0)));
if (!$ids) {
    flash('error', 'Select at least one lead first.');
    redirect('leads.php');
}

$assignmentRaw = $_POST['assigned_to'] ?? '';
$setAssignment = $assignmentRaw !== '__keep__';
$assignedTo = null;
if ($setAssignment && $assignmentRaw !== '') {
    $assignedTo = (int)$assignmentRaw;
    $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND role='caller' AND active=1");
    $s->execute([$assignedTo]);
    if (!(int)$s->fetchColumn()) {
        flash('error', 'Selected caller is invalid.');
        redirect('leads.php');
    }
}

$status = $_POST['bulk_status'] ?? '__keep__';
$setStatus = $status !== '__keep__';
if ($setStatus && !isset(statuses()[$status])) $setStatus = false;

$service = $_POST['bulk_service'] ?? '__keep__';
$setService = $service !== '__keep__';
if ($setService && !isset(service_types()[$service])) $setService = false;

$sets = [];
$params = [];
if ($setAssignment) { $sets[] = 'assigned_to=?'; $params[] = $assignedTo; }
if ($setStatus) {
    $sets[] = 'status=?'; $params[] = $status;
    if ($status === 'demo_sent') $sets[] = 'demo_sent=1';
    if ($status === 'won') $sets[] = 'sale_date=COALESCE(sale_date,CURDATE())';
}
if ($setService) { $sets[] = 'service_type=?'; $params[] = $service; }

if (!$sets) {
    flash('error', 'Choose an assignment, status or service change.');
    redirect('leads.php');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params = array_merge($params, $ids);
$stmt = $pdo->prepare('UPDATE leads SET '.implode(',', $sets).' WHERE id IN ('.$placeholders.')');
$stmt->execute($params);
$changed = $stmt->rowCount();
$ph = implode(',', array_fill(0, count($ids), '?'));
if ($setAssignment) {
    $defaults = $pdo->prepare("UPDATE leads l LEFT JOIN users u ON u.id=l.assigned_to SET l.estimated_value=COALESCE(l.estimated_value,NULLIF(u.standard_price,0)) WHERE l.id IN ($ph)");
    $defaults->execute($ids);
}
if ($setStatus && $status === 'won') {
    $snap = $pdo->prepare("UPDATE leads l LEFT JOIN users u ON u.id=l.assigned_to SET l.commission_percent=COALESCE(l.commission_percent,u.commission_percent), l.sale_value=COALESCE(l.sale_value,NULLIF(u.standard_price,0)), l.sale_date=COALESCE(l.sale_date,CURDATE()) WHERE l.id IN ($ph)");
    $snap->execute($ids);
}
flash('success', $changed.' selected lead(s) updated.');
redirect('leads.php');
