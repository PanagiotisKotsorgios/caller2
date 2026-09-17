<?php

declare(strict_types=1);

session_start();

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Athens');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', 'mysql');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'crm');
    $user = env('DB_USER', 'crm');
    $pass = env('DB_PASSWORD', 'crm_password');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    ensure_schema($pdo);
    return $pdo;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','caller') NOT NULL DEFAULT 'caller',
        commission_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
        standard_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lead_import_batches (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        imported_by INT UNSIGNED NULL,
        assigned_to INT UNSIGNED NULL,
        imported_count INT UNSIGNED NOT NULL DEFAULT 0,
        skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_imported_by (imported_by),
        INDEX idx_batch_assigned_to (assigned_to),
        CONSTRAINT fk_batches_imported_by FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_batches_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(180) NOT NULL,
        contact_name VARCHAR(120) NULL,
        phone VARCHAR(80) NULL,
        email VARCHAR(180) NULL,
        address VARCHAR(255) NULL,
        city VARCHAR(120) NULL,
        region VARCHAR(120) NULL,
        category VARCHAR(180) NULL,
        subcategory VARCHAR(180) NULL,
        source_comments VARCHAR(255) NULL,
        service_type VARCHAR(30) NOT NULL DEFAULT 'website',
        status VARCHAR(40) NOT NULL DEFAULT 'new',
        demo_sent TINYINT(1) NOT NULL DEFAULT 0,
        follow_up_date DATE NULL,
        estimated_value DECIMAL(10,2) NULL,
        sale_value DECIMAL(10,2) NULL,
        sale_date DATE NULL,
        commission_percent DECIMAL(5,2) NULL,
        notes TEXT NULL,
        assigned_to INT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        import_batch_id INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_sale_date (sale_date),
        INDEX idx_follow_up_date (follow_up_date),
        INDEX idx_city (city),
        INDEX idx_region (region),
        INDEX idx_import_batch_id (import_batch_id),
        CONSTRAINT fk_leads_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_leads_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Small automatic migrations so an already-running installation upgrades itself on redeploy.
    if (!column_exists($pdo, 'users', 'standard_price')) {
        $pdo->exec("ALTER TABLE users ADD standard_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER commission_percent");
    }

    $leadColumns = [
        'address' => "ALTER TABLE leads ADD address VARCHAR(255) NULL AFTER email",
        'region' => "ALTER TABLE leads ADD region VARCHAR(120) NULL AFTER city",
        'category' => "ALTER TABLE leads ADD category VARCHAR(180) NULL AFTER region",
        'subcategory' => "ALTER TABLE leads ADD subcategory VARCHAR(180) NULL AFTER category",
        'source_comments' => "ALTER TABLE leads ADD source_comments VARCHAR(255) NULL AFTER subcategory",
        'import_batch_id' => "ALTER TABLE leads ADD import_batch_id INT UNSIGNED NULL AFTER created_by",
    ];
    foreach ($leadColumns as $column => $sql) {
        if (!column_exists($pdo, 'leads', $column)) {
            $pdo->exec($sql);
        }
    }
    if (!index_exists($pdo, 'leads', 'idx_import_batch_id')) {
        $pdo->exec("ALTER TABLE leads ADD INDEX idx_import_batch_id (import_batch_id)");
    }

    $done = true;
}

function app_name(): string
{
    return env('APP_NAME', 'Caller CRM') ?? 'Caller CRM';
}
