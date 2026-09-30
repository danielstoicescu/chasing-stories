<?php
/**
 * Shared helpers: escaping, CSRF, settings, rate limiting, media.
 */

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------- sessions + CSRF ---------------- */

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('cs_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    session_boot();
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.');
    }
}

function flash(?string $msg = null, string $kind = 'ok'): ?array
{
    session_boot();
    if ($msg !== null) {
        $_SESSION['flash'] = [$kind, $msg];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ---------------- settings (flat dotted keys, e.g. "home.h1") ---------------- */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db()->query('SELECT k, v FROM ' . t('settings')) as $r) {
            $cache[$r['k']] = (string) $r['v'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setting_json(string $key, $default = [])
{
    $v = setting($key, '');
    if ($v === '') {
        return $default;
    }
    $d = json_decode($v, true);
    return $d === null ? $default : $d;
}

function setting_save(array $pairs): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM ' . t('settings') . ' WHERE k = ?');
    $ins = $pdo->prepare('INSERT INTO ' . t('settings') . ' (k, v) VALUES (?, ?)');
    foreach ($pairs as $k => $v) {
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $del->execute([$k]);
        $ins->execute([$k, (string) $v]);
    }
    $pdo->commit();
    settings_all(true);
    content_cache_clear();
}

/* ---------------- request helpers ---------------- */

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** At most $max hits for $action from one IP inside $window seconds. */
function rate_ok(string $action, int $max, int $window): bool
{
    $pdo = db();
    $ip = client_ip();
    $now = time();
    $pdo->prepare('DELETE FROM ' . t('rate') . ' WHERE ts < ?')->execute([$now - 86400]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM ' . t('rate') . ' WHERE ip = ? AND action = ? AND ts > ?');
    $q->execute([$ip, $action, $now - $window]);
    if ((int) $q->fetchColumn() >= $max) {
        return false;
    }
    $pdo->prepare('INSERT INTO ' . t('rate') . ' (ip, action, ts) VALUES (?, ?, ?)')->execute([$ip, $action, $now]);
    return true;
}

function json_out(int $code, array $data): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function slugify(string $text): string
{
    $text = strtr($text, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't', '&' => ' and ']);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t !== false) {
            $text = $t;
        }
    }
    $text = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
    return trim($text, '-') ?: 'item';
}

function site_base(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/* ---------------- media ----------------
   A "ref" is either a key of a shipped asset (e.g. "palau-1" -> /assets/palau-1.webp)
   or an upload path ("/uploads/2026/10/name.webp"). */

function asset_manifest(): array
{
    static $m = null;
    if ($m === null) {
        $f = CS_ROOT . '/assets/manifest.json';
        $m = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }
    return $m;
}

function ref_url(string $ref): string
{
    if ($ref === '') {
        return '';
    }
    if ($ref[0] === '/' || preg_match('#^https?://#', $ref)) {
        return $ref;
    }
    return isset(asset_manifest()[$ref]) ? '/assets/' . $ref . '.webp' : $ref;
}

function logo_url(string $ref): string
{
    if ($ref === '') {
        return '';
    }
    return ($ref[0] === '/' || preg_match('#^https?://#', $ref)) ? $ref : '/assets/logo/' . $ref;
}

/** mb_substr for servers without mbstring (only this function is used). */
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $enc = null): string
    {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($chars, $start, $length));
    }
}

