<?php
/** Modul Transaksi — input manual & pengelolaan (FR-021; perubahan tercatat di log FR-032). */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';

function tx_list(int $wsId): array
{
    $st = db()->prepare('SELECT * FROM transactions WHERE workspace_id = ? ORDER BY tx_date DESC, id DESC');
    $st->execute([$wsId]);
    return $st->fetchAll();
}

function tx_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM transactions WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function tx_count(int $wsId): int
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM transactions WHERE workspace_id = ?');
    $st->execute([$wsId]);
    return (int) ($st->fetch()['c'] ?? 0);
}

function tx_totals(int $wsId): array
{
    $st = db()->prepare("SELECT
        COALESCE(SUM(CASE WHEN type = 'masuk' THEN amount ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN type = 'keluar' THEN amount ELSE 0 END), 0) AS total_keluar
        FROM transactions WHERE workspace_id = ?");
    $st->execute([$wsId]);
    $row = $st->fetch();
    $masuk = (int) ($row['total_masuk'] ?? 0);
    $keluar = (int) ($row['total_keluar'] ?? 0);
    return ['masuk' => $masuk, 'keluar' => $keluar, 'selisih' => $masuk - $keluar];
}

function tx_add(int $wsId, int $actorId, string $actorUsername, string $date, string $type, int $amount, string $description): int
{
    $now = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO transactions (workspace_id, created_by, tx_date, type, amount, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$wsId, $actorId, $date, $type, $amount, $description, $now, $now]);
    $id = (int) db()->lastInsertId();
    log_write($wsId, $actorId, $actorUsername, 'tambah', 'transaksi', $id, 'Menambah transaksi ' . tx_label($type, $amount, $description));
    return $id;
}

function tx_update(int $wsId, int $actorId, string $actorUsername, int $id, string $date, string $type, int $amount, string $description): void
{
    db()->prepare('UPDATE transactions SET tx_date = ?, type = ?, amount = ?, description = ?, updated_at = ? WHERE id = ? AND workspace_id = ?')
        ->execute([$date, $type, $amount, $description, date('Y-m-d H:i:s'), $id, $wsId]);
    log_write($wsId, $actorId, $actorUsername, 'ubah', 'transaksi', $id, 'Mengubah transaksi ' . tx_label($type, $amount, $description));
}

function tx_delete(int $wsId, int $actorId, string $actorUsername, int $id): void
{
    $tx = tx_get($id);
    if ($tx === null || (int) $tx['workspace_id'] !== $wsId) {
        return;
    }
    db()->prepare('DELETE FROM transactions WHERE id = ? AND workspace_id = ?')->execute([$id, $wsId]);
    log_write($wsId, $actorId, $actorUsername, 'hapus', 'transaksi', $id, 'Menghapus transaksi ' . tx_label((string) $tx['type'], (int) $tx['amount'], (string) $tx['description']));
}

/** Ringkasan singkat transaksi untuk detail log. */
function tx_label(string $type, int $amount, string $description): string
{
    return '[' . ($type === 'masuk' ? 'masuk' : 'keluar') . '] ' . rupiah($amount) . ' — ' . $description;
}
