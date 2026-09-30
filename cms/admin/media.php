<?php
require __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $st = db()->prepare('SELECT * FROM ' . t('media') . ' WHERE id = ?');
        $st->execute([(int) $_POST['delete']]);
        if ($m = $st->fetch()) {
            $real = realpath(CS_ROOT . $m['path']);
            if ($real && str_starts_with($real, realpath(CS_ROOT . '/uploads'))) {
                @unlink($real);
            }
            db()->prepare('DELETE FROM ' . t('media') . ' WHERE id = ?')->execute([$m['id']]);
            content_cache_clear();
            flash('Fișier șters. Dacă era folosit undeva pe site, alege altă imagine acolo.');
        }
    } elseif (!empty($_FILES['files'])) {
        $n = 0;
        $errs = [];
        foreach ($_FILES['files']['name'] as $i => $name) {
            $f = ['name' => $name, 'type' => $_FILES['files']['type'][$i], 'tmp_name' => $_FILES['files']['tmp_name'][$i], 'error' => $_FILES['files']['error'][$i], 'size' => $_FILES['files']['size'][$i]];
            try {
                $mime = is_file($f['tmp_name']) ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
                str_starts_with($mime, 'video/') ? store_video($f) : store_image($f, $mime === 'image/svg+xml' ? 'logo' : 'image');
                $n++;
            } catch (Throwable $e) {
                $errs[] = $name . ': ' . $e->getMessage();
            }
        }
        flash($n . ' fișier(e) încărcate.' . ($errs ? ' Erori: ' . implode(' · ', $errs) : ''), $errs ? 'bad' : 'ok');
    }
    redirect('media.php');
}

$rows = db()->query('SELECT * FROM ' . t('media') . ' ORDER BY id DESC')->fetchAll();
admin_head('Media', 'media');
?>
<form method="post" enctype="multipart/form-data" class="card" style="padding:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap"><?= csrf_field() ?>
  <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/svg+xml,video/mp4">
  <button class="btn" type="submit">Încarcă</button>
  <span class="help">Pozele se redimensionează automat (max 2400 px) și se salvează ca WebP. Filme: MP4 H.264; limita serverului este <?= e(ini_get('upload_max_filesize')) ?>.</span>
</form>
<table class="list"><thead><tr><th></th><th>Fișier</th><th class="hide-m">Dimensiuni</th><th class="hide-m">Încărcat</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $m): ?>
<tr><td><?php if ($m['kind'] === 'video'): ?><span class="status">video</span><?php else: ?><img class="thumb <?= $m['kind'] === 'logo' ? 'logo' : '' ?>" src="<?= e($m['path']) ?>" alt="" loading="lazy"><?php endif ?></td>
<td><?= e($m['original'] ?: basename($m['path'])) ?><br><span class="muted" style="font-size:.8rem"><?= e($m['path']) ?></span></td>
<td class="hide-m"><?= $m['w'] ? (int) $m['w'] . ' × ' . (int) $m['h'] : '—' ?></td><td class="hide-m muted"><?= e(substr((string) $m['created_at'], 0, 16)) ?></td>
<td><form method="post"><?= csrf_field() ?><button class="btn small danger" name="delete" value="<?= (int) $m['id'] ?>" data-confirm="Ștergi definitiv fișierul?">Șterge</button></form></td></tr>
<?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Nu ai încărcat încă nimic. Imaginile livrate cu site-ul (din Drive) sunt disponibile oricum în selectorul de imagini.</td></tr><?php endif ?>
</tbody></table>
<?php admin_foot();
