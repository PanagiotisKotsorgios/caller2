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
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(180) NOT NULL,
        contact_name VARCHAR(120) NULL,
        phone VARCHAR(80) NULL,
        email VARCHAR(180) NULL,
        city VARCHAR(120) NULL,
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
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_sale_date (sale_date),
        INDEX idx_follow_up_date (follow_up_date),
        CONSTRAINT fk_leads_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_leads_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $done = true;
}

function app_name(): string
{
    return env('APP_NAME', 'Caller CRM') ?? 'Caller CRM';
}
