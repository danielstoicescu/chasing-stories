<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
if (!db_ready()) {
    exit('Baza de date nu e instalată încă. Deschide /_install/install.php.');
}
if (!defined('CS_PUBLIC_ADMIN')) {
    auth_check();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
    }
}
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
