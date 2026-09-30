<?php
require __DIR__ . '/_boot.php';
$me = db()->prepare('SELECT * FROM ' . t('admins') . ' WHERE id = ?');
$me->execute([auth_id()]);
$me = $me->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['display_name'] ?? ''));
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    db()->prepare('UPDATE ' . t('admins') . ' SET display_name = ? WHERE id = ?')->execute([$name, auth_id()]);
    $_SESSION['admin_name'] = $name ?: $me['username'];
    if ($new !== '') {
        if (!password_verify($cur, $me['password_hash'])) {
            flash('Parola actuală nu e corectă.', 'bad');
            redirect('account.php');
        }
        if (strlen($new) < 10) {
            flash('Parola nouă trebuie să aibă cel puțin 10 caractere.', 'bad');
            redirect('account.php');
        }
        db()->prepare('UPDATE ' . t('admins') . ' SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), auth_id()]);
    }
    flash('Salvat.');
    redirect('account.php');
}
admin_head('Contul meu', '');
?>
<form method="post" class="edit"><?= csrf_field() ?>
<?= card_open('Profil') ?>
<?= f_text('display_name', 'Nume afișat', (string) $me['display_name']) ?>
<div class="fld"><span class="lb">Utilizator</span><div><?= e($me['username']) ?> · <?= e($me['role']) ?></div></div>
<?= card_close() ?>
<?= card_open('Schimbă parola', 'Lasă gol dacă nu o schimbi. Minimum 10 caractere.') ?>
<?= f_text('current', 'Parola actuală', '', '', ['type' => 'password']) ?>
<?= f_text('new', 'Parola nouă', '', '', ['type' => 'password']) ?>
<?= card_close() ?>
<?= save_bar() ?>
</form>
<?php admin_foot();
