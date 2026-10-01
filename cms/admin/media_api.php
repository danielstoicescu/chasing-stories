<?php
/* JSON for the media picker: list the library, or take an upload. */
require __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $kind = in_array($_POST['kind'] ?? '', ['image', 'logo', 'video'], true) ? $_POST['kind'] : 'image';
        $f = $_FILES['file'] ?? [];
        $mime = file_mime((string) ($f['tmp_name'] ?? ''));
        $rec = str_starts_with($mime, 'video/') ? store_video($f) : store_image($f, $kind);
        content_cache_clear();
        json_out(200, ['item' => ['ref' => $rec['ref'], 'url' => $rec['url'], 'thumb' => $rec['url'], 'name' => basename($rec['ref']), 'kind' => $rec['kind']]]);
    } catch (Throwable $e) {
        json_out(400, ['error' => $e->getMessage()]);
    }
}

$items = [];
foreach (db()->query('SELECT * FROM ' . t('media') . ' ORDER BY id DESC') as $m) {
    $items[] = ['ref' => $m['path'], 'url' => $m['path'], 'thumb' => $m['path'], 'name' => $m['original'] ?: basename($m['path']), 'kind' => $m['kind']];
}
foreach (array_keys(asset_manifest()) as $k) {
    $thumb = is_file(CS_ROOT . '/assets/thumb/' . $k . '.webp') ? '/assets/thumb/' . $k . '.webp' : '/assets/' . $k . '.webp';
    $items[] = ['ref' => $k, 'url' => '/assets/' . $k . '.webp', 'thumb' => $thumb, 'name' => $k, 'kind' => 'image'];
}
foreach (glob(CS_ROOT . '/assets/logo/*') ?: [] as $f) {
    $b = basename($f);
    $items[] = ['ref' => $b, 'url' => '/assets/logo/' . $b, 'thumb' => '/assets/logo/' . $b, 'name' => $b, 'kind' => 'logo'];
}
foreach (glob(CS_ROOT . '/assets/video/*.mp4') ?: [] as $f) {
    $b = basename($f);
    $items[] = ['ref' => '/assets/video/' . $b, 'url' => '/assets/video/' . $b, 'thumb' => '', 'name' => $b, 'kind' => 'video'];
}
json_out(200, ['items' => $items, 'max' => upload_limit()]);
