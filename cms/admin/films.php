<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_collection.php';
collection_page([
    'table' => 'films', 'title' => 'Film', 'nav' => 'films', 'colTitle' => 'Film',
    'editHint' => 'Filmele se grupează pe categorii; o categorie cu un singur film intră la „More films”. Dacă filmul aparține unui proiect, click-ul duce în proiect.',
    'fields' => [
        'poster' => ['Poster (16:9)', 'image', 'Un cadru ales, nu primul cadru automat.'],
        'video' => ['Link film (MP4)', 'text', 'Din Media (/uploads/video/…) sau de pe serviciul video. Pornește în player-ul site-ului.'],
        'title' => ['Titlu', 'text'],
        'client' => ['Client', 'text'],
        'loc' => ['Locație', 'text'],
        'cat' => ['Categorie', 'select', ['Hospitality' => 'Hospitality', 'Destination' => 'Destination', 'Lifestyle' => 'Lifestyle', 'Brand & Commercial' => 'Brand & Commercial']],
        'project_slug' => ['Proiect', 'select', project_options()],
        'published' => ['Publicat', 'check'],
    ],
    'defaults' => ['poster' => '', 'video' => '', 'title' => '', 'client' => '', 'loc' => '', 'cat' => 'Hospitality', 'project_slug' => '', 'published' => 1],
    'validate' => fn($v) => $v['title'] === '' ? 'Filmul are nevoie de un titlu.' : null,
    'list' => fn($r) => ['<img class="thumb" src="' . e(ref_url($r['poster'])) . '" alt="" loading="lazy">', $r['title'], trim($r['cat'] . ' · ' . $r['client'] . ($r['video'] ? '' : ' · fără fișier video'), ' ·')],
]);
