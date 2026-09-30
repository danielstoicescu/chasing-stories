<?php
/**
 * One PDO connection for the whole app. MySQL on the server, SQLite for local testing.
 * Every table name goes through t() so the prefix is applied in one place.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        if (DB_DRIVER === 'sqlite') {
            $pdo = new PDO('sqlite:' . DB_PATH, null, null, $opts);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
        }
    } catch (PDOException $e) {
        if (defined('CS_INSTALLING')) {
            throw $e;   // the installer shows the reason
        }
        http_response_code(500);
        exit(APP_ENV === 'dev' ? 'DB connection failed: ' . $e->getMessage() : 'Service temporarily unavailable.');
    }
    return $pdo;
}

function t(string $table): string
{
    return DB_PREFIX . $table;
}

function db_ready(): bool
{
    try {
        db()->query('SELECT 1 FROM ' . t('settings') . ' LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
