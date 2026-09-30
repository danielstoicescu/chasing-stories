<?php
/* Shared "move up / move down" for any table with sort_order. */
function sort_move(string $table, int $id, string $dir): void
{
    $rows = db()->query('SELECT id FROM ' . t($table) . ' ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
    $i = array_search($id, array_map('intval', $rows), true);
    if ($i === false) {
        return;
    }
    $j = $dir === 'up' ? $i - 1 : $i + 1;
    if ($j < 0 || $j >= count($rows)) {
        return;
    }
    [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
    $st = db()->prepare('UPDATE ' . t($table) . ' SET sort_order = ? WHERE id = ?');
    foreach ($rows as $n => $rid) {
        $st->execute([($n + 1) * 10, $rid]);
    }
    content_cache_clear();
}
function next_sort(string $table): int
{
    return (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM ' . t($table))->fetchColumn();
}
