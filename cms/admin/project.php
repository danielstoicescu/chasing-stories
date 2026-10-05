<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_sort.php';

$id = (int) ($_GET['id'] ?? 0);
$p = ['id' => 0, 'slug' => '', 'name' => '', 'client' => '', 'location' => '', 'year' => '', 'services' => '[]', 'description' => '', 'hero' => '', 'hero_m' => '', 'hero_video' => '', 'hero_video_m' => '', 'category' => '',
    'cover_v' => '', 'cover_l' => '', 'logo' => '', 'blocks' => '[]', 'meta_description' => '', 'published' => 1];
if ($id) {
    $st = db()->prepare('SELECT * FROM ' . t('projects') . ' WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch() ?: redirect('work.php');
}
$SERVICES = ['Photography', 'Film', 'Drone', 'Creative Direction', 'Lifestyle Production'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete']) && $id) {
        db()->prepare('DELETE FROM ' . t('projects') . ' WHERE id = ?')->execute([$id]);
        content_cache_clear();
        flash('Proiect șters.');
        redirect('work.php');
    }
    $name = trim((string) $_POST['name']);
    $slug = slugify(trim((string) $_POST['slug']) ?: $name);
    $dupe = db()->prepare('SELECT COUNT(*) FROM ' . t('projects') . ' WHERE slug = ? AND id <> ?');
    $dupe->execute([$slug, $id]);
    if ($dupe->fetchColumn()) {
        $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
    }
    $services = array_values(array_intersect($SERVICES, (array) ($_POST['services'] ?? [])));
    foreach (array_filter(array_map('trim', explode(',', (string) ($_POST['services_other'] ?? '')))) as $x) {
        $services[] = $x;
    }
    $blocks = [];
    foreach (rep_post('blocks') as $b) {
        $t = in_array($b['t'] ?? '', ['large', 'pair', 'full', 'drone', 'mixed', 'video', 'text'], true) ? $b['t'] : 'large';
        $blk = ['t' => $t];
        if ($t === 'text') {
            if (trim((string) $b['text']) === '') continue;
            $blk['text'] = (string) $b['text'];
        } elseif (in_array($t, ['pair', 'mixed'], true)) {
            if (empty($b['k1']) || empty($b['k2'])) continue;
            $blk['k'] = [(string) $b['k1'], (string) $b['k2']];
            if ($t === 'mixed' && !empty($b['video'])) { $blk['video'] = 1; $blk['src'] = (string) $b['src']; }
        } else {
            if (empty($b['k1'])) continue;
            $blk['k'] = (string) $b['k1'];
            if ($t === 'large' && ($b['side'] ?? '') === 'right') $blk['side'] = 'right';
            if ($t === 'video') $blk['src'] = (string) $b['src'];
        }
        $blocks[] = $blk;
    }
    $vals = [$slug, $name, trim((string) $_POST['client']), trim((string) $_POST['location']), trim((string) $_POST['year']),
        json_encode($services, JSON_UNESCAPED_UNICODE), trim((string) $_POST['description']),
        (string) $_POST['hero'], (string) $_POST['hero_m'], trim((string) ($_POST['hero_video'] ?? '')), trim((string) ($_POST['hero_video_m'] ?? '')), mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 60), (string) $_POST['cover_v'], (string) $_POST['cover_l'], (string) $_POST['logo'],
        json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), mb_substr(trim((string) $_POST['meta_description']), 0, 255),
        (int) ($_POST['published'] ?? 0), date('Y-m-d H:i:s')];
    if ($name === '') {
        flash('Proiectul are nevoie de un nume.', 'bad');
    } elseif ($id) {
        db()->prepare('UPDATE ' . t('projects') . ' SET slug=?, name=?, client=?, location=?, year=?, services=?, description=?, hero=?, hero_m=?, hero_video=?, hero_video_m=?, category=?, cover_v=?, cover_l=?, logo=?, blocks=?, meta_description=?, published=?, updated_at=? WHERE id=?')
            ->execute([...$vals, $id]);
        flash('Proiect salvat.');
    } else {
        db()->prepare('INSERT INTO ' . t('projects') . ' (slug, name, client, location, year, services, description, hero, hero_m, hero_video, hero_video_m, category, cover_v, cover_l, logo, blocks, meta_description, published, updated_at, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([...$vals, next_sort('projects')]);
        $id = (int) db()->lastInsertId();
        flash('Proiect creat.');
    }
    content_cache_clear();
    redirect('project.php?id=' . $id);
}

