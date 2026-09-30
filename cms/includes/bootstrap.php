<?php
/**
 * Loaded first by every entry point.
 */
if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(503);
    exit('Not configured yet: copy includes/config.example.php to includes/config.php.');
}
require_once __DIR__ . '/config.php';
if (APP_ENV === 'dev') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
define('CS_ROOT', dirname(__DIR__));
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
