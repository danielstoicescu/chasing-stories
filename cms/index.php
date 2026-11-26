<?php
/**
 * Public site. Every page URL (/, /work, /work/{slug}, /photography/{cat}, ...) lands here:
 * we answer with the right status, title and meta for crawlers and share cards,
 * and hand the content to the front end as window.SITE_DATA.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/seo.php';

if (!db_ready()) {
    http_response_code(503);
    exit('Site is being installed. Open /_install/install.php to finish.');
}

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim(rawurldecode($uri), '/');

/* old WordPress addresses (brief, section 16) keep their search ranking */
$legacy = [
    '/portfolio' => '/work', '/portfolio/interiors' => '/photography/interiors', '/portfolio/lifestyle-photos' => '/photography/lifestyle',
    '/portfolio/location-photos' => '/photography', '/portfolio/food-photos' => '/photography/food-beverage',
    '/portfolio/drone-photos' => '/photography/drone', '/portfolio/nature-photos' => '/photography/nature',
    '/portfolio/night-photography' => '/photography', '/portfolio/activities' => '/photography/lifestyle',
    '/wp-admin' => '/admin/', '/wp-login.php' => '/admin/',
];
$legacy += setting_json('redirects', []);   // extra ones can be added from the panel
if (isset($legacy[$path])) {
    header('Location: ' . $legacy[$path], true, 301);
    exit;
}

$data = site_data();
$C = $data['copy'];
$brand = $C['brand'] ?? 'Chasing Stories';
$bySlug = [];
foreach ($data['projects'] as $p) {
    $bySlug[$p['slug']] = $p;
}
$catIds = array_column($data['cats'], 0);
$svcIds = array_column($data['services'], 'id');

$seg = $path === '/' ? [] : explode('/', substr($path, 1));
$status = 200;
$title = $brand . ' | ' . setting('seo.home_title_suffix', 'Luxury Hospitality Photography & Film');
$desc = setting('seo.description', 'Visual storytelling for luxury hospitality, travel and lifestyle brands. Photography, film and creative production, available worldwide.');
$image = $C['img']['hero'] ?? 'hero';
$pages = ['work' => 'Work', 'photography' => 'Photography', 'film' => 'Film', 'services' => 'Services', 'about' => 'About', 'contact' => 'Contact', 'privacy' => 'Privacy & Cookies'];
$pageDesc = [
    'work' => 'Selected photography and film productions for luxury hotels, resorts, destinations and brands.',
    'photography' => 'Hospitality, lifestyle, food, nature and interiors photography for luxury hotels, resorts and brands.',
    'film' => 'Property films, brand films and short-form stories for luxury hospitality, travel and lifestyle brands.',
    'services' => 'Photography, film, creative direction, lifestyle production, drone and content libraries for luxury hospitality brands.',
    'about' => 'A creative production studio creating photography and film for luxury hotels, resorts and destinations worldwide.',
    'contact' => 'Tell us about your project, location and dates. Available for productions worldwide.',
];
if ($seg) {
    $top = $seg[0];
    if (count($seg) === 1 && isset($pages[$top])) {
        $title = $pages[$top] . ' | ' . $brand;
        $desc = $pageDesc[$top] ?? $desc;
    } elseif (count($seg) === 2 && $top === 'work' && isset($bySlug[$seg[1]])) {
        $p = $bySlug[$seg[1]];
        $title = $p['name'] . ($p['location'] ? ', ' . $p['location'] : '') . ' | ' . $brand;
        $row = db()->prepare('SELECT meta_description FROM ' . t('projects') . ' WHERE slug = ?');
        $row->execute([$p['slug']]);
        $md = (string) $row->fetchColumn();
        $desc = $md !== '' ? $md : trim(implode(', ', $p['services']) . ' for ' . ($p['client'] ?: $p['name']) . ($p['location'] ? ' in ' . $p['location'] : '') . ($p['year'] ? ', ' . $p['year'] : '') . '.');
        $image = $p['coverL'] ?: $p['hero'];
    } elseif (count($seg) === 2 && $top === 'photography' && in_array($seg[1], $catIds, true)) {
        $cn = array_column($data['cats'], 1, 0)[$seg[1]] ?? 'Photography';
        $title = $cn . ' Photography | ' . $brand;
        $desc = $cn . ' photography for luxury hotels, resorts and brands by ' . $brand . '. Selected images from recent productions worldwide.';
    } elseif (count($seg) === 2 && $top === 'services' && in_array($seg[1], $svcIds, true)) {
        $sv = array_column($data['services'], null, 'id')[$seg[1]];
        $title = $sv['name'] . ' for Luxury Hospitality | ' . $brand;
        $desc = trim((string) ($sv['short'] ?: $desc));
    } else {
        $status = 404;
        $title = 'Page not found | ' . $brand;
    }
}
http_response_code($status);

