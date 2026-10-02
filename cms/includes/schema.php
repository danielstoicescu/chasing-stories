<?php
/**
 * Tables. Written once for MySQL and translated for SQLite (local testing).
 */
function schema_statements(): array
{
    $sql = [
        'settings'  => 'k VARCHAR(120) NOT NULL PRIMARY KEY, v MEDIUMTEXT',
        'admins'    => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, username VARCHAR(60) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
                        display_name VARCHAR(120) NOT NULL DEFAULT \'\', role VARCHAR(20) NOT NULL DEFAULT \'editor\', last_login DATETIME NULL, created_at DATETIME NULL',
        'projects'  => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, slug VARCHAR(120) NOT NULL UNIQUE, name VARCHAR(190) NOT NULL, client VARCHAR(190) NOT NULL DEFAULT \'\',
                        location VARCHAR(190) NOT NULL DEFAULT \'\', year VARCHAR(20) NOT NULL DEFAULT \'\', services TEXT, description TEXT,
                        hero VARCHAR(255) NOT NULL DEFAULT \'\', hero_m VARCHAR(255) NOT NULL DEFAULT \'\', hero_video VARCHAR(255) NOT NULL DEFAULT \'\', hero_video_m VARCHAR(255) NOT NULL DEFAULT \'\', cover_v VARCHAR(255) NOT NULL DEFAULT \'\', cover_l VARCHAR(255) NOT NULL DEFAULT \'\',
                        logo VARCHAR(255) NOT NULL DEFAULT \'\', blocks MEDIUMTEXT, meta_description VARCHAR(255) NOT NULL DEFAULT \'\',
                        published INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, updated_at DATETIME NULL',
        'clients'   => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(190) NOT NULL, logo VARCHAR(255) NOT NULL DEFAULT \'\', project_slug VARCHAR(120) NOT NULL DEFAULT \'\',
                        w INTEGER NOT NULL DEFAULT 50, h INTEGER NOT NULL DEFAULT 40, published INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0',
        'photos'    => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, ref VARCHAR(255) NOT NULL, cats VARCHAR(255) NOT NULL DEFAULT \'\', project_slug VARCHAR(120) NOT NULL DEFAULT \'\',
                        loc VARCHAR(190) NOT NULL DEFAULT \'\', alt VARCHAR(255) NOT NULL DEFAULT \'\', published INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0',
        'films'     => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, poster VARCHAR(255) NOT NULL DEFAULT \'\', video VARCHAR(500) NOT NULL DEFAULT \'\', title VARCHAR(190) NOT NULL,
                        client VARCHAR(190) NOT NULL DEFAULT \'\', loc VARCHAR(190) NOT NULL DEFAULT \'\', cat VARCHAR(60) NOT NULL DEFAULT \'Hospitality\',
                        project_slug VARCHAR(120) NOT NULL DEFAULT \'\', published INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0',
        'services'  => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, slug VARCHAR(120) NOT NULL UNIQUE, name VARCHAR(190) NOT NULL, short VARCHAR(255) NOT NULL DEFAULT \'\',
                        body TEXT, deliv TEXT, img VARCHAR(255) NOT NULL DEFAULT \'\', sort_order INTEGER NOT NULL DEFAULT 0',
        'media'     => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, path VARCHAR(255) NOT NULL, w INTEGER NOT NULL DEFAULT 0, h INTEGER NOT NULL DEFAULT 0,
                        kind VARCHAR(20) NOT NULL DEFAULT \'image\', original VARCHAR(190) NOT NULL DEFAULT \'\', created_at DATETIME NULL',
        'enquiries' => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, created_at DATETIME NULL, name VARCHAR(190) NOT NULL DEFAULT \'\', company VARCHAR(190) NOT NULL DEFAULT \'\',
                        email VARCHAR(190) NOT NULL DEFAULT \'\', website VARCHAR(255) NOT NULL DEFAULT \'\', location VARCHAR(190) NOT NULL DEFAULT \'\',
                        dates VARCHAR(190) NOT NULL DEFAULT \'\', type VARCHAR(120) NOT NULL DEFAULT \'\', details TEXT, source VARCHAR(190) NOT NULL DEFAULT \'\',
                        page VARCHAR(255) NOT NULL DEFAULT \'\', ip VARCHAR(45) NOT NULL DEFAULT \'\', status VARCHAR(20) NOT NULL DEFAULT \'new\',
                        mail_status VARCHAR(255) NOT NULL DEFAULT \'\', notes TEXT',
        'rate'      => 'id INTEGER PRIMARY KEY AUTO_INCREMENT, ip VARCHAR(45) NOT NULL, action VARCHAR(40) NOT NULL, ts INTEGER NOT NULL',
    ];
    $out = [];
    foreach ($sql as $table => $cols) {
        if (DB_DRIVER === 'sqlite') {
            $cols = str_replace(['INTEGER PRIMARY KEY AUTO_INCREMENT', 'MEDIUMTEXT'], ['INTEGER PRIMARY KEY AUTOINCREMENT', 'TEXT'], $cols);
            $out[] = 'CREATE TABLE IF NOT EXISTS ' . t($table) . ' (' . $cols . ')';
        } else {
            $out[] = 'CREATE TABLE IF NOT EXISTS ' . t($table) . ' (' . $cols . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
    }
    $out[] = 'CREATE INDEX ' . (DB_DRIVER === 'sqlite' ? 'IF NOT EXISTS ' : '') . t('rate_ix') . ' ON ' . t('rate') . ' (ip, action, ts)';
    return $out;
}

/** Columns added after the first install; runs once per version from the panel. */
function schema_upgrade(): void
{
    if ((int) setting('schema.v', '1') >= 2) {
        return;
    }
    foreach (["hero_video VARCHAR(255) NOT NULL DEFAULT ''", "hero_video_m VARCHAR(255) NOT NULL DEFAULT ''"] as $col) {
        try {
            db()->exec('ALTER TABLE ' . t('projects') . ' ADD COLUMN ' . $col);
        } catch (Throwable $e) {
            // already there (fresh install)
        }
    }
    setting_save(['schema.v' => '2']);
}

