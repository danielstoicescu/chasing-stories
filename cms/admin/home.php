<?php
require __DIR__ . '/_boot.php';

$copyKeys = ['home.display', 'home.h1', 'home.sub', 'home.button', 'home.heroAlt', 'img.hero', 'img.heroM', 'video.hero', 'video.heroM',
    'home.workLabel', 'home.workLine', 'home.photoLabel', 'home.photoLine', 'home.trustedLabel', 'home.trustedLine', 'home.filmLabel', 'home.filmLine',
    'home.servicesLabel', 'home.studioLabel', 'home.studioLead', 'home.studioText', 'home.studioAlt', 'img.studio',
    'cta.h2', 'cta.lead', 'cta.projectH2', 'cta.button', 'available', 'img.prefooter'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    save_copy_from_post($copyKeys);
    $work = []; $workImg = [];
    foreach (rep_post('home_work') as $r) {
        $sl = (string) ($r['slug'] ?? '');
        if ($sl === '' || in_array($sl, $work, true)) continue;
        // first slot may stay empty (= project cover); extras keep their order
        $extra = array_values(array_filter([(string) ($r['img2'] ?? ''), (string) ($r['img3'] ?? ''), (string) ($r['img4'] ?? '')], 'strlen'));
        $first = (string) ($r['img'] ?? '');
        $work[] = $sl; $workImg[] = ($first === '' && !$extra) ? [] : array_merge([$first], $extra);
    }
    $photos = array_values(array_map(fn($r) => [(string) $r['img'], (string) $r['label']], array_filter(rep_post('home_photos'), fn($r) => !empty($r['img']))));
    $films = array_values(array_map(fn($r) => ['k' => (string) $r['k'], 'title' => (string) $r['title'], 'meta' => (string) $r['meta'],
        'project' => (string) $r['project'] ?: null, 'video' => (string) $r['video']], array_filter(rep_post('home_films'), fn($r) => !empty($r['k']))));
    setting_save(['home.work' => $work, 'home.workImg' => $workImg, 'home.photos' => $photos, 'home.films' => $films]);
    flash('Homepage salvat. Modificările sunt deja pe site.');
    redirect('home.php');
}

$projects = project_options(false);
admin_head('Homepage', 'home');
?>
<form method="post" class="edit"><?= csrf_field() ?>

<?= card_open('Hero', 'Prima imagine de pe site. Pixelii apar doar la trecerea mouse-ului.') ?>
<?= cf('home.display', 'Wordmark mare', 'Rămâne numele studioului până vine logo-ul.') ?>
<?= cf('home.button', 'Text buton') ?>
<?= cf('home.h1', 'Titlu (H1)', 'Contează pentru Google: descrie ce face studioul.', 'text', ['wide' => true]) ?>
<?= cf('home.sub', 'Subtitlu', '', 'text', ['wide' => true]) ?>
<?= cimg('img.hero', 'Imagine desktop (16:9)', 'Afișată până pornește video-ul și pe dispozitive cu mișcare redusă.') ?>
<?= cimg('img.heroM', 'Imagine mobil (4:5)') ?>
<?= f_video('c_video__hero', 'Film hero desktop (orizontal)', c('video.hero'), 'Pornește peste poza hero, fără sunet. Gol = filmul livrat cu site-ul.') ?>
<?= f_video('c_video__heroM', 'Film hero mobil (vertical)', c('video.heroM'), 'Pe telefon. Gol = filmul vertical livrat cu site-ul.') ?>
<?= cf('home.heroAlt', 'Descrierea imaginii (alt)', 'Pentru cititoare de ecran și Google.', 'text', ['wide' => true]) ?>
<?= card_close() ?>

