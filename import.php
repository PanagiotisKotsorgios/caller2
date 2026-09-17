<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/xlsx.php';

$user = require_login();
$pdo = db();
$isAdmin = $user['role'] === 'admin';
$callers = $isAdmin
    ? $pdo->query("SELECT id,name,commission_percent,standard_price FROM users WHERE role='caller' AND active=1 ORDER BY name")->fetchAll()
    : [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Admin may choose any caller or leave leads unassigned.
    // A caller can only import leads directly into their own account.
    $assignedTo = $isAdmin ? ((int)($_POST['assigned_to'] ?? 0) ?: null) : (int)$user['id'];
    $service = $_POST['service_type'] ?? 'website';
    if (!isset(service_types()[$service])) $service = 'website';
    $duplicateMode = ($_POST['duplicate_mode'] ?? 'skip') === 'all' ? 'all' : 'skip';

    $callerSettings = null;
    if ($assignedTo) {
        $cs = $pdo->prepare("SELECT id,commission_percent,standard_price FROM users WHERE id=? AND role='caller' AND active=1");
        $cs->execute([$assignedTo]);
        $callerSettings = $cs->fetch() ?: null;
        if (!$callerSettings) {
            if ($isAdmin) {
                $assignedTo = null;
            } else {
                http_response_code(403);
                exit('Your caller account is not active.');
            }
        }
    }

    if (!isset($_FILES['xlsx']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please choose a valid .xlsx file.');
        redirect('import.php');
    }
    if (($_FILES['xlsx']['size'] ?? 0) > 20 * 1024 * 1024) {
        flash('error', 'The XLSX file is larger than 20 MB.');
        redirect('import.php');
    }
    $name = $_FILES['xlsx']['name'] ?? '';
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
        flash('error', 'Only .xlsx files are supported.');
        redirect('import.php');
    }

    try {
        $parsed = xlsx_read_rows($_FILES['xlsx']['tmp_name']);
        $rows = $parsed['rows'];
        if (!$rows) throw new RuntimeException('The workbook contains no rows.');

        $headerIndex = null;
        $map = [];
        foreach ($rows as $i => $row) {
            $candidate = import_header_map($row);
            if (isset($candidate['company_name'])) {
                $headerIndex = $i;
                $map = $candidate;
                break;
            }
        }
        if ($headerIndex === null) throw new RuntimeException('Could not detect the lead header row.');

        $get = static function(array $row, array $map, string $field): ?string {
            if (!isset($map[$field])) return null;
            $v = trim((string)($row[$map[$field]] ?? ''));
            return $v === '' ? null : $v;
        };

        $insert = $pdo->prepare("INSERT INTO leads
            (company_name,contact_name,phone,email,address,city,region,category,subcategory,source_comments,service_type,status,demo_sent,estimated_value,sale_value,sale_date,commission_percent,assigned_to,created_by,import_batch_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $duplicate = $pdo->prepare("SELECT id FROM leads WHERE company_name=? AND COALESCE(phone,'')=COALESCE(?, '') AND COALESCE(city,'')=COALESCE(?, '') LIMIT 1");

        $imported = 0; $skipped = 0; $empty = 0;
        $pdo->beginTransaction();
        try {
            $batchStmt = $pdo->prepare("INSERT INTO lead_import_batches (filename, imported_by, assigned_to) VALUES (?,?,?)");
            $batchStmt->execute([$name, $user['id'], $assignedTo]);
            $batchId = (int)$pdo->lastInsertId();
            for ($i = $headerIndex + 1, $count = count($rows); $i < $count; $i++) {
                $row = $rows[$i];
                $company = $get($row, $map, 'company_name');
                if (!$company) { $empty++; continue; }
                $phone = $get($row, $map, 'phone');
                $city = $get($row, $map, 'city');

                if ($duplicateMode === 'skip') {
                    $duplicate->execute([$company, $phone, $city]);
                    if ($duplicate->fetchColumn()) { $skipped++; continue; }
                }

                $rawStatus = $get($row, $map, 'status') ?? '';
                $status = import_status_value($rawStatus);
                $standardPrice = $callerSettings && (float)$callerSettings['standard_price'] > 0 ? (float)$callerSettings['standard_price'] : null;
                $won = $status === 'won';

                $insert->execute([
                    $company,
                    $get($row, $map, 'contact_name'),
                    $phone,
                    $get($row, $map, 'email'),
                    $get($row, $map, 'address'),
                    $city,
                    $get($row, $map, 'region'),
                    $get($row, $map, 'category'),
                    $get($row, $map, 'subcategory'),
                    $get($row, $map, 'source_comments'),
                    $service,
                    $status,
                    $status === 'demo_sent' ? 1 : 0,
                    $standardPrice,
                    $won ? $standardPrice : null,
                    $won ? date('Y-m-d') : null,
                    $won && $callerSettings ? (float)$callerSettings['commission_percent'] : null,
                    $assignedTo,
                    $user['id'],
                    $batchId,
                ]);
                $imported++;
            }
            $batchUpdate = $pdo->prepare("UPDATE lead_import_batches SET imported_count=?, skipped_count=? WHERE id=?");
            $batchUpdate->execute([$imported, $skipped, $batchId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $result = [
            'sheet' => $parsed['sheet'],
            'imported' => $imported,
            'skipped' => $skipped,
            'empty' => $empty,
            'columns' => array_keys($map),
            'batch_id' => $batchId,
        ];
    } catch (Throwable $e) {
        $result = ['error' => $e->getMessage()];
    }
}

$pageTitle = 'Import XLSX';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Import XLSX leads</h1>
        <p><?= $isAdmin
            ? 'Import lead lists and optionally assign them to a caller.'
            : 'Import your XLSX lead list. Every imported lead is automatically registered to you and visible to the administrator.' ?></p>
    </div>
    <a class="btn" href="leads.php">← Back to leads</a>
</div>

<?php if ($result && isset($result['error'])): ?>
    <div class="flash error"><?= e($result['error']) ?></div>
<?php elseif ($result): ?>
    <div class="flash success"><strong>Import complete.</strong> <?= (int)$result['imported'] ?> leads imported, <?= (int)$result['skipped'] ?> duplicates skipped. Sheet: <?= e($result['sheet']) ?>. Import batch #<?= (int)$result['batch_id'] ?>.</div>
    <section class="panel"><strong>Detected columns:</strong> <?= e(implode(', ', $result['columns'])) ?><div class="form-actions import-result-actions"><a class="btn primary" href="leads.php">Open imported leads</a><?php if ($isAdmin): ?><a class="btn" href="imports.php">Import history</a><?php endif; ?></div></section>
<?php endif; ?>

<form class="panel form-grid" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label class="full">XLSX file *<input type="file" name="xlsx" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>

    <?php if ($isAdmin): ?>
    <label>Assign all imported leads to
        <select name="assigned_to"><option value="">Unassigned — distribute later</option><?php foreach($callers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> · <?= e((string)$c['commission_percent']) ?>% · standard <?= money($c['standard_price']) ?></option><?php endforeach; ?></select>
    </label>
    <?php else: ?>
    <div class="info-box"><strong>Assignment:</strong> all imported leads will automatically be assigned to your account. You cannot import leads for another caller.</div>
    <?php endif; ?>

    <label>Default service
        <select name="service_type"><?php foreach(service_types() as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
    </label>
    <label>Duplicates
        <select name="duplicate_mode"><option value="skip">Skip same business + phone + city</option><option value="all">Import everything</option></select>
    </label>
    <?php if ($isAdmin): ?>
    <div class="info-box"><strong>After import:</strong> leave leads unassigned if you want to select individual rows in Leads and assign only those rows to a specific caller.</div>
    <?php else: ?>
    <div class="info-box"><strong>Admin tracking:</strong> the administrator can see that these leads were registered by you, while other callers cannot see them.</div>
    <?php endif; ?>
    <div class="full form-actions"><button class="btn primary" type="submit">Import XLSX</button></div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
