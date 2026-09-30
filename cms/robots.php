<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$open = db_ready() && setting('seo.index', '0') === '1';
echo $open ? "User-agent: *\nDisallow: /admin/\nDisallow: /api/\n" : "User-agent: *\nDisallow: /\n";
