<?php
require __DIR__ . '/_boot.php';

$statuses = ['new' => 'Nou', 'read' => 'Citit', 'replied' => 'Răspuns', 'archived' => 'Arhivat'];
$f = $_GET['s'] ?? '';
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enquiries-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', 'Name', 'Company', 'Email', 'Website', 'Location', 'Dates', 'Type', 'Details', 'Source', 'Status']);
    foreach (db()->query('SELECT * FROM ' . t('enquiries') . ' ORDER BY id DESC') as $r) {
        fputcsv($out, [$r['created_at'], $r['name'], $r['company'], $r['email'], $r['website'], $r['location'], $r['dates'], $r['type'], $r['details'], $r['source'], $r['status']]);
    }
    exit;
}
$where = isset($statuses[$f]) ? ' WHERE status = ' . db()->quote($f) : " WHERE status <> 'archived'";
$rows = db()->query('SELECT * FROM ' . t('enquiries') . $where . ' ORDER BY id DESC LIMIT 300')->fetchAll();
$counts = [];
foreach (db()->query('SELECT status, COUNT(*) n FROM ' . t('enquiries') . ' GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$month = db()->prepare('SELECT COUNT(*) FROM ' . t('enquiries') . ' WHERE created_at >= ?');
$month->execute([date('Y-m-01')]);

admin_head('Mesaje', 'index');
?>
<div class="stats">
  <div class="stat"><b><?= $counts['new'] ?? 0 ?></b><span>Noi</span></div>
  <div class="stat"><b><?= (int) $month->fetchColumn() ?></b><span>Luna aceasta</span></div>
  <div class="stat"><b><?= ($counts['replied'] ?? 0) ?></b><span>Cu răspuns</span></div>
  <div class="stat"><b class="sm"><?= e(setting('mail.to', 'begin@chasingstories.org')) ?></b><span>Merg pe email la</span></div>
</div>
<div class="toolbar">
  <div class="chips"><a href="index.php" class="<?= $f === '' ? 'on' : '' ?>">Active</a>
  <?php foreach ($statuses as $k => $v): ?><a href="?s=<?= $k ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= e($v) ?> <?= $counts[$k] ?? 0 ?></a><?php endforeach ?></div>
  <span class="sp"></span><a class="btn small ghost" href="?export=1">Export CSV</a>
</div>
<table class="list"><thead><tr><th>Data</th><th>De la</th><th class="hide-m">Proiect</th><th class="hide-m">Tip</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr style="cursor:pointer" onclick="location='enquiry.php?id=<?= (int) $r['id'] ?>'">
  <td class="muted"><?= e(date('d.m.Y H:i', strtotime((string) $r['created_at']))) ?></td>
  <td><a href="enquiry.php?id=<?= (int) $r['id'] ?>"><strong><?= e($r['name']) ?></strong></a><br><span class="muted"><?= e($r['company']) ?> · <?= e($r['email']) ?></span></td>
  <td class="hide-m"><?= e($r['location']) ?><?= $r['source'] && $r['source'] !== 'contact' ? '<br><span class="muted">' . e($r['source']) . '</span>' : '' ?></td>
  <td class="hide-m"><?= e($r['type']) ?></td>
  <td><span class="status <?= e($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
</tr>
<?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Niciun mesaj aici încă. Mesajele din formularul de contact apar aici și pleacă pe email la adresa din „Contact și email”.</td></tr><?php endif ?>
</tbody></table>
<?php admin_foot();
