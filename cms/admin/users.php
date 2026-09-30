<?php
require __DIR__ . '/_boot.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $uid = (int) $_POST['delete'];
        if ($uid === auth_id()) {
            flash('Nu îți poți șterge propriul cont.', 'bad');
        } else {
            db()->prepare('DELETE FROM ' . t('admins') . ' WHERE id = ?')->execute([$uid]);
            flash('Cont șters.');
        }
    } elseif (isset($_POST['add'])) {
        $u = strtolower(trim((string) $_POST['username']));
        $p = (string) $_POST['password'];
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $u)) {
            flash('Utilizatorul: 3–40 caractere, litere mici, cifre, punct, cratimă.', 'bad');
        } elseif (strlen($p) < 10) {
            flash('Parola trebuie să aibă cel puțin 10 caractere.', 'bad');
        } else {
            try {
                db()->prepare('INSERT INTO ' . t('admins') . ' (username, password_hash, display_name, role, created_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$u, password_hash($p, PASSWORD_DEFAULT), trim((string) $_POST['display_name']), $_POST['role'] === 'admin' ? 'admin' : 'editor', date('Y-m-d H:i:s')]);
                flash('Cont creat. Trimite-i utilizatorului parola pe un canal sigur.');
            } catch (Throwable $e) {
                flash('Utilizatorul există deja.', 'bad');
            }
        }
    }
    redirect('users.php');
}
$rows = db()->query('SELECT * FROM ' . t('admins') . ' ORDER BY id')->fetchAll();
admin_head('Utilizatori', 'users');
?>
<table class="list"><thead><tr><th>Utilizator</th><th>Rol</th><th class="hide-m">Ultima intrare</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?>
<tr><td><strong><?= e($u['display_name'] ?: $u['username']) ?></strong><br><span class="muted"><?= e($u['username']) ?></span></td>
<td><?= $u['role'] === 'admin' ? 'Administrator' : 'Editor' ?></td><td class="hide-m muted"><?= e($u['last_login'] ?: '—') ?></td>
<td><?php if ((int) $u['id'] !== auth_id()): ?><form method="post"><?= csrf_field() ?><button class="btn small danger" name="delete" value="<?= (int) $u['id'] ?>" data-confirm="Ștergi contul?">Șterge</button></form><?php else: ?><span class="muted">tu</span><?php endif ?></td></tr>
<?php endforeach ?>
</tbody></table>
<form method="post" class="edit" style="margin-top:22px"><?= csrf_field() ?>
<?= card_open('Cont nou', 'Editor: conținut și mesaje. Administrator: tot, inclusiv setările de email și conturile.') ?>
<?= f_text('username', 'Utilizator', '') ?><?= f_text('display_name', 'Nume afișat', '') ?>
<?= f_text('password', 'Parolă (min. 10 caractere)', '', '', ['type' => 'password']) ?>
<?= f_select('role', 'Rol', 'editor', ['editor' => 'Editor', 'admin' => 'Administrator']) ?>
<?= card_close() ?>
<?= save_bar('Creează contul') ?><input type="hidden" name="add" value="1">
</form>
<?php admin_foot();
