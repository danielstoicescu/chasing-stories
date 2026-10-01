<?php
/**
 * Admin chrome and form widgets.
 */

function admin_nav(): array
{
    return [
        'index'    => ['Mesaje', 'Formularul de contact'],
        'home'     => ['Homepage', 'Hero, selecții, texte'],
        'work'     => ['Work', 'Proiectele din portofoliu'],
        'photos'   => ['Photography', 'Galeria și categoriile'],
        'films'    => ['Film', 'Filmele'],
        'clients'  => ['Clienți', 'Logo-urile Trusted by'],
        'services' => ['Services', 'Cele șase servicii'],
        'about'    => ['About', 'Pagina despre studio'],
        'contact'  => ['Contact și email', 'Date de contact, destinatar, SMTP'],
        'pages'    => ['Pagini și SEO', 'Intro-uri, privacy, 404, indexare'],
        'media'    => ['Media', 'Biblioteca de imagini și filme'],
        'users'    => ['Utilizatori', 'Conturile din panou'],
    ];
}

function admin_head(string $title, string $active): void
{
    $flash = flash();
    $newCount = 0;
    try {
        $newCount = (int) db()->query('SELECT COUNT(*) FROM ' . t('enquiries') . " WHERE status = 'new'")->fetchColumn();
    } catch (Throwable $e) {
    }
    ?><!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · Chasing Stories</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,400..600&family=Geist:wght@400..600&family=Geist+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="../assets/admin.css?v=<?= @filemtime(CS_ROOT . '/assets/admin.css') ?>">
</head><body data-csrf="<?= e(csrf_token()) ?>">
<div class="shell">
  <aside class="side">
    <a class="brand" href="index.php"><span class="mark"><i></i><i></i><i></i></span><span>Chasing Stories</span></a>
    <nav>
      <?php foreach (admin_nav() as $k => [$label, $hint]):
          if ($k === 'users' && !is_admin()) continue; ?>
        <a href="<?= $k ?>.php" class="<?= $k === $active ? 'on' : '' ?>" title="<?= e($hint) ?>"><?= e($label) ?><?php if ($k === 'index' && $newCount): ?><b class="badge"><?= $newCount ?></b><?php endif ?></a>
      <?php endforeach ?>
    </nav>
    <div class="me">
      <a href="/" target="_blank" rel="noopener">Vezi site-ul ↗</a>
      <a href="account.php"><?= e(auth_name()) ?></a>
      <a href="logout.php">Ieșire</a>
    </div>
  </aside>
  <main class="main">
    <header class="top"><h1><?= e($title) ?></h1><button class="menu" type="button" onclick="document.body.classList.toggle('nav-open')">Meniu</button></header>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></div><?php endif ?>
<?php
}

function admin_foot(): void
{
    ?>
  </main>
</div>
<div class="picker" id="picker" hidden>
  <div class="pk-box" role="dialog" aria-modal="true" aria-label="Alege o imagine">
    <div class="pk-top"><strong>Biblioteca media</strong>
      <input type="search" id="pkSearch" placeholder="Caută după nume">
      <label class="btn small">Încarcă <input type="file" id="pkUpload" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,image/svg+xml,video/mp4,video/quicktime,.heic,.mov" multiple hidden></label>
      <button type="button" class="btn small ghost" id="pkClose">Închide</button></div>
    <div class="pk-status" id="pkStatus" hidden></div>
    <div class="pk-grid" id="pkGrid"></div>
  </div>
</div>
<script src="../assets/admin.js?v=<?= @filemtime(CS_ROOT . '/assets/admin.js') ?>"></script>
</body></html>
<?php
}

/* ---------------- widgets ---------------- */

