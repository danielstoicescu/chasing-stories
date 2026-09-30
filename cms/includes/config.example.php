<?php
/**
 * Chasing Stories · configuration
 *
 * COPY this file to config.php and fill in the real values.
 * config.php is never part of an update package and is never overwritten.
 */

// --- Database -------------------------------------------------------------
// On the server: MySQL / MariaDB (the same database the old WordPress used is fine:
// every table here starts with DB_PREFIX, so nothing collides with wp_ tables).
define('DB_DRIVER', 'mysql');          // 'mysql' on the server, 'sqlite' for local testing
define('DB_HOST',   'localhost');
define('DB_NAME',   'CHANGE_ME');
define('DB_USER',   'CHANGE_ME');
define('DB_PASS',   'CHANGE_ME');
define('DB_PREFIX', 'cs_');
define('DB_PATH',   __DIR__ . '/../cache/local.sqlite');   // only used when DB_DRIVER is 'sqlite'

// --- Security -------------------------------------------------------------
// Any long random string (40+ characters). Signs sessions and encrypts the SMTP password.
define('APP_SECRET', 'CHANGE_ME_to_a_long_random_string');

// --- Environment ----------------------------------------------------------
define('APP_ENV', 'production');       // 'production' hides errors, 'dev' shows them
date_default_timezone_set('Europe/Bucharest');
