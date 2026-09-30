<?php
/**
 * Public site. Every page URL (/, /work, /work/{slug}, /photography/{cat}, ...) lands here:
 * we answer with the right status, title and meta for crawlers and share cards,
 * and hand the content to the front end as window.SITE_DATA.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/content.php';

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
        $title = 'Photography | ' . $brand;
    } elseif (count($seg) === 2 && $top === 'services' && in_array($seg[1], $svcIds, true)) {
        $title = 'Services | ' . $brand;
    } else {
        $status = 404;
        $title = 'Page not found | ' . $brand;
    }
}
http_response_code($status);

[$assets, $dims, $avoid] = site_assets($data);
$indexable = setting('seo.index', '0') === '1';
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
echo str_replace(['<!--HEAD-->', '/*BOOT*/'], [$head, $boot], $tpl);