/** MIME type of an uploaded file: fileinfo when the server has it, otherwise the file's magic bytes. */
function file_mime(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    if (class_exists('finfo')) {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    }
    $h = (string) file_get_contents($path, false, null, 0, 512);
    if (strncmp($h, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
    if (strncmp($h, "\x89PNG", 4) === 0) return 'image/png';
    if (strncmp($h, 'GIF8', 4) === 0) return 'image/gif';
    if (strncmp($h, 'RIFF', 4) === 0 && substr($h, 8, 4) === 'WEBP') return 'image/webp';
    if (strncmp($h, "\x1A\x45\xDF\xA3", 4) === 0) return 'video/webm';
    if (substr($h, 4, 4) === 'ftyp') return substr($h, 8, 2) === 'qt' ? 'video/quicktime' : 'video/mp4';
    if (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE svg[^>]*>\s*)?<svg[\s>]/is', $h)) return 'image/svg+xml';
    return 'application/octet-stream';
}

/** Stores an uploaded image: EXIF-rotated, max 2400px on the long edge, saved as WebP when the server can. */
function store_image(array $file, string $kind = 'image'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text((int) ($file['error'] ?? 4)));
    }
    $mime = file_mime($file['tmp_name']);
    $dir = '/uploads/' . date('Y/m');
    @mkdir(CS_ROOT . $dir, 0755, true);
    $base = slugify(pathinfo((string) $file['name'], PATHINFO_FILENAME));
    $base = substr($base, 0, 60) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

    if ($mime === 'image/svg+xml' || ($mime === 'text/plain' && preg_match('/\.svg$/i', (string) $file['name']))) {
        if ($kind !== 'logo') {
            throw new RuntimeException('SVG este acceptat doar pentru logo-uri.');
        }
        $svg = (string) file_get_contents($file['tmp_name']);
        // strip scripts, event handlers and external references
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $svg);
        $svg = preg_replace('#(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*\2#i', '', $svg);
        $path = $dir . '/' . $base . '.svg';
        file_put_contents(CS_ROOT . $path, $svg);
        return media_record($path, 0, 0, 'logo', (string) $file['name']);
    }

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Format neacceptat (' . $mime . '). Folosește JPG, PNG sau WebP.');
    }
    if (!function_exists('imagecreatefromjpeg')) {
        // no GD on the server: keep the file as it is
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $path = $dir . '/' . $base . '.' . $ext;
        move_uploaded_file($file['tmp_name'], CS_ROOT . $path);
        [$w, $h] = @getimagesize(CS_ROOT . $path) ?: [0, 0];
        return media_record($path, (int) $w, (int) $h, $kind, (string) $file['name']);
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) {
        throw new RuntimeException('Imaginea nu a putut fi citită.');
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $max = $kind === 'logo' ? 1200 : 2400;
    $scale = min(1, $max / max($w, $h));
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if (function_exists('imagewebp')) {
        $path = $dir . '/' . $base . '.webp';
        imagewebp($dst, CS_ROOT . $path, 82);
    } else {
        $path = $dir . '/' . $base . ($mime === 'image/png' ? '.png' : '.jpg');
        $mime === 'image/png' ? imagepng($dst, CS_ROOT . $path, 7) : imagejpeg($dst, CS_ROOT . $path, 86);
    }
    imagedestroy($src);
    imagedestroy($dst);
    return media_record($path, $nw, $nh, $kind, (string) $file['name']);
}

function store_video(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text((int) ($file['error'] ?? 4)));
    }
    $mime = file_mime($file['tmp_name']);
    if (!in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime'], true)) {
        throw new RuntimeException('Video neacceptat (' . $mime . '). Folosește MP4 (H.264).');
    }
    $dir = '/uploads/video/' . date('Y/m');
    @mkdir(CS_ROOT . $dir, 0755, true);
    $ext = $mime === 'video/webm' ? 'webm' : 'mp4';
    $path = $dir . '/' . substr(slugify(pathinfo((string) $file['name'], PATHINFO_FILENAME)), 0, 60) . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], CS_ROOT . $path);
    return media_record($path, 0, 0, 'video', (string) $file['name']);
}

function media_record(string $path, int $w, int $h, string $kind, string $original): array
{
    db()->prepare('INSERT INTO ' . t('media') . ' (path, w, h, kind, original, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$path, $w, $h, $kind, substr($original, 0, 190), date('Y-m-d H:i:s')]);
    return ['ref' => $path, 'url' => $path, 'w' => $w, 'h' => $h, 'kind' => $kind];
}

function upload_error_text(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fișierul e mai mare decât permite serverul (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_PARTIAL => 'Fișierul s-a încărcat doar parțial. Încearcă din nou.',
        UPLOAD_ERR_NO_FILE => 'Niciun fișier ales.',
        default => 'Încărcarea a eșuat (cod ' . $code . ').',
    };
}

/* ---------------- content cache ---------------- */

function content_cache_clear(): void
{
    @unlink(CS_ROOT . '/cache/site.json');
}
