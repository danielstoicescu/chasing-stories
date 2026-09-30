<?php
require __DIR__ . '/_boot.php';
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM ' . t('enquiries') . ' WHERE id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    redirect('index.php');
}
$statuses = ['new' => 'Nou', 'read' => 'Citit', 'replied' => 'Răspuns', 'archived' => 'Arhivat'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        db()->prepare('DELETE FROM ' . t('enquiries') . ' WHERE id = ?')->execute([$id]);
        flash('Mesaj șters.');
        redirect('index.php');
    }
    $s = isset($statuses[$_POST['status'] ?? '']) ? $_POST['status'] : $r['status'];
    db()->prepare('UPDATE ' . t('enquiries') . ' SET status = ?, notes = ? WHERE id = ?')->execute([$s, trim((string) ($_POST['notes'] ?? '')), $id]);
    flash('Salvat.');
    redirect('enquiry.php?id=' . $id);
}
if ($r['status'] === 'new') {
    db()->prepare('UPDATE ' . t('enquiries') . " SET status = 'read' WHERE id = ?")->execute([$id]);
    $r['status'] = 'read';
}
admin_head($r['company'] ?: $r['name'], 'index');
$subject = rawurlencode('Re: your enquiry' . ($r['location'] ? ' (' . $r['location'] . ')' : ''));
?>
<p><a href="index.php">← Toate mesajele</a></p>
<section class="card enq"><header><h2><?= e(date('d.m.Y H:i', strtotime((string) $r['created_at']))) ?></h2></header>
<dl>
<?php foreach (['Nume' => 'name', 'Companie / brand' => 'company', 'Email' => 'email', 'Website / Instagram' => 'website', 'Locația proiectului' => 'location',
    'Date preferate' => 'dates', 'Servicii' => 'type', 'A venit din' => 'source', 'Pagina' => 'page'] as $label => $k):
    if ((string) $r[$k] === '') continue; ?>
  <dt><?= e($label) ?></dt><dd><?= $k === 'email' ? '<a href="mailto:' . e($r[$k]) . '?subject=' . $subject . '">' . e($r[$k]) . '</a>' : e($r[$k]) ?></dd>
<?php endforeach ?>
  <dt>Email trimis</dt><dd class="muted"><?= e($r['mail_status'] ?: '—') ?></dd>
</dl>
<div class="msg"><?= e($r['details']) ?></div>
</section>
<form method="post" class="edit"><?= csrf_field() ?>
<?= card_open('Urmărire') ?>
<?= f_select('status', 'Status', (string) $r['status'], $statuses) ?>
<div class="fld"><span class="lb">Răspunde</span><a class="btn ghost small" href="mailto:<?= e($r['email']) ?>?subject=<?= $subject ?>">Scrie un email ↗</a></div>
<?= f_area('notes', 'Notițe interne', (string) $r['notes'], 'Vizibile doar în panou.') ?>
<?= card_close() ?>
<?= save_bar('Salvează', '<button class="btn danger" name="delete" value="1" data-confirm="Ștergi definitiv mesajul?">Șterge mesajul</button>') ?>
</form>
<?php admin_foot();
