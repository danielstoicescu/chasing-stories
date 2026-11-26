<?php
/* /sitemap.xml: every public page, with the photographs and films on it, so Google Images and Video find them too. */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/content.php';
header('Content-Type: application/xml; charset=utf-8');

$base = site_base();
$x = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$abs = fn(string $ref) => ($u = ref_url($ref)) === '' ? '' : (preg_match('#^https?://#', $u) ? $u : $base . $u);
$shipped = fn(string $k) => $k !== '' && is_file(CS_ROOT . '/assets/video/' . $k . '.mp4') ? '/assets/video/' . $k . '.mp4' : '';

$data = db_ready() ? site_data() : ['projects' => [], 'photos' => [], 'films' => [], 'cats' => [], 'services' => [], 'copy' => []];
$brand = $data['copy']['brand'] ?? 'Chasing Stories';
$urls = [];   // [path, changefreq, priority, lastmod, images[[loc,title]], videos[[content,thumb,title,desc]]]

$urls[] = ['/', 'weekly', '1.0', '', [[$abs((string) ($data['copy']['img']['hero'] ?? 'hero')), $brand]], []];
$urls[] = ['/work', 'weekly', '0.9', '', [], []];

$updated = [];
if (db_ready()) {
    foreach (db()->query('SELECT slug, updated_at FROM ' . t('projects')) as $r) {
        $updated[$r['slug']] = $r['updated_at'] ? substr((string) $r['updated_at'], 0, 10) : '';
    }
}
foreach ($data['projects'] as $p) {
    $title = $p['name'] . ($p['location'] ? ', ' . $p['location'] : '');
    $imgs = [];
    foreach (array_merge([(string) $p['hero'], (string) $p['coverL'], (string) $p['coverV']], ...array_map(fn($b) => array_filter((array) ($b['k'] ?? []), 'is_string'), $p['blocks'])) as $k) {
        $u = $abs($k);
        if ($u && !isset($imgs[$u]) && count($imgs) < 30) $imgs[$u] = [$u, $title];
    }
    $vids = [];
    $hv = (string) ($p['heroVideo'] ?? '') ?: $shipped((string) $p['hero']);
    if ($hv !== '') {
        $vids[] = [$hv[0] === '/' ? $base . $hv : $hv, $abs((string) ($p['hero'] ?: $p['coverL'])), $title . ' · ' . $brand,
            trim(implode(', ', $p['services']) . ' for ' . ($p['client'] ?: $p['name']) . ($p['location'] ? ' in ' . $p['location'] : '') . '.')];
    }
    foreach ($p['blocks'] as $b) {
        if (!empty($b['src']) && ($b['t'] ?? '') !== 'text') {
            $k = (array) ($b['k'] ?? []);
            $vids[] = [$b['src'][0] === '/' ? $base . $b['src'] : $b['src'], $abs((string) end($k)), $title . ' · film', 'Film from the ' . $title . ' production by ' . $brand . '.'];
        }
    }
    $urls[] = ['/work/' . rawurlencode($p['slug']), 'monthly', '0.8', $updated[$p['slug']] ?? '', array_values($imgs), $vids];
}

$ph = [];
foreach (array_slice($data['photos'], 0, 200) as $p) {
    if ($u = $abs((string) $p['k'])) $ph[] = [$u, trim(($p['loc'] ?: 'Photography') . ' · ' . $brand)];
}
$urls[] = ['/photography', 'monthly', '0.7', '', $ph, []];
foreach ($data['cats'] as $c) {
    $urls[] = ['/photography/' . rawurlencode($c[0]), 'monthly', '0.5', '', [], []];
}

$fv = [];
foreach ($data['films'] as $f) {
    $src = (string) $f['video'];
    if (str_starts_with($src, 'poster:')) $src = $shipped(substr($src, 7));
    if ($src === '') $src = $shipped((string) $f['k']);
    if ($src !== '') {
        $fv[] = [$src[0] === '/' ? $base . $src : $src, $abs((string) $f['k']), trim($f['title'] . ($f['client'] && $f['client'] !== $f['title'] ? ' · ' . $f['client'] : '')),
            trim('Film by ' . $brand . ($f['loc'] ? ', ' . $f['loc'] : '') . '.')];
    }
}
$urls[] = ['/film', 'monthly', '0.7', '', [], $fv];
$urls[] = ['/services', 'monthly', '0.7', '', [], []];
foreach ($data['services'] as $s) {
    $urls[] = ['/services/' . rawurlencode($s['id']), 'monthly', '0.5', '', [], []];
}
$urls[] = ['/about', 'monthly', '0.6', '', [], []];
$urls[] = ['/contact', 'yearly', '0.6', '', [], []];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
    . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
foreach ($urls as [$path, $freq, $prio, $mod, $imgs, $vids]) {
    echo '<url><loc>' . $x($base . $path) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . '<changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority>';
    foreach ($imgs as [$loc, $t]) {
        if ($loc) echo '<image:image><image:loc>' . $x($loc) . '</image:loc></image:image>';
    }
    foreach ($vids as [$c, $th, $t, $d]) {
        if ($c && $th) echo '<video:video><video:thumbnail_loc>' . $x($th) . '</video:thumbnail_loc><video:title>' . $x($t) . '</video:title><video:description>' . $x($d) . '</video:description><video:content_loc>' . $x($c) . '</video:content_loc></video:video>';
    }
    echo "</url>\n";
}
echo "</urlset>\n";
