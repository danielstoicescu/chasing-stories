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
        if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            json_out(413, ['error' => 'Fișierul e mai mare decât permite serverul (' . ini_get('post_max_size') . ').']);
        }
        csrf_check();
    }
}
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