function f_text(string $name, string $label, string $value, string $help = '', array $o = []): string
{
    $type = $o['type'] ?? 'text';
    return '<label class="fld' . (!empty($o['wide']) ? ' wide' : '') . '"><span class="lb">' . e($label) . '</span>'
        . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . (!empty($o['required']) ? ' required' : '')
        . (isset($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '') . '>'
        . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</label>';
}

function f_area(string $name, string $label, string $value, string $help = '', int $rows = 4): string
{
    return '<label class="fld wide"><span class="lb">' . e($label) . '</span><textarea name="' . e($name) . '" rows="' . $rows . '">' . e($value) . '</textarea>'
        . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</label>';
}

function f_select(string $name, string $label, string $value, array $options, string $help = ''): string
{
    $h = '<label class="fld"><span class="lb">' . e($label) . '</span><select name="' . e($name) . '">';
    foreach ($options as $k => $v) {
        $h .= '<option value="' . e($k) . '"' . ((string) $k === $value ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $h . '</select>' . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</label>';
}

function f_check(string $name, string $label, bool $on, string $help = ''): string
{
    return '<label class="fld check"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '>'
        . '<span>' . e($label) . ($help ? '<small>' . e($help) . '</small>' : '') . '</span></label>';
}

/** An image field: preview + "Alege" (library / upload) + clear. The value is a ref. */
function f_image(string $name, string $label, string $ref, string $help = '', string $kind = 'image'): string
{
    $url = $kind === 'logo' ? logo_url($ref) : ref_url($ref);
    return '<div class="fld img" data-kind="' . e($kind) . '"><span class="lb">' . e($label) . '</span>'
        . '<div class="imgbox"><img src="' . e($url) . '" alt=""' . ($url ? '' : ' hidden') . '><span class="none"' . ($url ? ' hidden' : '') . '>Nicio imagine</span></div>'
        . '<input type="hidden" name="' . e($name) . '" value="' . e($ref) . '" data-ref>'
        . '<div class="imgact"><button type="button" class="btn small" data-pick>Alege</button><button type="button" class="btn small ghost" data-clear>Scoate</button></div>'
        . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</div>';
}

/** Film field: pick or upload an MP4 from the library, or paste a link. */
function f_video(string $name, string $label, string $ref, string $help = ''): string
{
    return '<div class="fld img vidf" data-kind="video"><span class="lb">' . e($label) . '</span>'
        . '<input type="text" name="' . e($name) . '" value="' . e($ref) . '" data-ref placeholder="Alege sau încarcă un MP4, ori lipește un link">'
        . '<div class="imgact"><button type="button" class="btn small" data-pick>Alege / încarcă film</button><button type="button" class="btn small ghost" data-clear>Scoate</button></div>'
        . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</div>';
}

function card_open(string $title, string $hint = ''): string
{
    return '<section class="card"><header><h2>' . e($title) . '</h2>' . ($hint ? '<p>' . e($hint) . '</p>' : '') . '</header><div class="grid2">';
}

function card_close(): string
{
    return '</div></section>';
}

/** Save bar used at the bottom of every edit form. */
function save_bar(string $label = 'Salvează', string $extra = ''): string
{
    return '<div class="savebar"><button class="btn" type="submit">' . e($label) . '</button>' . $extra . '</div>';
}

/** Options list of published projects, for "links to project" selects. */
function project_options(bool $withNone = true): array
{
    $o = $withNone ? ['' => '— niciun proiect —'] : [];
    foreach (db()->query('SELECT slug, name FROM ' . t('projects') . ' ORDER BY sort_order, id') as $p) {
        $o[$p['slug']] = $p['name'];
    }
    return $o;
}

/* copy.* helpers */
function c(string $key, string $default = ''): string
{
    return setting('copy.' . $key, $default);
}

function save_copy_from_post(array $keys): void
{
    $pairs = [];
    foreach ($keys as $k) {
        $post = 'c_' . str_replace('.', '__', $k);
        if (array_key_exists($post, $_POST)) {
            $pairs['copy.' . $k] = trim((string) $_POST[$post]);
        }
    }
    setting_save($pairs);
}

function cf(string $key, string $label, string $help = '', string $type = 'text', array $o = []): string
{
    $name = 'c_' . str_replace('.', '__', $key);
    return $type === 'area' ? f_area($name, $label, c($key), $help, $o['rows'] ?? 4) : f_text($name, $label, c($key), $help, $o);
}

function cimg(string $key, string $label, string $help = ''): string
{
    return f_image('c_' . str_replace('.', '__', $key), $label, c($key), $help);
}

/* ---------------- repeaters ----------------
   $spec: list of ['f' => key, 'label' => ..., 'type' => text|area|select|image|logo|check, 'options' => [...],
                   'span' => 1|2|4, 'show' => 'type1 type2' (only for rows that have a "t" select)] */
function rep_field(array $s, $value): string
{
    $cls = 'fld' . (!empty($s['span']) && $s['span'] > 1 ? ' span' . $s['span'] : '');
    $show = !empty($s['show']) ? ' data-show="' . e($s['show']) . '"' : '';
    $lb = '<span class="lb">' . e($s['label']) . '</span>';
    $v = is_bool($value) ? ($value ? '1' : '') : (string) ($value ?? '');
    switch ($s['type'] ?? 'text') {
        case 'area':
            return '<label class="' . $cls . '"' . $show . '>' . $lb . '<textarea data-f="' . e($s['f']) . '" rows="3">' . e($v) . '</textarea></label>';
        case 'select':
            $h = '<label class="' . $cls . '"' . $show . '>' . $lb . '<select data-f="' . e($s['f']) . '">';
            foreach ($s['options'] as $k => $o) {
                $h .= '<option value="' . e($k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e($o) . '</option>';
            }
            return $h . '</select></label>';
        case 'check':
            return '<label class="fld check"' . $show . '><input type="checkbox" data-f="' . e($s['f']) . '"' . ($v ? ' checked' : '') . '><span>' . e($s['label']) . '</span></label>';
        case 'video':
            return '<div class="fld img vidf ' . trim(str_replace('fld', '', $cls)) . '" data-kind="video"' . $show . '>' . $lb
                . '<input type="text" data-f="' . e($s['f']) . '" value="' . e($v) . '" data-ref placeholder="Alege sau încarcă un MP4, ori lipește un link">'
                . '<div class="imgact"><button type="button" class="btn small" data-pick>Alege / încarcă film</button><button type="button" class="btn small ghost" data-clear>Scoate</button></div></div>';
        case 'image':
        case 'logo':
            $url = $s['type'] === 'logo' ? logo_url($v) : ref_url($v);
            return '<div class="fld img ' . trim(str_replace('fld', '', $cls)) . '" data-kind="' . e($s['type']) . '"' . $show . '>' . $lb
                . '<div class="imgbox"><img src="' . e($url) . '" alt=""' . ($url ? '' : ' hidden') . '><span class="none"' . ($url ? ' hidden' : '') . '>Nicio imagine</span></div>'
                . '<input type="hidden" data-f="' . e($s['f']) . '" value="' . e($v) . '" data-ref>'
                . '<div class="imgact"><button type="button" class="btn small" data-pick>Alege</button><button type="button" class="btn small ghost" data-clear>Scoate</button></div></div>';
        default:
            return '<label class="' . $cls . '"' . $show . '>' . $lb . '<input type="text" data-f="' . e($s['f']) . '" value="' . e($v) . '"' . (isset($s['placeholder']) ? ' placeholder="' . e($s['placeholder']) . '"' : '') . '></label>';
    }
}

function rep_row(array $spec, array $row, string $title): string
{
    $h = '<div class="rep-row"><div class="rowhead"><b>' . e($title) . '</b><span class="row-actions">'
        . '<button type="button" class="btn small ghost" data-up title="Mută sus">↑</button><button type="button" class="btn small ghost" data-down title="Mută jos">↓</button>'
        . '<button type="button" class="btn small danger" data-del>Șterge</button></span></div>';
    foreach ($spec as $s) {
        $h .= rep_field($s, $row[$s['f']] ?? '');
    }
    return $h . '</div>';
}

function rep_widget(string $into, array $spec, array $rows, string $rowTitle, array $addButtons = ['' => 'Adaugă']): string
{
    $h = '<input type="hidden" name="' . e($into) . '" value=""><div class="rep" data-into="' . e($into) . '"><div class="rep-list">';
    foreach ($rows as $r) {
        $h .= rep_row($spec, is_array($r) ? $r : [], $rowTitle);
    }
    $h .= '</div><template>' . rep_row($spec, [], $rowTitle) . '</template><div class="rep-add">';
    foreach ($addButtons as $preset => $label) {
        $h .= '<button type="button" class="btn small ghost" data-add="' . e($preset) . '">+ ' . e($label) . '</button>';
    }
    return $h . '</div></div>';
}

/** Decoded JSON posted by a repeater. */
function rep_post(string $into): array
{
    $d = json_decode((string) ($_POST[$into] ?? '[]'), true);
    return is_array($d) ? $d : [];
}
