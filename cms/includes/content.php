<?php
/**
 * Builds the data the public site runs on (window.SITE_DATA) from the database.
 * The shape matches src/data.js, so the front end needs no special cases.
 */

/* dotted flat keys ("home.h1") -> nested array; values that hold JSON arrays are decoded */
function copy_tree(): array
{
    $tree = [];
    foreach (settings_all() as $k => $v) {
        if (!str_starts_with($k, 'copy.')) {
            continue;
        }
        $path = explode('.', substr($k, 5));
        $val = $v;
        if ($v !== '' && ($v[0] === '[' || $v[0] === '{')) {
            $d = json_decode($v, true);
            if ($d !== null) {
                $val = $d;
            }
        }
        $node = &$tree;
        foreach ($path as $i => $seg) {
            if ($i === count($path) - 1) {
                $node[$seg] = $val;
            } else {
                if (!isset($node[$seg]) || !is_array($node[$seg])) {
                    $node[$seg] = [];
                }
                $node = &$node[$seg];
            }
        }
        unset($node);
    }
    return $tree;
}

function csv_list(?string $s): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $s)), fn($x) => $x !== ''));
}

function site_data(): array
{
    $cacheFile = CS_ROOT . '/cache/site.json';
    if (is_file($cacheFile) && ($d = json_decode((string) file_get_contents($cacheFile), true))) {
        return $d;
    }
    $pdo = db();
    $q = fn(string $sql) => $pdo->query($sql)->fetchAll();

    $projects = array_map(fn($p) => [
        'slug' => $p['slug'], 'name' => $p['name'], 'client' => $p['client'], 'location' => $p['location'], 'year' => $p['year'],
        'services' => json_decode((string) $p['services'], true) ?: csv_list($p['services']),
        'desc' => (string) $p['description'], 'logo' => $p['logo'],
        'hero' => $p['hero'], 'heroM' => $p['hero_m'], 'heroVideo' => $p['hero_video'] ?? '', 'heroVideoM' => $p['hero_video_m'] ?? '', 'cat' => trim((string) ($p['category'] ?? '')) ?: 'Hospitality', 'coverV' => $p['cover_v'], 'coverL' => $p['cover_l'],
        'blocks' => json_decode((string) $p['blocks'], true) ?: [],
    ], $q('SELECT * FROM ' . t('projects') . ' WHERE published = 1 ORDER BY sort_order, id'));

    $cats = setting_json('cats', [['hospitality', 'Hospitality'], ['lifestyle', 'Lifestyle'], ['food-beverage', 'Food & Beverage'],
        ['nature', 'Nature'], ['interiors', 'Interiors'], ['drone', 'Drone'], ['product', 'Product']]);

    $photos = array_map(fn($p) => ['k' => $p['ref'], 'cats' => csv_list($p['cats']), 'project' => $p['project_slug'] ?: null, 'loc' => $p['loc']],
        $q('SELECT * FROM ' . t('photos') . ' WHERE published = 1 ORDER BY sort_order, id'));

    $films = array_map(fn($f) => ['k' => $f['poster'], 'video' => $f['video'], 'title' => $f['title'], 'client' => $f['client'], 'loc' => $f['loc'],
        'cat' => $f['cat'], 'project' => $f['project_slug'] ?: null],
        $q('SELECT * FROM ' . t('films') . ' WHERE published = 1 ORDER BY sort_order, id'));

    $services = array_map(fn($s) => ['id' => $s['slug'], 'name' => $s['name'], 'short' => $s['short'], 'body' => (string) $s['body'],
        'deliv' => (string) $s['deliv'], 'img' => $s['img']],
        $q('SELECT * FROM ' . t('services') . ' ORDER BY sort_order, id'));

    $logos = array_map(fn($c) => [$c['logo'], $c['name'], $c['project_slug'] ?: null, (int) $c['w'], (int) $c['h']],
        $q('SELECT * FROM ' . t('clients') . ' WHERE published = 1 ORDER BY sort_order, id'));

    $data = [
        'routing'    => 'path',
        'api'        => '/api/enquiry.php',
        'projects'   => $projects,
        'cats'       => $cats,
        'photos'     => $photos,
        'films'      => $films,
        'homeFilms'  => setting_json('home.films', []),
        'services'   => $services,
        'logos'      => $logos,
        'homeWork'   => setting_json('home.work', []),
        'homeWorkImg' => setting_json('home.workImg', []),
        'homePhotos' => setting_json('home.photos', []),
        'copy'       => copy_tree(),
    ];
    @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $data;
}

/* Every image ref the site uses -> URL, and ref -> [w, h] for layout. */
function site_assets(array $data): array
{
    $refs = [];
    $walk = function ($v) use (&$walk, &$refs) {
        if (is_array($v)) {
            foreach ($v as $x) {
                $walk($x);
            }
        } elseif (is_string($v) && $v !== '' && strlen($v) < 256) {
            $refs[$v] = true;
        }
    };
    $walk($data);
    $manifest = asset_manifest();
    $assets = [];
    $dims = [];
    foreach (array_keys($refs) as $r) {
        if (isset($manifest[$r])) {
            $assets[$r] = '/assets/' . $r . '.webp';
            $dims[$r] = $manifest[$r];
        }
    }
    foreach (db()->query('SELECT path, w, h FROM ' . t('media') . " WHERE kind <> 'video'") as $m) {
        if (isset($refs[$m['path']]) && $m['w']) {
            $dims[$m['path']] = [(int) $m['w'], (int) $m['h']];
        }
    }
    foreach ($data['logos'] as $l) {
        if ($l[0] !== '' && $l[0][0] !== '/') {
            $assets['logo/' . $l[0]] = '/assets/logo/' . $l[0];
        }
    }
    foreach ($data['projects'] as $p) {
        if ($p['logo'] !== '' && $p['logo'][0] !== '/') {
            $assets['logo/' . $p['logo']] = '/assets/logo/' . $p['logo'];
        }
    }
    $avoid = [];
    foreach (['avoid.json', 'avoid_manual.json'] as $f) {
        $path = CS_ROOT . '/assets/' . $f;
        if (is_file($path)) {
            foreach ((json_decode((string) file_get_contents($path), true) ?: []) as $k => $v) {
                if (isset($assets[$k])) {
                    $avoid[$k] = $v;
                }
            }
        }
    }
    return [$assets, $dims, $avoid];
}