[$assets, $dims, $avoid] = site_assets($data);
$indexable = true;   // the site is live: always open to search engines
$abs = site_base();
$img = ref_url((string) $image);
$head = '<title>' . e($title) . '</title>'
    . '<meta name="description" content="' . e($desc) . '">'
    . ($indexable ? '' : '<meta name="robots" content="noindex,nofollow">')
    . '<link rel="canonical" href="' . e($abs . $path) . '">'
    . '<meta property="og:type" content="website"><meta property="og:site_name" content="' . e($brand) . '">'
    . '<meta property="og:title" content="' . e($title) . '"><meta property="og:description" content="' . e($desc) . '">'
    . ($img ? '<meta property="og:image" content="' . e($abs . $img) . '"><meta name="twitter:card" content="summary_large_image">' : '')
    . '<link rel="icon" href="' . e(setting('seo.favicon', '/assets/favicon.svg')) . '">';
$head .= '<meta property="og:url" content="' . e($abs . $path) . '"><meta property="og:locale" content="en_GB">'
    . '<meta name="twitter:title" content="' . e($title) . '"><meta name="twitter:description" content="' . e($desc) . '">'
    . '<link rel="sitemap" type="application/xml" href="/sitemap.xml">';
$ld = fn(array $o) => '<script type="application/ld+json">' . json_encode($o, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
if ($status === 200 && $seg) {
    $crumbs = [['/', $brand]];
    if (isset($pages[$seg[0]])) $crumbs[] = ['/' . $seg[0], $pages[$seg[0]]];
    if (count($seg) === 2) $crumbs[] = [$path, preg_replace('/ \| .*$/', '', $title)];
    $head .= $ld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[1], 'item' => $abs . $c[0]], $crumbs, array_keys($crumbs))]);
}
if ($status === 200 && count($seg) === 2 && $seg[0] === 'work' && isset($bySlug[$seg[1]])) {
    // a project is a piece of creative work by the studio, with its photographs and film
    $p = $bySlug[$seg[1]];
    $imgs = [];
    foreach (array_merge([(string) ($p['hero'] ?: $p['coverL'])], ...array_map(fn($b) => array_filter((array) ($b['k'] ?? []), 'is_string'), $p['blocks'])) as $k) {
        if (($u = ref_url($k)) && count($imgs) < 12) $imgs[] = $abs . $u;
    }
    $work = ['@context' => 'https://schema.org', '@type' => 'CreativeWork', 'name' => $p['name'], 'url' => $abs . $path, 'description' => $desc,
        'creator' => ['@type' => 'Organization', 'name' => $brand, 'url' => $abs . '/'], 'image' => array_values(array_unique($imgs)),
        'keywords' => implode(', ', array_merge($p['services'], array_filter([$p['client'], $p['location'], 'luxury hospitality'])))];
    if ($p['year']) $work['dateCreated'] = (string) $p['year'];
    if ($p['location']) $work['locationCreated'] = ['@type' => 'Place', 'name' => $p['location']];
    if ($p['client']) $work['sourceOrganization'] = ['@type' => 'Organization', 'name' => $p['client']];
    $head .= $ld($work);
}
if ($path === '/') {
    $head .= $ld(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $brand, 'url' => $abs . '/']);
    // who the studio is, for Google's knowledge panel
    $K = $data['copy']['contact'] ?? [];
    $org = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $brand, 'url' => $abs . '/',
        'description' => $desc, 'logo' => $abs . '/assets/favicon.svg',
        'sameAs' => array_values(array_filter([$K['instagram'] ?? '', $K['linkedin'] ?? '']))];
    if (!empty($K['email'])) $org['email'] = $K['email'];
    if ($img) $org['image'] = $abs . $img;
    $head .= '<script type="application/ld+json">' . json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
}

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
$boot = 'window.SITE_DATA=' . json_encode($data, $flags) . ";\n"
    . 'const ASSETS=' . json_encode((object) $assets, $flags) . ";\n"
    . 'const MANIFEST=' . json_encode((object) $dims, $flags) . ";\n"
    . 'const AVOID=' . json_encode((object) $avoid, $flags) . ';';

$tpl = (string) file_get_contents(__DIR__ . '/site.template.html');
header('Content-Type: text/html; charset=utf-8');
if (!$indexable) {
    header('X-Robots-Tag: noindex, nofollow');
}
// readable page for crawlers and previews; the front end swaps in the designed page on start
$ssr = $status === 200 ? seo_snapshot($seg, $data, $desc) : '';
$head .= '<style>.ssr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}</style>'
    . '<noscript><style>.ssr{position:static;width:auto;height:auto;clip-path:none;white-space:normal;max-width:900px;margin:120px auto;padding:0 16px}.ssr img{max-width:100%;height:auto}</style></noscript>';
echo str_replace(['<!--HEAD-->', '/*BOOT*/', '<main id="app"></main>'], [$head, $boot, '<main id="app">' . $ssr . '</main>'], $tpl);
