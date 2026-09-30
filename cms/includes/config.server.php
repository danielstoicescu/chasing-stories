<?php
/**
 * Chasing Stories · server configuration that needs no typing.
 *
 * Rename this file to config.php. On the first request it reads the database credentials
 * from the old WordPress wp-config.php (so nobody retypes or sends them around), generates
 * APP_SECRET, and stores both in includes/secrets.php. After that the WordPress folder can go.
 */

$secrets = __DIR__ . '/secrets.php';
if (!is_file($secrets)) {
    $wp = '';
    foreach ([dirname(__DIR__) . '/_wordpress-vechi/wp-config.php', dirname(__DIR__) . '/wp-config.php', dirname(__DIR__, 2) . '/wp-config.php', dirname(__DIR__, 2) . '/_wordpress-vechi/wp-config.php'] as $f) {
        if (is_file($f)) {
            $wp = (string) file_get_contents($f);
            break;
        }
    }
    $get = function (string $k) use ($wp): string {
        return preg_match('/define\(\s*[\'"]' . $k . '[\'"]\s*,\s*([\'"])(.*?)\1\s*\)/s', $wp, $m) ? stripcslashes($m[2]) : '';
    };
    if ($wp === '' || $get('DB_NAME') === '') {
        http_response_code(503);
        exit('Setup: no wp-config.php found to read the database from. Put it in _wordpress-vechi/ or fill includes/config.php by hand (see config.example.php).');
    }
    $vals = ['DB_HOST' => $get('DB_HOST') ?: 'localhost', 'DB_NAME' => $get('DB_NAME'), 'DB_USER' => $get('DB_USER'),
        'DB_PASS' => $get('DB_PASSWORD'), 'APP_SECRET' => bin2hex(random_bytes(32))];
    $php = "<?php\n// Generated on " . date('Y-m-d H:i') . " from the old wp-config.php. Keep private; never part of an update.\n";
    foreach ($vals as $k => $v) {
        $php .= 'define(' . var_export($k, true) . ', ' . var_export($v, true) . ");\n";
    }
    if (@file_put_contents($secrets, $php, LOCK_EX) === false) {
        http_response_code(503);
        exit('Setup: cannot write includes/secrets.php. Give the includes folder write permission for a moment.');
    }
    @chmod($secrets, 0600);
}
require $secrets;

define('DB_DRIVER', 'mysql');
define('DB_PREFIX', 'cs_');
define('DB_PATH', '');
define('APP_ENV', 'production');
date_default_timezone_set('Europe/Bucharest');
