<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_collection.php';
collection_page([
    'table' => 'clients', 'title' => 'Clienți', 'nav' => 'clients', 'colTitle' => 'Client',
    'listHint' => 'logo-urile din secțiunea Trusted by de pe homepage',
    'editHint' => 'Logo într-o singură culoare, ideal SVG cu fundal transparent. Pe site se afișează automat negru (sau alb pe tema închisă).',
    'fields' => [
        'logo' => ['Logo', 'logo', 'SVG sau PNG transparent.'],
        'name' => ['Nume client', 'text'],
        'project_slug' => ['Duce la proiectul', 'select', project_options()],
        'w' => ['Lățime logo', 'number'],
        'h' => ['Înălțime logo', 'number'],
        'published' => ['Afișat', 'check'],
    ],
    'defaults' => ['logo' => '', 'name' => '', 'project_slug' => '', 'w' => 50, 'h' => 40, 'published' => 1],
    'validate' => fn($v) => $v['name'] === '' ? 'Adaugă numele clientului.' : null,
    'list' => fn($r) => ['<img class="thumb logo" src="' . e(logo_url($r['logo'])) . '" alt="" loading="lazy">', $r['name'], $r['project_slug'] ? 'duce la ' . $r['project_slug'] : ''],
]);
