<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$open = true;   // live site: always open to search engines
echo $open ? "User-agent: *\nDisallow: /admin/\nDisallow: /api/\n\nSitemap: " . site_base() . "/sitemap.xml\n" : "User-agent: *\nDisallow: /\n";
