<?php
/** Modul Log Aktivitas (FR-032/033) — hanya menambah data, tidak pernah mengubah/menghapus. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pagination.php';

/** Catat satu entri log aktivitas workspace. */
function log_write(int $wsId, ?int $actorId, string $actorUsername, string $action, string $objectType, ?int $objectId, string $detail): void
{
    db()->prepare('INSERT INTO activity_logs (workspace_id, actor_id, actor_username, action, object_type, object_id, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$wsId, $actorId, $actorUsername, $action, $objectType, $objectId, $detail, date('Y-m-d H:i:s')]);
}

/** Jumlah entri log sebuah workspace. */
function log_count(int $wsId): int
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM activity_logs WHERE workspace_id = ?');
    $st->execute([$wsId]);
    return (int) ($st->fetch()['c'] ?? 0);
}

/** Daftar entri log sebuah workspace — urutan menurun, berhalaman (FR-033). */
function log_list(int $wsId, int $limit = PER_PAGE, int $offset = 0): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $st = db()->prepare('SELECT * FROM activity_logs WHERE workspace_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
    $st->execute([$wsId]);
    return $st->fetchAll();
}

/** Catat satu entri log aktivitas tingkat akun (login, buat/hapus workspace). */
function user_log_write(int $userId, string $action, string $objectType, string $detail): void
{
    db()->prepare('INSERT INTO user_activity_logs (user_id, action, object_type, detail, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $action, $objectType, $detail, date('Y-m-d H:i:s')]);
}

/** Jumlah entri log aktivitas sebuah akun. */
function user_log_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM user_activity_logs WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) ($st->fetch()['c'] ?? 0);
}

/** Daftar entri log aktivitas sebuah akun — urutan menurun, berhalaman (FR-055). */
function user_log_list(int $userId, int $limit = PER_PAGE, int $offset = 0): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $st = db()->prepare('SELECT * FROM user_activity_logs WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
    $st->execute([$userId]);
    return $st->fetchAll();
}

/** Label & kelas badge untuk jenis aksi log. */
function log_action_badge(string $action): array
{
    switch ($action) {
        case 'tambah':
            return ['Tambah', 'badge-yellow'];
        case 'ubah':
            return ['Ubah', 'badge-orange'];
        case 'hapus':
            return ['Hapus', 'badge-orange'];
        case 'anggota-masuk':
            return ['Bergabung', 'badge-yellow'];
        case 'anggota-keluar':
            return ['Keluar', 'badge-orange'];
        case 'masuk':
            return ['Login', 'badge-yellow'];
        default:
            return [$action, 'badge-yellow'];
    }
}

/** Label objek log. */
function log_object_label(string $objectType): string
{
    switch ($objectType) {
        case 'transaksi':
            return 'Transaksi';
        case 'keanggotaan':
            return 'Keanggotaan';
        case 'rekening':
            return 'Rekening';
        case 'workspace':
            return 'Workspace';
        case 'akun':
            return 'Akun';
        default:
            return $objectType;
    }
}
