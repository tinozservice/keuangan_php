<?php
/** Modul Transaksi — input manual & pengelolaan (FR-021; perubahan tercatat di log FR-032). */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/rekening.php';

/** Normalisasi filter pencarian manual (FR-030): dari/sampai (Y-m-d) + kata kunci q + rekening. */
function tx_filter_from_query(array $query): array
{
    $valid = static function (string $d): bool {
        $parsed = DateTime::createFromFormat('Y-m-d', $d);
        return $parsed !== false && $parsed->format('Y-m-d') === $d;
    };

    $dari = trim((string) ($query['dari'] ?? ''));
    $sampai = trim((string) ($query['sampai'] ?? ''));
    $q = trim((string) ($query['q'] ?? ''));
    $rekening = (int) ($query['rekening'] ?? 0);

    if ($dari !== '' && !$valid($dari)) {
        $dari = '';
    }
    if ($sampai !== '' && !$valid($sampai)) {
        $sampai = '';
    }
    if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    if (mb_strlen($q) > 100) {
        $q = mb_substr($q, 0, 100);
    }

    return ['dari' => $dari, 'sampai' => $sampai, 'q' => $q, 'rekening' => $rekening > 0 ? $rekening : 0];
}

/** True bila minimal satu filter pencarian aktif. */
function tx_filter_active(array $filter): bool
{
    return (string) ($filter['dari'] ?? '') !== ''
        || (string) ($filter['sampai'] ?? '') !== ''
        || (string) ($filter['q'] ?? '') !== ''
        || (int) ($filter['rekening'] ?? 0) > 0;
}

/** Klausa WHERE + parameter terikat untuk filter transaksi (prepared statement). */
function tx_filter_sql(array $filter, int $wsId): array
{
    $where = 't.workspace_id = ?';
    $params = [$wsId];

    $dari = (string) ($filter['dari'] ?? '');
    $sampai = (string) ($filter['sampai'] ?? '');
    $q = (string) ($filter['q'] ?? '');
    $rekening = (int) ($filter['rekening'] ?? 0);

    if ($dari !== '') {
        $where .= ' AND t.tx_date >= ?';
        $params[] = $dari;
    }
    if ($sampai !== '') {
        $where .= ' AND t.tx_date <= ?';
        $params[] = $sampai;
    }
    if ($q !== '') {
        $where .= " AND t.description LIKE ? ESCAPE '\\'";
        $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    }
    if ($rekening > 0) {
        $where .= ' AND t.account_id = ?';
        $params[] = $rekening;
    }

    return [$where, $params];
}

/** Label filter aktif untuk ditampilkan di UI. */
function tx_filter_label(array $filter): string
{
    $parts = [];
    $dari = (string) ($filter['dari'] ?? '');
    $sampai = (string) ($filter['sampai'] ?? '');
    if ($dari !== '' || $sampai !== '') {
        $parts[] = 'tanggal ' . ($dari !== '' ? date('d M Y', strtotime($dari)) : 'awal')
            . ' – ' . ($sampai !== '' ? date('d M Y', strtotime($sampai)) : 'sekarang');
    }
    if ((string) ($filter['q'] ?? '') !== '') {
        $parts[] = 'kata kunci "' . (string) $filter['q'] . '"';
    }
    $rekening = (int) ($filter['rekening'] ?? 0);
    if ($rekening > 0) {
        $name = rek_name($rekening);
        if ($name !== '') {
            $parts[] = 'rekening ' . $name;
        }
    }
    return implode(' · ', $parts);
}

/** Filter sebagai parameter query string (tanpa nilai kosong). */
function tx_filter_query(array $filter): array
{
    $out = [];
    foreach (['dari', 'sampai', 'q'] as $key) {
        $value = (string) ($filter[$key] ?? '');
        if ($value !== '') {
            $out[$key] = $value;
        }
    }
    $rekening = (int) ($filter['rekening'] ?? 0);
    if ($rekening > 0) {
        $out['rekening'] = $rekening;
    }
    return $out;
}

function tx_list(int $wsId, int $limit = PER_PAGE, int $offset = 0, array $filter = []): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    [$where, $params] = tx_filter_sql($filter, $wsId);
    $st = db()->prepare('SELECT t.*, a.name AS account_name FROM transactions t LEFT JOIN accounts a ON a.id = t.account_id WHERE ' . $where . ' ORDER BY t.tx_date DESC, t.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
    $st->execute($params);
    return $st->fetchAll();
}

function tx_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM transactions WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function tx_count(int $wsId, array $filter = []): int
{
    [$where, $params] = tx_filter_sql($filter, $wsId);
    $st = db()->prepare('SELECT COUNT(*) AS c FROM transactions t WHERE ' . $where);
    $st->execute($params);
    return (int) ($st->fetch()['c'] ?? 0);
}

