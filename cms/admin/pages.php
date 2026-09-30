<?php
require __DIR__ . '/_boot.php';
$keys = ['brand', 'pages.workTitle', 'pages.workIntro', 'pages.photoTitle', 'pages.photoIntro', 'pages.filmTitle', 'pages.filmIntro',
    'pages.servicesTitle', 'pages.servicesIntro', 'pages.servicesBand', 'footer.line', 'privacy', 'notfound.h1', 'notfound.p', 'img.notfound'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    save_copy_from_post($keys);
    $red = [];
    foreach (preg_split('/\R/', (string) ($_POST['redirects'] ?? '')) as $line) {
        if (preg_match('#^\s*(/\S*)\s*(?:->|→|\s)\s*(/\S*|https?://\S+)\s*$#u', $line, $m)) {
            $red['/' . trim($m[1], '/')] = $m[2];
        }
    }
    $pairs = ['redirects' => $red, 'seo.description' => trim((string) ($_POST['seo_description'] ?? '')),
        'seo.home_title_suffix' => trim((string) ($_POST['seo_suffix'] ?? ''))];
    if (is_admin()) {
        $pairs['seo.index'] = ($_POST['seo_index'] ?? '0') === '1' ? '1' : '0';
    }
    setting_save($pairs);
    flash('Salvat.');
    redirect('pages.php');
}
$red = '';
foreach (setting_json('redirects', []) as $from => $to) {
    $red .= $from . ' -> ' . $to . "\n";
}
admin_head('Pagini și SEO', 'pages');
?>
<form method="post" class="edit"><?= csrf_field() ?>
<?= card_open('Google și distribuire', 'Titlul și descrierea paginii principale, cum apar în Google și când site-ul e distribuit pe WhatsApp sau social.') ?>
<?= f_text('seo_suffix', 'Titlul paginii principale (după nume)', setting('seo.home_title_suffix', 'Luxury Hospitality Photography & Film'), 'Rezultă: Chasing Stories | …', ['wide' => true]) ?>
<?= f_area('seo_description', 'Descriere', setting('seo.description', 'Visual storytelling for luxury hospitality, travel and lifestyle brands. Photography, film and creative production, available worldwide.'), 'Max 155 de caractere.', 2) ?>
<?php if (is_admin()): ?>
<?= f_check('seo_index', 'Site-ul poate apărea în Google', setting('seo.index', '0') === '1', 'Lasă debifat cât timp site-ul e în test. Bifează la lansare.') ?>
<?php endif ?>
<?= cf('brand', 'Numele studioului', 'Apare în titluri și în emailuri.') ?>
<?= card_close() ?>
<?= card_open('Work, Photography, Film') ?>
<?= cf('pages.workTitle', 'Work: titlu') ?><?= cf('pages.workIntro', 'Work: intro') ?>
<?= cf('pages.photoTitle', 'Photography: titlu') ?><?= cf('pages.photoIntro', 'Photography: intro', '', 'area', ['rows' => 3]) ?>
<?= cf('pages.filmTitle', 'Film: titlu') ?><?= cf('pages.filmIntro', 'Film: intro', '', 'area', ['rows' => 3]) ?>
<?= card_close() ?>
<a id="services"></a>
<?= card_open('Services', 'Serviciile propriu-zise se editează în Services.') ?>
<?= cf('pages.servicesTitle', 'Titlu') ?><?= cf('pages.servicesIntro', 'Intro', '', 'area', ['rows' => 3]) ?>
<?= cf('pages.servicesBand', 'Textul benzii de final', 'Deasupra butonului „Start a project”.', 'area', ['rows' => 2]) ?>
<?= card_close() ?>
<?= card_open('Footer, Privacy, 404') ?>
<?= cf('footer.line', 'Rândul din footer', '', 'text', ['wide' => true]) ?>
<?= cf('privacy', 'Textul Privacy & Cookies', 'Textul legal complet. Lasă un rând liber între paragrafe. Gol = se afișează structura de lucru.', 'area', ['rows' => 10]) ?>
<?= cf('notfound.h1', 'Pagina 404: titlu') ?><?= cf('notfound.p', 'Pagina 404: text') ?>
<?= cimg('img.notfound', 'Pagina 404: imagine') ?>
<?= card_close() ?>
<?= card_open('Redirecționări', 'Adrese vechi care trebuie să ducă în altă parte (cele din vechiul WordPress sunt deja incluse). Câte una pe rând: /adresa-veche -> /adresa-noua') ?>
<label class="fld wide"><span class="lb">Reguli</span><textarea name="redirects" rows="6" placeholder="/blog/articol-vechi -> /work"><?= e($red) ?></textarea></label>
<?= card_close() ?>
<?= save_bar() ?>
</form>
<?php admin_foot();
