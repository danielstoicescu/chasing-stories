<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_collection.php';
collection_page([
    'table' => 'services', 'title' => 'Services', 'nav' => 'services', 'colTitle' => 'Serviciu', 'public' => false,
    'listHint' => 'apar pe homepage și pe pagina Services, în aceeași ordine',
    'toolbar' => '<a class="btn small ghost" href="pages.php#services">Textele paginii</a>',
    'fields' => [
        'name' => ['Nume', 'text'],
        'slug' => ['Adresă (ancoră)', 'text', 'Ex. film → /services/film. Linkurile de pe homepage duc aici.'],
        'short' => ['Rândul scurt (homepage)', 'text'],
        'body' => ['Descriere (pagina Services)', 'area', 'Lasă un rând liber între paragrafe.'],
        'deliv' => ['Livrabile tipice', 'text'],
        'img' => ['Imagine (4:5)', 'image'],
    ],
    'defaults' => ['name' => '', 'slug' => '', 'short' => '', 'body' => '', 'deliv' => '', 'img' => ''],
    'validate' => function (&$v, $id) {
        if ($v['name'] === '') return 'Adaugă numele serviciului.';
        $v['slug'] = slugify($v['slug'] !== '' ? $v['slug'] : $v['name']);
        return null;
    },
    'list' => fn($r) => ['<img class="thumb" src="' . e(ref_url($r['img'])) . '" alt="" loading="lazy">', $r['name'], $r['short']],
]);