$services = json_decode((string) $p['services'], true) ?: [];
$other = implode(', ', array_diff($services, $SERVICES));
$blocks = array_map(function ($b) {
    $k = (array) ($b['k'] ?? []);
    return ['t' => $b['t'] ?? 'large', 'k1' => $k[0] ?? '', 'k2' => $k[1] ?? '', 'side' => $b['side'] ?? '', 'video' => !empty($b['video']), 'src' => $b['src'] ?? '', 'text' => $b['text'] ?? ''];
}, json_decode((string) $p['blocks'], true) ?: []);
$types = ['large' => 'Poză mare (verticală)', 'pair' => 'Pereche de poze', 'full' => 'Poză pe toată lățimea', 'drone' => 'Dronă (lată)',
    'mixed' => 'Mixt: poză + poză sau film', 'video' => 'Film', 'text' => 'Text'];
$logos = ['' => '— fără logo —'];
foreach (db()->query('SELECT name, logo FROM ' . t('clients') . ' ORDER BY sort_order') as $c) {
    if ($c['logo'] !== '') $logos[$c['logo']] = $c['name'];
}
if ($p['logo'] !== '' && !isset($logos[$p['logo']])) {
    $logos[$p['logo']] = $p['logo'];
}

admin_head($id ? $p['name'] : 'Proiect nou', 'work');
?>
<p><a href="work.php">← Toate proiectele</a><?php if ($id): ?> · <a href="/work/<?= e($p['slug']) ?>" target="_blank" rel="noopener">Vezi pe site ↗</a><?php endif ?></p>
<form method="post" class="edit"><?= csrf_field() ?>
<?= card_open('Detalii') ?>
<label class="fld"><span class="lb">Nume proiect</span><input type="text" name="name" value="<?= e($p['name']) ?>" required data-slug-source></label>
<?= f_text('slug', 'Adresă (slug)', (string) $p['slug'], 'Apare în link: /work/adresa. Doar litere mici, cifre și cratime.') ?>
<?= f_text('client', 'Client', (string) $p['client'], 'Exact cum vrea clientul să apară.') ?>
<?= f_text('location', 'Locație', (string) $p['location'], 'Oraș, țară') ?>
<?= f_text('year', 'An', (string) $p['year']) ?>
<?php $usedCats = array_values(array_unique(array_filter(array_merge(['Hospitality', 'Brands'], db()->query('SELECT DISTINCT category FROM ' . t('projects'))->fetchAll(PDO::FETCH_COLUMN))))); ?>
<label class="fld"><span class="lb">Categorie</span><input type="text" name="category" value="<?= e((string) ($p['category'] ?? '')) ?>" list="projCats" placeholder="Hospitality">
  <datalist id="projCats"><?php foreach ($usedCats as $c): ?><option value="<?= e($c) ?>"><?php endforeach ?></datalist>
  <span class="help">Ex. Hospitality sau Brands. Pe pagina Work apar filtre când există cel puțin două categorii. Gol = Hospitality.</span></label>