function tx_totals(int $wsId, array $filter = []): array
{
    [$where, $params] = tx_filter_sql($filter, $wsId);
    $st = db()->prepare("SELECT
        COALESCE(SUM(CASE WHEN t.type = 'masuk' THEN t.amount ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN t.type = 'keluar' THEN t.amount ELSE 0 END), 0) AS total_keluar
        FROM transactions t WHERE " . $where);
    $st->execute($params);
    $row = $st->fetch();
    $masuk = (int) ($row['total_masuk'] ?? 0);
    $keluar = (int) ($row['total_keluar'] ?? 0);
    return ['masuk' => $masuk, 'keluar' => $keluar, 'selisih' => $masuk - $keluar];
}

/** Total masuk/keluar per rekening (termasuk baris "tanpa rekening") — urut nama, tanpa rekening di akhir. */
function tx_totals_by_account(int $wsId, array $filter = []): array
{
    [$where, $params] = tx_filter_sql($filter, $wsId);
    $st = db()->prepare("SELECT
        t.account_id,
        a.name AS account_name,
        COALESCE(SUM(CASE WHEN t.type = 'masuk' THEN t.amount ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN t.type = 'keluar' THEN t.amount ELSE 0 END), 0) AS total_keluar,
        COUNT(*) AS jumlah
        FROM transactions t
        LEFT JOIN accounts a ON a.id = t.account_id
        WHERE " . $where . "
        GROUP BY t.account_id, a.name
        ORDER BY (t.account_id IS NULL) ASC, a.name COLLATE NOCASE ASC");
    $st->execute($params);

    $rows = [];
    foreach ($st->fetchAll() as $row) {
        $masuk = (int) $row['total_masuk'];
        $keluar = (int) $row['total_keluar'];
        $rows[] = [
            'name' => $row['account_name'] === null ? null : (string) $row['account_name'],
            'masuk' => $masuk,
            'keluar' => $keluar,
            'selisih' => $masuk - $keluar,
            'jumlah' => (int) $row['jumlah'],
        ];
    }
    return $rows;
}

/** Semua transaksi workspace (tanpa pagination) untuk ekspor — urut kronologis naik. */
function tx_all(int $wsId, array $filter = []): array
{
    [$where, $params] = tx_filter_sql($filter, $wsId);
    $st = db()->prepare('SELECT t.*, a.name AS account_name FROM transactions t LEFT JOIN accounts a ON a.id = t.account_id WHERE ' . $where . ' ORDER BY t.tx_date ASC, t.id ASC');
    $st->execute($params);
    return $st->fetchAll();
}

function tx_add(int $wsId, int $actorId, string $actorUsername, string $date, string $type, int $amount, string $description, ?int $accountId = null): int
{
    $now = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO transactions (workspace_id, created_by, account_id, tx_date, type, amount, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$wsId, $actorId, $accountId, $date, $type, $amount, $description, $now, $now]);
    $id = (int) db()->lastInsertId();
    log_write($wsId, $actorId, $actorUsername, 'tambah', 'transaksi', $id, 'Menambah transaksi ' . tx_label($type, $amount, $description) . tx_rek_suffix($accountId));
    return $id;
}

function tx_update(int $wsId, int $actorId, string $actorUsername, int $id, string $date, string $type, int $amount, string $description, ?int $accountId = null): void
{
    db()->prepare('UPDATE transactions SET account_id = ?, tx_date = ?, type = ?, amount = ?, description = ?, updated_at = ? WHERE id = ? AND workspace_id = ?')
        ->execute([$accountId, $date, $type, $amount, $description, date('Y-m-d H:i:s'), $id, $wsId]);
    log_write($wsId, $actorId, $actorUsername, 'ubah', 'transaksi', $id, 'Mengubah transaksi ' . tx_label($type, $amount, $description) . tx_rek_suffix($accountId));
}

function tx_delete(int $wsId, int $actorId, string $actorUsername, int $id): void
{
    $tx = tx_get($id);
    if ($tx === null || (int) $tx['workspace_id'] !== $wsId) {
        return;
    }
    db()->prepare('DELETE FROM transactions WHERE id = ? AND workspace_id = ?')->execute([$id, $wsId]);
    $accountId = ((int) ($tx['account_id'] ?? 0)) > 0 ? (int) $tx['account_id'] : null;
    log_write($wsId, $actorId, $actorUsername, 'hapus', 'transaksi', $id, 'Menghapus transaksi ' . tx_label((string) $tx['type'], (int) $tx['amount'], (string) $tx['description']) . tx_rek_suffix($accountId));
}

/** Ringkasan singkat transaksi untuk detail log. */
function tx_label(string $type, int $amount, string $description): string
{
    return '[' . ($type === 'masuk' ? 'masuk' : 'keluar') . '] ' . rupiah($amount) . ' — ' . $description;
}

/** Sufiks nama rekening untuk detail log (kosong bila tanpa rekening). */
function tx_rek_suffix(?int $accountId): string
{
    if ($accountId === null || $accountId <= 0) {
        return '';
    }
    $name = rek_name($accountId);
    return $name === '' ? '' : ' · rekening ' . $name;
}
