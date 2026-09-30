<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_sort.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['move'])) {
        [$id, $dir] = explode(':', (string) $_POST['move']);
        sort_move('projects', (int) $id, $dir);
    } elseif (isset($_POST['toggle'])) {
        db()->prepare('UPDATE ' . t('projects') . ' SET published = 1 - published WHERE id = ?')->execute([(int) $_POST['toggle']]);
        content_cache_clear();
    } elseif (isset($_POST['delete'])) {
        db()->prepare('DELETE FROM ' . t('projects') . ' WHERE id = ?')->execute([(int) $_POST['delete']]);
        content_cache_clear();
        flash('Proiect șters.');
    }
    redirect('work.php');
}
$rows = db()->query('SELECT * FROM ' . t('projects') . ' ORDER BY sort_order, id')->fetchAll();
admin_head('Work', 'work');
?>
<div class="toolbar"><span class="muted"><?= count($rows) ?> proiecte · ordinea de aici e ordinea de pe site</span><span class="sp"></span><a class="btn" href="project.php">+ Proiect nou</a></div>
<form method="post"><?= csrf_field() ?>
<table class="list"><thead><tr><th></th><th>Proiect</th><th class="hide-m">Client</th><th>Pe site</th><th>Ordine</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $i => $p): ?>
<tr>
  <td><img class="thumb" src="<?= e(ref_url($p['cover_v'] ?: $p['hero'])) ?>" alt="" loading="lazy"></td>
  <td><a href="project.php?id=<?= (int) $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a><br><span class="muted"><?= e(trim($p['location'] . ' · ' . $p['year'], ' ·')) ?></span></td>
  <td class="hide-m"><?= e($p['client']) ?></td>
  <td><button class="btn small <?= $p['published'] ? '' : 'ghost' ?>" name="toggle" value="<?= (int) $p['id'] ?>" title="Schimbă"><?= $p['published'] ? 'Publicat' : 'Ascuns' ?></button></td>
  <td class="row-actions"><button class="btn small ghost" name="move" value="<?= (int) $p['id'] ?>:up" <?= $i ? '' : 'disabled' ?>>↑</button><button class="btn small ghost" name="move" value="<?= (int) $p['id'] ?>:down" <?= $i < count($rows) - 1 ? '' : 'disabled' ?>>↓</button></td>
  <td class="row-actions"><a class="btn small" href="project.php?id=<?= (int) $p['id'] ?>">Editează</a><a class="btn small ghost" href="/work/<?= e($p['slug']) ?>" target="_blank" rel="noopener">Vezi ↗</a></td>
</tr>
<?php endforeach ?>
</tbody></table></form>
<?php admin_foot();
