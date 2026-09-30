<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_collection.php';
$cats = setting_json('cats', []);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cats_save'])) {
    $new = [];
    foreach ((array) ($_POST['cat_id'] ?? []) as $i => $cid) {
        $label = trim((string) ($_POST['cat_label'][$i] ?? ''));
        if ($label !== '') $new[] = [slugify($cid ?: $label), $label];
    }
    setting_save(['cats' => $new]);
    flash('Categorii salvate.');
    redirect('photos.php');
}
$catOpts = [];
foreach ($cats as $c) $catOpts[$c[0]] = $c[1];
$catsForm = '<form method="post" class="card" style="margin-top:22px">' . csrf_field() . '<header><h2>Categorii</h2><p>Filtrele de pe pagina Photography. Adresa (ex. /photography/drone) nu se schimbă când redenumești.</p></header><div class="grid2">';
foreach (array_merge($cats, [['', '']]) as $c) {
    $catsForm .= '<label class="fld"><span class="lb">' . ($c[0] ? e('/photography/' . $c[0]) : 'Categorie nouă') . '</span><input type="hidden" name="cat_id[]" value="' . e($c[0]) . '"><input type="text" name="cat_label[]" value="' . e($c[1]) . '" placeholder="Nume categorie"></label>';
}
$catsForm .= '</div><div style="padding:0 20px 20px"><button class="btn" name="cats_save" value="1">Salvează categoriile</button></div></form>';
collection_page([
    'table' => 'photos', 'title' => 'Photography', 'nav' => 'photos', 'colTitle' => 'Poză',
    'editHint' => 'O poză poate intra în mai multe categorii. Dacă aparține unui proiect, click-ul duce în proiect.',
    'fields' => [
        'ref' => ['Poză', 'image', 'Latura lungă de minim 3000 px ideal. Se păstrează proporția.'],
        'cats' => ['Categorii', 'checks', $catOpts],
        'project_slug' => ['Proiect', 'select', project_options()],
        'loc' => ['Locație (legenda)', 'text', 'Ex. Raa Atoll, Maldives'],
        'alt' => ['Descriere (alt)', 'text', 'Ce se vede în poză, pentru Google și cititoare de ecran.'],
        'published' => ['Publicată', 'check'],
    ],
    'defaults' => ['ref' => '', 'cats' => '', 'project_slug' => '', 'loc' => '', 'alt' => '', 'published' => 1],
    'validate' => fn($v) => $v['ref'] === '' ? 'Alege o poză.' : null,
    'list' => fn($r) => ['<img class="thumb" src="' . e(ref_url($r['ref'])) . '" alt="" loading="lazy">', $r['loc'] ?: basename($r['ref']),
        implode(', ', array_map(fn($c) => $catOpts[$c] ?? $c, array_filter(explode(',', $r['cats'])))) . ($r['project_slug'] ? ' · ' . $r['project_slug'] : '')],
    'after' => $catsForm,
]);
