<?php
/** Modul Rekening (akun keuangan) per workspace — CRUD + log aktivitas. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';

function rek_list(int $wsId): array
{
    $st = db()->prepare('SELECT * FROM accounts WHERE workspace_id = ? ORDER BY name COLLATE NOCASE ASC');
    $st->execute([$wsId]);
    return $st->fetchAll();
}

function rek_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM accounts WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Tambah rekening; kembalikan [ok, pesan]. */
function rek_add(int $wsId, int $actorId, string $actorUsername, string $name): array
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 60) {
        return [false, 'Nama rekening harus 1–60 karakter.'];
    }

    $st = db()->prepare('SELECT 1 FROM accounts WHERE workspace_id = ? AND name = ? COLLATE NOCASE LIMIT 1');
    $st->execute([$wsId, $name]);
    if ($st->fetch() !== false) {
        return [false, 'Rekening dengan nama tersebut sudah ada.'];
    }

    db()->prepare('INSERT INTO accounts (workspace_id, name, created_at) VALUES (?, ?, ?)')->execute([$wsId, $name, date('Y-m-d H:i:s')]);
    $id = (int) db()->lastInsertId();
    log_write($wsId, $actorId, $actorUsername, 'tambah', 'rekening', $id, 'Menambah rekening "' . $name . '"');
    return [true, 'Rekening "' . $name . '" ditambahkan.'];
}

/** Ubah nama rekening; kembalikan [ok, pesan]. */
function rek_update(int $wsId, int $actorId, string $actorUsername, int $id, string $name): array
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 60) {
        return [false, 'Nama rekening harus 1–60 karakter.'];
    }

    $st = db()->prepare('SELECT 1 FROM accounts WHERE workspace_id = ? AND name = ? COLLATE NOCASE AND id <> ? LIMIT 1');
    $st->execute([$wsId, $name, $id]);
    if ($st->fetch() !== false) {
        return [false, 'Rekening dengan nama tersebut sudah ada.'];
    }

    db()->prepare('UPDATE accounts SET name = ? WHERE id = ? AND workspace_id = ?')->execute([$name, $id, $wsId]);
    log_write($wsId, $actorId, $actorUsername, 'ubah', 'rekening', $id, 'Mengubah nama rekening menjadi "' . $name . '"');
    return [true, 'Nama rekening diperbarui.'];
}

/** Hapus rekening (transaksi terkait tetap tersimpan — FK ON DELETE SET NULL). */
function rek_delete(int $wsId, int $actorId, string $actorUsername, int $id): void
{
    $acc = rek_get($id);
    if ($acc === null || (int) $acc['workspace_id'] !== $wsId) {
        return;
    }

    db()->prepare('DELETE FROM accounts WHERE id = ? AND workspace_id = ?')->execute([$id, $wsId]);
    log_write($wsId, $actorId, $actorUsername, 'hapus', 'rekening', $id, 'Menghapus rekening "' . (string) $acc['name'] . '"');
}

/** Jumlah transaksi yang memakai rekening tertentu. */
function rek_tx_count(int $id): int
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM transactions WHERE account_id = ?');
    $st->execute([$id]);
    return (int) ($st->fetch()['c'] ?? 0);
}

function rek_exists(int $wsId, int $id): bool
{
    $st = db()->prepare('SELECT 1 FROM accounts WHERE id = ? AND workspace_id = ? LIMIT 1');
    $st->execute([$id, $wsId]);
    return $st->fetch() !== false;
}

function rek_name(int $id): string
{
    $st = db()->prepare('SELECT name FROM accounts WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? '' : (string) $row['name'];
}
