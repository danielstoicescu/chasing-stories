<?php
/**
 * A plain-HTML version of each page, sent inside <main id="app"> so search engines and link previews
 * read the real content and the links between pages without running JavaScript.
 * The front end replaces it with the designed page as soon as it starts (same content, more presentation).
 */

function seo_snapshot(array $seg, array $data, string $desc): string
{
    $C = $data['copy'];
    $brand = $C['brand'] ?? 'Chasing Stories';
    $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $img = function (string $ref, string $alt) use ($h): string {
        $u = ref_url($ref);
        return $u ? '<img src="' . $h($u) . '" alt="' . $h($alt) . '" loading="lazy">' : '';
    };
    $paras = fn(string $t) => implode('', array_map(fn($p) => '<p>' . $h(trim($p)) . '</p>', array_filter(preg_split('/\n\s*\n/', $t) ?: [])));
    $projLink = fn(array $p) => '<li><a href="/work/' . rawurlencode($p['slug']) . '">' . $h($p['name']) . '</a>'
        . ($p['location'] ? ' · ' . $h($p['location']) : '') . ($p['services'] ? ' · ' . $h(implode(', ', $p['services'])) : '') . '</li>';
    $projects = $data['projects'];
    $bySlug = array_column($projects, null, 'slug');
    $top = $seg[0] ?? '';
    $o = '';

    if (!$seg) {
        $home = array_values(array_filter(array_map(fn($s) => $bySlug[$s] ?? null, $data['homeWork'] ?: array_column($projects, 'slug'))));
        $o .= '<p>' . $h($brand) . '</p><h1>' . $h($C['home']['h1'] ?? 'Visual storytelling for luxury hospitality, travel & lifestyle brands.') . '</h1><p>' . $h($desc) . '</p>'
            . '<h2>Selected work</h2><ul>' . implode('', array_map($projLink, $home)) . '</ul>'
            . '<h2>Services</h2><ul>' . implode('', array_map(fn($s) => '<li><a href="/services/' . rawurlencode($s['id']) . '">' . $h($s['name']) . '</a>' . ($s['short'] ? ': ' . $h($s['short']) : '') . '</li>', $data['services'])) . '</ul>'
            . '<h2>Trusted by</h2><ul>' . implode('', array_map(fn($l) => '<li>' . ($l[2] && isset($bySlug[$l[2]]) ? '<a href="/work/' . rawurlencode($l[2]) . '">' . $h($l[1]) . '</a>' : $h($l[1])) . '</li>', $data['logos'])) . '</ul>'
            . '<p><a href="/contact">Start a project</a></p>';
    } elseif ($top === 'work' && count($seg) === 1) {
        $o .= '<h1>Work</h1><p>' . $h($desc) . '</p><ul>' . implode('', array_map($projLink, $projects)) . '</ul>';
    } elseif ($top === 'work' && isset($bySlug[$seg[1] ?? ''])) {
        $p = $bySlug[$seg[1]];
        $alt = $p['name'] . ($p['location'] ? ', ' . $p['location'] : '');
        $o .= '<h1>' . $h($p['name']) . '</h1>'
            . '<p>' . $h(implode(' · ', array_filter([$p['client'], $p['location'], $p['year']]))) . '</p>'
            . ($p['services'] ? '<p>' . $h(implode(', ', $p['services'])) . '</p>' : '')
            . $paras((string) $p['desc']) . $img((string) ($p['hero'] ?: $p['coverL']), $alt);
        $n = 0;
        foreach ($p['blocks'] as $b) {
            foreach ((array) ($b['k'] ?? []) as $k) {
                if ($n++ < 16 && is_string($k)) {
                    $o .= $img($k, $alt);
                }
            }
            if (($b['t'] ?? '') === 'text' && !empty($b['text'])) {
                $o .= $paras((string) $b['text']);
            }
        }
        $i = array_search($p['slug'], array_column($projects, 'slug'), true);
        $next = $projects[($i + 1) % count($projects)];
        $o .= '<p><a href="/work/' . rawurlencode($next['slug']) . '">Next project: ' . $h($next['name']) . '</a> · <a href="/work">All work</a> · <a href="/contact?project=' . rawurlencode($p['slug']) . '">Enquire about a similar project</a></p>';
    } elseif ($top === 'photography') {
        $o .= '<h1>Photography</h1><p>' . $h($desc) . '</p><ul>'
            . implode('', array_map(fn($c) => '<li><a href="/photography/' . rawurlencode($c[0]) . '">' . $h($c[1]) . '</a></li>', $data['cats'])) . '</ul>';
        $alts = [];
        foreach (db()->query('SELECT ref, alt FROM ' . t('photos') . ' WHERE published = 1') as $r) {
            $alts[$r['ref']] = (string) $r['alt'];
        }
        foreach (array_slice($data['photos'], 0, 30) as $ph) {
            $o .= $img((string) $ph['k'], ($alts[$ph['k']] ?? '') ?: trim(($ph['loc'] ?: 'Photography') . ' by ' . $brand));
        }
    } elseif ($top === 'film') {
        $o .= '<h1>Film</h1><p>' . $h($desc) . '</p><ul>' . implode('', array_map(fn($f) => '<li>'
            . ($f['project'] && isset($bySlug[$f['project']]) ? '<a href="/work/' . rawurlencode($f['project']) . '">' . $h($f['title']) . '</a>' : $h($f['title']))
            . ($f['client'] ? ' · ' . $h($f['client']) : '') . ($f['loc'] ? ' · ' . $h($f['loc']) : '') . '</li>', $data['films'])) . '</ul>';
    } elseif ($top === 'services') {
        $o .= '<h1>Services</h1><p>' . $h($desc) . '</p>';
        foreach ($data['services'] as $s) {
            $o .= '<h2 id="svc-' . $h($s['id']) . '">' . $h($s['name']) . '</h2>' . $paras($s['body'] ?: $s['short']) . ($s['deliv'] ? '<p>Typical deliverables: ' . $h($s['deliv']) . '</p>' : '');
        }
        $o .= '<p><a href="/contact">Start a project</a></p>';
    } elseif ($top === 'about') {
        $A = $C['about'] ?? [];
        $o .= '<h1>' . $h($A['title'] ?? 'About') . '</h1>' . (!empty($A['intro']) ? $paras((string) $A['intro']) : '<p>' . $h($desc) . '</p>')
            . '<p><a href="/work">See the work</a> · <a href="/contact">Contact</a></p>';
    } elseif ($top === 'contact') {
        $K = $C['contact'] ?? [];
        $o .= '<h1>' . $h($K['title'] ?? 'Contact') . '</h1><p>' . $h($K['intro'] ?? $desc) . '</p>'
            . (!empty($K['email']) ? '<p>Email: <a href="mailto:' . $h($K['email']) . '">' . $h($K['email']) . '</a></p>' : '')
            . (!empty($K['instagram']) ? '<p><a href="' . $h($K['instagram']) . '" rel="noopener">Instagram</a></p>' : '');
    }
    return $o === '' ? '' : '<div class="ssr">' . $o . '</div>';
}
