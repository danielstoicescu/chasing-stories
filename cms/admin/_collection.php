<?php
/**
 * One pattern for the simple collections (photos, films, clients, services):
 * a list with order + visibility, and an edit form on ?id= (0 = new).
 * $cfg: table, title, nav, fields (name => [label, type, options/help]), list (callable row -> [thumbHtml, title, sub]),
 *       defaults, public (bool: has "published"), view (callable row -> url|null)
 */
require_once __DIR__ . '/_sort.php';

function collection_page(array $cfg): void
{
    $T = $cfg['table'];
    $hasPub = $cfg['public'] ?? true;
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['move'])) {
            [$rid, $dir] = explode(':', (string) $_POST['move']);
            sort_move($T, (int) $rid, $dir);
            redirect($cfg['nav'] . '.php');
        }
        if (isset($_POST['toggle']) && $hasPub) {
            db()->prepare('UPDATE ' . t($T) . ' SET published = 1 - published WHERE id = ?')->execute([(int) $_POST['toggle']]);
            content_cache_clear();
            redirect($cfg['nav'] . '.php');
        }
        if (isset($_POST['delete'])) {
            db()->prepare('DELETE FROM ' . t($T) . ' WHERE id = ?')->execute([(int) $_POST['delete']]);
            content_cache_clear();
            flash('Șters.');
            redirect($cfg['nav'] . '.php');
        }
        if (isset($_POST['save'])) {
            $vals = [];
            foreach ($cfg['fields'] as $name => $f) {
                $type = $f[1];
                if ($type === 'check') {
                    $vals[$name] = (int) ($_POST[$name] ?? 0);
                } elseif ($type === 'checks') {
                    $vals[$name] = implode(',', array_map('strval', (array) ($_POST[$name] ?? [])));
                } elseif ($type === 'number') {
                    $vals[$name] = max(5, min(100, (int) ($_POST[$name] ?? 50)));
                } else {
                    $vals[$name] = trim((string) ($_POST[$name] ?? ''));
                }
            }
            if (isset($cfg['validate']) && ($err = $cfg['validate']($vals, (int) $_POST['save']))) {
                flash($err, 'bad');
                redirect($cfg['nav'] . '.php?id=' . (int) $_POST['save']);
            }
            $rid = (int) $_POST['save'];
            if ($rid) {
                $set = implode(', ', array_map(fn($k) => $k . ' = ?', array_keys($vals)));
                db()->prepare('UPDATE ' . t($T) . ' SET ' . $set . ' WHERE id = ?')->execute([...array_values($vals), $rid]);
            } else {
                $vals['sort_order'] = next_sort($T);
                db()->prepare('INSERT INTO ' . t($T) . ' (' . implode(', ', array_keys($vals)) . ') VALUES (' . implode(', ', array_fill(0, count($vals), '?')) . ')')
                    ->execute(array_values($vals));
            }
            content_cache_clear();
            flash('Salvat.');
            redirect($cfg['nav'] . '.php');
        }
    }

    admin_head($cfg['title'], $cfg['nav']);
    if ($id !== null) {
        $row = $cfg['defaults'];
        if ($id) {
            $st = db()->prepare('SELECT * FROM ' . t($T) . ' WHERE id = ?');
            $st->execute([$id]);
            $row = $st->fetch() ?: $cfg['defaults'];
        }
        echo '<p><a href="' . $cfg['nav'] . '.php">← Înapoi la listă</a></p><form method="post" class="edit">' . csrf_field()
            . card_open($id ? 'Editează' : 'Adaugă', $cfg['editHint'] ?? '');
        foreach ($cfg['fields'] as $name => $f) {
            [$label, $type] = $f;
            $extra = $f[2] ?? '';
            $v = (string) ($row[$name] ?? '');
            echo match ($type) {
                'image'  => f_image($name, $label, $v, is_string($extra) ? $extra : ''),
                'logo'   => f_image($name, $label, $v, is_string($extra) ? $extra : '', 'logo'),
                'video'  => f_video($name, $label, $v, is_string($extra) ? $extra : ''),
                'area'   => f_area($name, $label, $v, is_string($extra) ? $extra : ''),
                'select' => f_select($name, $label, $v, $extra),
                'check'  => f_check($name, $label, (bool) $v),
                'number' => f_text($name, $label, $v, '% din căsuța logo-ului', ['type' => 'number']),
                'checks' => (function () use ($name, $label, $v, $extra) {
                    $on = array_filter(explode(',', $v));
                    $h = '<div class="fld"><span class="lb">' . e($label) . '</span><div style="display:flex;flex-wrap:wrap;gap:6px 16px">';
                    foreach ($extra as $k => $o) {
                        $h .= '<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="' . e($name) . '[]" value="' . e($k) . '"' . (in_array((string) $k, $on, true) ? ' checked' : '') . '>' . e($o) . '</label>';
                    }
                    return $h . '</div></div>';
                })(),
                default  => f_text($name, $label, $v, is_string($extra) ? $extra : ''),
            };
        }
        echo card_close() . save_bar('Salvează', $id ? '<button class="btn danger" name="delete" value="' . $id . '" data-confirm="Ștergi definitiv?">Șterge</button>' : '')
            . '<input type="hidden" name="save" value="' . (int) $id . '"></form>';
        admin_foot();
        return;
    }

    $rows = db()->query('SELECT * FROM ' . t($T) . ' ORDER BY sort_order, id')->fetchAll();
    echo '<div class="toolbar"><span class="muted">' . count($rows) . ' · ' . e($cfg['listHint'] ?? 'ordinea de aici e ordinea de pe site') . '</span><span class="sp"></span>'
        . ($cfg['toolbar'] ?? '') . '<a class="btn" href="' . $cfg['nav'] . '.php?id=0">+ Adaugă</a></div>';
    echo '<form method="post">' . csrf_field() . '<table class="list"><thead><tr><th></th><th>' . e($cfg['colTitle'] ?? 'Titlu') . '</th>' . ($hasPub ? '<th>Pe site</th>' : '') . '<th>Ordine</th><th></th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        [$thumb, $title, $sub] = $cfg['list']($r);
        echo '<tr><td>' . $thumb . '</td><td><a href="' . $cfg['nav'] . '.php?id=' . (int) $r['id'] . '"><strong>' . e($title) . '</strong></a><br><span class="muted">' . e($sub) . '</span></td>'
            . ($hasPub ? '<td><button class="btn small ' . ($r['published'] ? '' : 'ghost') . '" name="toggle" value="' . (int) $r['id'] . '">' . ($r['published'] ? 'Publicat' : 'Ascuns') . '</button></td>' : '')
            . '<td class="row-actions"><button class="btn small ghost" name="move" value="' . (int) $r['id'] . ':up"' . ($i ? '' : ' disabled') . '>↑</button>'
            . '<button class="btn small ghost" name="move" value="' . (int) $r['id'] . ':down"' . ($i < count($rows) - 1 ? '' : ' disabled') . '>↓</button></td>'
            . '<td><a class="btn small" href="' . $cfg['nav'] . '.php?id=' . (int) $r['id'] . '">Editează</a></td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="5" class="muted">Nimic aici încă.</td></tr>';
    }
    echo '</tbody></table></form>' . ($cfg['after'] ?? '');
    admin_foot();
}
