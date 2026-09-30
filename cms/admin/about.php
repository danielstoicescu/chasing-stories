<?php
require __DIR__ . '/_boot.php';
$keys = ['about.title', 'about.intro', 'img.aboutOpen', 'about.openAlt', 'about.approachTitle', 'about.approach', 'about.productionTitle', 'about.production',
    'about.stepsTitle', 'about.worldTitle', 'about.worldText', 'about.worldCta', 'img.world'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    save_copy_from_post($keys);
    $disc = array_slice(array_map(fn($d) => ['label' => (string) $d['label'], 'meta' => (string) $d['meta'], 'img' => (string) $d['img'],
        'project' => (string) $d['project'], 'film' => (string) $d['film']], rep_post('disc')), 0, 3);
    $steps = array_map(fn($s) => ['t' => (string) $s['t'], 'd' => (string) $s['d'], 'img' => (string) $s['img']], array_filter(rep_post('steps'), fn($s) => trim((string) $s['t']) !== ''));
    setting_save(['copy.about.disc' => $disc, 'copy.about.steps' => array_values($steps)]);
    flash('Pagina About a fost salvată.');
    redirect('about.php');
}
$disc = setting_json('copy.about.disc', []);
$steps = setting_json('copy.about.steps', []);
admin_head('About', 'about');
?>
<form method="post" class="edit"><?= csrf_field() ?>
<?= card_open('Deschidere') ?>
<?= cf('about.title', 'Titlu (H1)') ?>
<?= cimg('img.aboutOpen', 'Imagine (verticală, fără portrete)') ?>
<?= cf('about.intro', 'Text', 'Lasă un rând liber între paragrafe.', 'area', ['rows' => 5]) ?>
<?= cf('about.openAlt', 'Descrierea imaginii (alt)') ?>
<?= card_close() ?>
<?= card_open('Our approach și Creative production') ?>
<?= cf('about.approachTitle', 'Titlu secțiune 1') ?><?= cf('about.productionTitle', 'Titlu secțiune 2') ?>
<?= cf('about.approach', 'Text secțiune 1', '', 'area', ['rows' => 5]) ?>
<?= cf('about.production', 'Text secțiune 2', '', 'area', ['rows' => 5]) ?>
<?= card_close() ?>
<section class="card"><header><h2>Cele trei discipline</h2><p>Trei carduri de mărimi diferite: primul mare vertical, al doilea orizontal, al treilea pătrat. Cu proiect, cardul duce în proiect; cu link de film, pornește filmul.</p></header>
<?= rep_widget('disc', [
    ['f' => 'img', 'label' => 'Imagine', 'type' => 'image'],
    ['f' => 'label', 'label' => 'Disciplina'], ['f' => 'meta', 'label' => 'Legenda (ex. proiectul)'],
    ['f' => 'project', 'label' => 'Duce la proiectul', 'type' => 'select', 'options' => project_options()],
    ['f' => 'film', 'label' => 'Sau pornește filmul (link MP4)', 'span' => 2],
], $disc, 'Card', ['' => 'Adaugă card']) ?>
</section>
<section class="card"><header><h2>How we work</h2><p>Pașii din caruselul orizontal, în ordine.</p></header>
<div class="grid2"><?= cf('about.stepsTitle', 'Titlul secțiunii') ?></div>
<?= rep_widget('steps', [
    ['f' => 'img', 'label' => 'Imagine', 'type' => 'image'],
    ['f' => 't', 'label' => 'Pas'], ['f' => 'd', 'label' => 'Descriere', 'type' => 'area', 'span' => 2],
], $steps, 'Pas', ['' => 'Adaugă pas']) ?>
</section>
<?= card_open('Available worldwide', 'Secțiunea finală, peste imaginea lată, cu butonul de contact.') ?>
<?= cf('about.worldTitle', 'Titlu') ?>
<?= cimg('img.world', 'Imagine de fundal (16:9)') ?>
<?= cf('about.worldText', 'Text', 'Țările și regiunile în care ați lucrat.', 'area', ['rows' => 3]) ?>
<?= cf('about.worldCta', 'Rândul de deasupra butonului') ?>
<?= card_close() ?>
<?= save_bar() ?>
</form>
<?php admin_foot();
