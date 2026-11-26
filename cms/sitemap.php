<?php
/* /sitemap.xml: every public page, so Google finds the projects. */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/content.php';
header('Content-Type: application/xml; charset=utf-8');
$base = site_base();
$urls = [['/', 'weekly', '1.0'], ['/work', 'weekly', '0.9'], ['/photography', 'monthly', '0.7'], ['/film', 'monthly', '0.7'],
    ['/services', 'monthly', '0.7'], ['/about', 'monthly', '0.6'], ['/contact', 'yearly', '0.6']];
if (db_ready()) {
    foreach (db()->query('SELECT slug, updated_at FROM ' . t('projects') . ' WHERE published = 1 ORDER BY sort_order, id') as $p) {
        $urls[] = ['/work/' . rawurlencode($p['slug']), 'monthly', '0.8', $p['updated_at'] ? substr((string) $p['updated_at'], 0, 10) : ''];
    }
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($base . $u[0], ENT_XML1) . '</loc>' . (!empty($u[3]) ? '<lastmod>' . $u[3] . '</lastmod>' : '')
        . '<changefreq>' . $u[1] . '</changefreq><priority>' . $u[2] . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
