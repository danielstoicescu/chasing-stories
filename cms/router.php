<?php
/* Local testing only (php -S): behaves like .htaccess. Not used on the server. */
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p === '/robots.txt') { require __DIR__ . '/robots.php'; return true; }
if (preg_match('#^/(includes|cache)(/|$)#', $p)) { http_response_code(403); return true; }
$f = __DIR__ . $p;
if ($p !== '/' && is_file($f)) { return false; }
if (is_dir($f) && is_file(rtrim($f, '/') . '/index.php')) { require rtrim($f, '/') . '/index.php'; return true; }
require __DIR__ . '/index.php';
return true;
