<?php
// Serves the static Chasing Stories page at / (the server's directory index is index.php only).
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
readfile(__DIR__ . '/index.html');