<?= card_open('Selected work', 'Proiectele de pe homepage, în ordinea afișării. Recomandat: 6.') ?>
<?= cf('home.workLabel', 'Eticheta secțiunii') ?><?= cf('home.workLine', 'Rândul de intro') ?>
<?php $wi = setting_json('home.workImg', []); ?>
<p class="help span-all" style="grid-column:1/-1;margin:0">Fiecare proiect poate avea până la 4 poze pe homepage: prima e cea afișată, celelalte se derulează când treci cu mouse-ul peste card (pe telefon, singure). Fără poze = coperta proiectului.</p>
<?= rep_widget('home_work', [['f' => 'slug', 'label' => 'Proiect', 'type' => 'select', 'options' => $projects, 'span' => 4],
    ['f' => 'img', 'label' => 'Poza 1 (principală)', 'type' => 'image'], ['f' => 'img2', 'label' => 'Poza 2', 'type' => 'image'],
    ['f' => 'img3', 'label' => 'Poza 3', 'type' => 'image'], ['f' => 'img4', 'label' => 'Poza 4', 'type' => 'image']],
    array_map(function ($s, $i) use ($wi) { $im = array_values((array) ($wi[$i] ?? [])); return ['slug' => $s, 'img' => $im[0] ?? '', 'img2' => $im[1] ?? '', 'img3' => $im[2] ?? '', 'img4' => $im[3] ?? '']; },
        setting_json('home.work', []), array_keys(setting_json('home.work', []))), 'Proiect', ['' => 'Adaugă proiect']) ?>
<?= card_close() ?>

<?= card_open('Photography pe homepage', 'Șapte poze, cele mai bune cadre, predominant verticale. Se deschid mari la click.') ?>
<?= cf('home.photoLabel', 'Eticheta secțiunii') ?><?= cf('home.photoLine', 'Rândul de intro') ?>
<?= rep_widget('home_photos', [['f' => 'img', 'label' => 'Poză', 'type' => 'image'], ['f' => 'label', 'label' => 'Categorie afișată', 'span' => 2]],
    array_map(fn($p) => ['img' => $p[0] ?? '', 'label' => $p[1] ?? ''], setting_json('home.photos', [])), 'Poză', ['' => 'Adaugă poză']) ?>
<?= card_close() ?>

<?= card_open('Trusted by', 'Logo-urile se editează în Clienți.') ?>
<?= cf('home.trustedLabel', 'Eticheta secțiunii') ?><?= cf('home.trustedLine', 'Rândul de intro') ?>
<?= card_close() ?>

<?= card_open('Film pe homepage', 'Trei filme. Primul e afișat mare. Dacă alegi un proiect, click-ul duce în proiect; altfel pornește filmul.') ?>
<?= cf('home.filmLabel', 'Eticheta secțiunii') ?><?= cf('home.filmLine', 'Rândul de intro') ?>
<?= rep_widget('home_films', [
    ['f' => 'k', 'label' => 'Poster (16:9)', 'type' => 'image'],
    ['f' => 'title', 'label' => 'Titlu'], ['f' => 'meta', 'label' => 'Client, locație'],
    ['f' => 'project', 'label' => 'Duce la proiectul', 'type' => 'select', 'options' => ['' => '— pornește filmul —'] + $projects],
    ['f' => 'video', 'label' => 'Link film (MP4)', 'span' => 2, 'placeholder' => 'https://… sau /uploads/video/…'],
], setting_json('home.films', []), 'Film', ['' => 'Adaugă film']) ?>
<?= card_close() ?>

<?= card_open('The studio') ?>
<?= cf('home.studioLabel', 'Eticheta secțiunii') ?>
<?= cimg('img.studio', 'Imagine (verticală)') ?>
<?= cf('home.studioLead', 'Primul paragraf (mare)', '', 'area', ['rows' => 2]) ?>
<?= cf('home.studioText', 'Restul textului', 'Lasă un rând liber între paragrafe.', 'area', ['rows' => 6]) ?>
<?= cf('home.studioAlt', 'Descrierea imaginii (alt)') ?>
<?= cf('home.servicesLabel', 'Eticheta listei de servicii', 'Serviciile se editează în Services.') ?>
<?= card_close() ?>

<?= card_open('CTA de final', 'Banda „Have a project in mind?” de la finalul paginilor.') ?>
<?= cf('cta.h2', 'Titlu') ?><?= cf('cta.lead', 'Rând secundar') ?>
<?= cf('cta.projectH2', 'Titlu pe paginile de proiect') ?><?= cf('cta.button', 'Text buton') ?>
<?= cf('available', 'Mențiune disponibilitate') ?>
<?= cimg('img.prefooter', 'Imagine de fundal (16:9)') ?>
<?= card_close() ?>

<?= save_bar() ?>
</form>
<?php admin_foot();