<div class="fld"><span class="lb">Servicii</span><div style="display:flex;flex-wrap:wrap;gap:6px 16px">
<?php foreach ($SERVICES as $s): ?><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="services[]" value="<?= e($s) ?>" <?= in_array($s, $services, true) ? 'checked' : '' ?>><?= e($s) ?></label><?php endforeach ?>
</div><input type="text" name="services_other" value="<?= e($other) ?>" placeholder="Altele, separate prin virgulă"></div>
<?= f_area('description', 'Descriere (opțional)', (string) $p['description'], '60–120 de cuvinte. Poate lipsi când pozele spun povestea.', 4) ?>
<?= f_text('meta_description', 'Descriere pentru Google (opțional)', (string) $p['meta_description'], 'Max 155 de caractere. Gol = se generează automat din servicii, client și locație.', ['wide' => true]) ?>
<?= f_check('published', 'Publicat pe site', (bool) $p['published']) ?>
<?= card_close() ?>

<?= card_open('Imagini principale', 'Hero-ul e afișat pe tot ecranul; copertele apar pe Home, Work și în banda „Next project”.') ?>
<?= f_image('hero', 'Hero desktop (16:9)', (string) $p['hero']) ?>
<?= f_image('hero_m', 'Hero mobil (4:5)', (string) $p['hero_m']) ?>
<?php $shipped = fn(string $k) => $k !== '' && is_file(CS_ROOT . '/assets/video/' . $k . '.mp4') ? ' Gol = filmul livrat cu site-ul (' . $k . '.mp4).' : ' Gol = doar poza.'; ?>
<?= f_video('hero_video', 'Film hero desktop (orizontal)', (string) ($p['hero_video'] ?? ''), 'Pornește peste poza hero, fără sunet, în buclă.' . $shipped((string) $p['hero'])) ?>
<?= f_video('hero_video_m', 'Film hero mobil (vertical)', (string) ($p['hero_video_m'] ?? ''), 'Pe telefon. Gol = filmul de desktop.') ?>
<?= f_image('cover_v', 'Copertă verticală (4:5)', (string) $p['cover_v']) ?>
<?= f_image('cover_l', 'Copertă orizontală (3:2)', (string) $p['cover_l']) ?>
<?= f_select('logo', 'Logo client (afișat după conținut)', (string) $p['logo'], $logos, 'Logo-urile se adaugă din Clienți.') ?>
<?= card_close() ?>

<section class="card"><header><h2>Conținut</h2><p>Blocurile de sub hero, de sus în jos. Pozele își păstrează proporțiile. Prima poză și cea de la mijloc primesc paleta de culori.</p></header>
<?= rep_widget('blocks', [
    ['f' => 't', 'label' => 'Tip bloc', 'type' => 'select', 'options' => $types, 'span' => 2],
    ['f' => 'side', 'label' => 'Aliniere', 'type' => 'select', 'options' => ['' => 'Stânga', 'right' => 'Dreapta'], 'show' => 'large'],
    ['f' => 'k1', 'label' => 'Poză / poster', 'type' => 'image', 'show' => 'large pair full drone mixed video'],
    ['f' => 'k2', 'label' => 'A doua poză / posterul filmului', 'type' => 'image', 'show' => 'pair mixed'],
    ['f' => 'video', 'label' => 'A doua e film', 'type' => 'check', 'show' => 'mixed'],
    ['f' => 'src', 'label' => 'Film (MP4)', 'type' => 'video', 'show' => 'video mixed', 'span' => 2],
    ['f' => 'text', 'label' => 'Text', 'type' => 'area', 'show' => 'text', 'span' => 4],
], $blocks, 'Bloc', ['large' => 'Poză mare', 'pair' => 'Pereche', 'full' => 'Lată', 'drone' => 'Dronă', 'mixed' => 'Mixt', 'video' => 'Film', 'text' => 'Text']) ?>
</section>

<?= save_bar($id ? 'Salvează proiectul' : 'Creează proiectul', $id ? '<button class="btn danger" name="delete" value="1" data-confirm="Ștergi definitiv proiectul?">Șterge</button>' : '') ?>
</form>
<?php admin_foot();
