<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/../includes/mailer.php';
$keys = ['contact.title', 'contact.intro', 'contact.success', 'contact.email', 'contact.instagram', 'contact.linkedin', 'img.contact', 'contact.imgAlt'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test'])) {
        $to = setting('mail.to', 'begin@chasingstories.org');
        [$ok, $msg] = send_mail(array_map('trim', explode(',', $to)), 'Test email from the website',
            mail_shell('Test', 'Emailurile ajung aici.', '<p>Acesta e un mesaj de test trimis din panoul Chasing Stories. Mesajele din formularul de contact vor veni la fel.</p>'));
        flash($ok ? 'Email de test trimis la ' . $to . ' (' . $msg . '). Verifică și Spam.' : 'Nu s-a putut trimite: ' . $msg, $ok ? 'ok' : 'bad');
        redirect('contact.php');
    }
    save_copy_from_post($keys);
    $pairs = [];
    $to = array_filter(array_map('trim', explode(',', (string) ($_POST['mail_to'] ?? ''))));
    $bad = array_filter($to, fn($x) => !filter_var($x, FILTER_VALIDATE_EMAIL));
    if ($bad) {
        flash('Adresă de email invalidă: ' . implode(', ', $bad), 'bad');
        redirect('contact.php');
    }
    $pairs['mail.to'] = implode(', ', $to);
    $pairs['mail.autoreply'] = ($_POST['mail_autoreply'] ?? '0') === '1' ? '1' : '0';
    $pairs['mail.autoreply_subject'] = trim((string) ($_POST['mail_autoreply_subject'] ?? ''));
    $pairs['mail.autoreply_text'] = trim((string) ($_POST['mail_autoreply_text'] ?? ''));
    if (is_admin()) {
        $pairs['mail.from_name'] = trim((string) ($_POST['from_name'] ?? ''));
        $pairs['mail.from_email'] = trim((string) ($_POST['from_email'] ?? ''));
        $pairs['smtp.host'] = trim((string) ($_POST['smtp_host'] ?? ''));
        $pairs['smtp.port'] = (string) (int) ($_POST['smtp_port'] ?? 587);
        $pairs['smtp.secure'] = in_array($_POST['smtp_secure'] ?? '', ['', 'ssl', 'tls'], true) ? $_POST['smtp_secure'] : 'tls';
        $pairs['smtp.user'] = trim((string) ($_POST['smtp_user'] ?? ''));
        if (($_POST['smtp_pass'] ?? '') !== '') {
            $pairs['smtp.pass'] = secret_encrypt((string) $_POST['smtp_pass']);
        }
    }
    setting_save($pairs);
    flash('Salvat.');
    redirect('contact.php');
}
admin_head('Contact și email', 'contact');
?>
<form method="post" class="edit"><?= csrf_field() ?>
<?= card_open('Unde ajung mesajele', 'Fiecare mesaj din formular se salvează în panou (Mesaje) și pleacă pe email la adresele de aici.') ?>
<?= f_text('mail_to', 'Trimite mesajele la', setting('mail.to', 'begin@chasingstories.org'), 'Mai multe adrese se separă prin virgulă.', ['wide' => true]) ?>
<?= f_check('mail_autoreply', 'Trimite un răspuns automat celui care scrie', setting('mail.autoreply', '1') === '1') ?>
<?= f_text('mail_autoreply_subject', 'Subiect răspuns automat', setting('mail.autoreply_subject', 'Thank you for your enquiry'), '', ['wide' => true]) ?>
<?= f_area('mail_autoreply_text', 'Text răspuns automat', setting('mail.autoreply_text', "Thank you for contacting Chasing Stories. We have received your enquiry and will reply within two working days.\n\nIn the meantime, you can explore our recent work at " . site_base() . "/work."), '', 5) ?>
<?= card_close() ?>

<?= card_open('Pagina Contact') ?>
<?= cf('contact.title', 'Titlu (H1)') ?>
<?= cimg('img.contact', 'Imagine laterală (4:5)') ?>
<?= cf('contact.intro', 'Intro', 'Include timpul de răspuns.', 'area', ['rows' => 3]) ?>
<?= cf('contact.success', 'Mesaj după trimitere', '', 'area', ['rows' => 2]) ?>
<p class="muted" style="margin:0">Opțiunile de la „What do you need?” (selecție multiplă) sunt serviciile din <a href="services.php">Cele șase servicii</a>, cu pozele lor.</p>
<?= cf('contact.imgAlt', 'Descrierea imaginii (alt)') ?>
<?= card_close() ?>

<?= card_open('Date de contact publice', 'Apar pe pagina Contact, în footer și în meniul de mobil. Lasă gol ce nu vrei afișat.') ?>
<?= cf('contact.email', 'Email public', '', 'text', ['type' => 'email']) ?>
<?= cf('contact.instagram', 'Instagram (link complet)') ?>
<?= cf('contact.linkedin', 'LinkedIn (link complet)') ?>
<?= card_close() ?>

<?php if (is_admin()): ?>
<?= card_open('Trimiterea emailurilor (SMTP)', 'Recomandat: căsuța de email a domeniului. Fără SMTP, serverul trimite prin mail(), dar emailurile ajung mai des în Spam.') ?>
<?= f_text('from_name', 'Nume expeditor', setting('mail.from_name', 'Chasing Stories')) ?>
<?= f_text('from_email', 'Email expeditor', setting('mail.from_email', ''), 'De preferat o adresă pe domeniul site-ului, ex. website@chasingstories.org') ?>
<?= f_text('smtp_host', 'Server SMTP', setting('smtp.host'), 'Ex. mail.chasingstories.org') ?>
<?= f_text('smtp_port', 'Port', setting('smtp.port', '587'), '587 cu TLS sau 465 cu SSL') ?>
<?= f_select('smtp_secure', 'Securitate', setting('smtp.secure', 'tls'), ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', '' => 'Fără']) ?>
<?= f_text('smtp_user', 'Utilizator', setting('smtp.user')) ?>
<?= f_text('smtp_pass', 'Parolă', '', setting('smtp.pass') ? 'Salvată criptat. Lasă gol ca să rămână aceeași.' : 'Se salvează criptat.', ['type' => 'password']) ?>
<?= card_close() ?>
<?php endif ?>
<?= save_bar('Salvează', '<button class="btn ghost" name="test" value="1" formnovalidate>Trimite un email de test</button>') ?>
</form>
<?php admin_foot();
