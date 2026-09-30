<?php
/**
 * One-time installer: creates the tables, imports the current site content and the first admin account.
 * Refuses to run again once an admin exists. Delete the _install folder afterwards.
 */
define('CS_INSTALLING', true);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/schema.php';

$installed = false;
try {
    $installed = (int) db()->query('SELECT COUNT(*) FROM ' . t('admins'))->fetchColumn() > 0;
} catch (Throwable $e) {
}
$msg = '';
$err = '';

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = strtolower(trim((string) ($_POST['u'] ?? '')));
    $p = (string) ($_POST['p'] ?? '');
    $to = trim((string) ($_POST['to'] ?? ''));
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $u)) {
        $err = 'Utilizatorul: 3–40 caractere, litere mici, cifre, punct, cratimă.';
    } elseif (strlen($p) < 10) {
        $err = 'Parola trebuie să aibă cel puțin 10 caractere.';
    } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = 'Adresa pentru mesaje nu e validă.';
    } elseif (APP_SECRET === 'CHANGE_ME_to_a_long_random_string' || strlen(APP_SECRET) < 32) {
        $err = 'Completează APP_SECRET în includes/config.php (cel puțin 32 de caractere aleatorii).';
    } else {
        $pdo = db();
        foreach (schema_statements() as $sql) {
            try { $pdo->exec($sql); } catch (Throwable $e) { if (stripos($e->getMessage(), 'exist') === false && stripos($e->getMessage(), 'Duplicate') === false) throw $e; }
        }
        $seed = json_decode((string) file_get_contents(__DIR__ . '/seed.json'), true);
        $pdo->beginTransaction();
        foreach (['settings', 'projects', 'clients', 'photos', 'films', 'services'] as $tb) {
            $pdo->exec('DELETE FROM ' . t($tb));
        }
        $now = date('Y-m-d H:i:s');
        $ins = fn(string $table, array $row) => $pdo->prepare('INSERT INTO ' . t($table) . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
        foreach ($seed['projects'] as $i => $p2) {
            $ins('projects', ['slug' => $p2['slug'], 'name' => $p2['name'], 'client' => $p2['client'] ?? '', 'location' => $p2['location'] ?? '', 'year' => $p2['year'] ?? '',
                'services' => json_encode($p2['services'] ?? [], JSON_UNESCAPED_UNICODE), 'description' => $p2['desc'] ?? '', 'hero' => $p2['hero'] ?? '', 'hero_m' => $p2['heroM'] ?? '',
                'cover_v' => $p2['coverV'] ?? '', 'cover_l' => $p2['coverL'] ?? '', 'logo' => $p2['logo'] ?? '', 'blocks' => json_encode($p2['blocks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'meta_description' => '', 'published' => 1, 'sort_order' => ($i + 1) * 10, 'updated_at' => $now]);
        }
        foreach ($seed['logos'] as $i => $l) {
            $ins('clients', ['name' => $l[1], 'logo' => $l[0], 'project_slug' => $l[2] ?? '', 'w' => $l[3], 'h' => $l[4], 'published' => 1, 'sort_order' => ($i + 1) * 10]);
        }
        foreach ($seed['photos'] as $i => $ph) {
            $ins('photos', ['ref' => $ph['k'], 'cats' => implode(',', $ph['cats']), 'project_slug' => $ph['project'] ?? '', 'loc' => $ph['loc'] ?? '', 'alt' => '', 'published' => 1, 'sort_order' => ($i + 1) * 10]);
        }
        foreach ($seed['films'] as $i => $f) {
            $ins('films', ['poster' => $f['k'], 'video' => $f['video'] ?? '', 'title' => $f['title'], 'client' => $f['client'] ?? '', 'loc' => $f['loc'] ?? '', 'cat' => $f['cat'] ?? 'Hospitality',
                'project_slug' => $f['project'] ?? '', 'published' => 1, 'sort_order' => ($i + 1) * 10]);
        }
        foreach ($seed['services'] as $i => $s) {
            $ins('services', ['slug' => $s['id'], 'name' => $s['name'], 'short' => $s['short'], 'body' => $s['body'], 'deliv' => $s['deliv'], 'img' => $s['img'], 'sort_order' => ($i + 1) * 10]);
        }
        $settings = ['cats' => $seed['cats'], 'home.work' => $seed['homeWork'], 'home.photos' => $seed['homePhotos'], 'home.films' => $seed['homeFilms'],
            'mail.to' => $to, 'mail.autoreply' => '1', 'seo.index' => '0'];
        $flat = function ($arr, $prefix) use (&$flat, &$settings) {
            foreach ($arr as $k => $v) {
                if (is_array($v) && array_keys($v) !== range(0, count($v) - 1)) {
                    $flat($v, $prefix . $k . '.');
                } else {
                    $settings[$prefix . $k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $v;
                }
            }
        };
        $flat($seed['copy'], 'copy.');
        foreach ($settings as $k => $v) {
            $ins('settings', ['k' => $k, 'v' => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v]);
        }
        $ins('admins', ['username' => $u, 'password_hash' => password_hash($p, PASSWORD_DEFAULT), 'display_name' => trim((string) ($_POST['n'] ?? '')), 'role' => 'admin', 'created_at' => $now]);
        $pdo->commit();
        @unlink(CS_ROOT . '/cache/site.json');
        $installed = true;
        $msg = 'Gata. Conținutul a fost importat și contul a fost creat.';
    }
}
?><!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Instalare · Chasing Stories</title><link rel="stylesheet" href="../assets/admin.css"></head><body>
<div class="login"><form method="post">
  <h1>Instalare</h1>
  <?php if ($installed): ?>
    <div class="flash"><?= e($msg ?: 'Site-ul e deja instalat.') ?></div>
    <p><strong>Pasul următor:</strong> șterge folderul <code>_install</code> de pe server.</p>
    <a class="btn" href="../admin/">Intră în panou</a> <a class="btn ghost" href="../">Vezi site-ul</a>
  <?php else:
    $req = [
        'PHP 8.1 sau mai nou (acum ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.1', '>='),
        'Extensia pdo_mysql' => extension_loaded('pdo_mysql') || DB_DRIVER === 'sqlite',
        'Extensia gd, cu WebP (redimensionează pozele)' => function_exists('imagewebp'),
        
        'Extensia openssl (parola SMTP criptată)' => function_exists('openssl_encrypt'),
        'Folderul uploads/ se poate scrie' => is_writable(CS_ROOT . '/uploads'),
        'Folderul cache/ se poate scrie' => is_writable(CS_ROOT . '/cache'),
        'Conexiunea la baza de date (datele din includes/config.php)' => (function () { try { db()->query('SELECT 1'); return true; } catch (Throwable $e) { return false; } })(),
    ]; ?>
    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:4px;font-size:.88rem">
      <?php foreach ($req as $label => $ok): ?><li><?= $ok ? '✓' : '<strong style="color:#B3261E">✕</strong>' ?> <?= e($label) ?></li><?php endforeach ?>
    </ul>
    <?php if ($err): ?><div class="flash bad"><?= e($err) ?></div><?php endif ?>
    <p class="muted">Creează tabelele, importă conținutul actual al site-ului și primul cont de administrator.</p>
    <label class="fld"><span class="lb">Utilizator administrator</span><input type="text" name="u" required value="<?= e($_POST['u'] ?? '') ?>"></label>
    <label class="fld"><span class="lb">Nume afișat</span><input type="text" name="n" value="<?= e($_POST['n'] ?? '') ?>"></label>
    <label class="fld"><span class="lb">Parolă (min. 10 caractere)</span><input type="password" name="p" required></label>
    <label class="fld"><span class="lb">Mesajele din formular merg la</span><input type="email" name="to" required value="<?= e($_POST['to'] ?? 'begin@chasingstories.org') ?>"></label>
    <button class="btn" type="submit">Instalează</button>
  <?php endif ?>
</form></div></body></html>
