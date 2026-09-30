<?php
define('CS_PUBLIC_ADMIN', true);
require __DIR__ . '/_boot.php';
session_boot();
if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!rate_ok('login', 8, 900)) {
        $err = 'Prea multe încercări. Așteaptă 15 minute.';
    } elseif (auth_attempt(trim((string) ($_POST['u'] ?? '')), (string) ($_POST['p'] ?? ''))) {
        redirect('index.php');
    } else {
        $err = 'Utilizator sau parolă greșite.';
    }
}
?><!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Intră · Chasing Stories</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,400..600&family=Geist:wght@400..600&family=Geist+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="../assets/admin.css"></head><body>
<div class="login"><form method="post" autocomplete="on">
  <h1>Chasing Stories</h1><p class="muted" style="margin:-8px 0 4px">Panoul de conținut</p>
  <?php if ($err): ?><div class="flash bad"><?= e($err) ?></div><?php endif ?>
  <?= csrf_field() ?>
  <label class="fld"><span class="lb">Utilizator</span><input type="text" name="u" autocomplete="username" required autofocus></label>
  <label class="fld"><span class="lb">Parolă</span><input type="password" name="p" autocomplete="current-password" required></label>
  <button class="btn" type="submit">Intră</button>
</form></div></body></html>
