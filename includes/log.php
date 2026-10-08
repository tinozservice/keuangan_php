<?php
/** Modul Log Aktivitas (FR-032/033) — hanya menambah data, tidak pernah mengubah/menghapus. */
require_once __DIR__ . '/db.php';

/** Catat satu entri log aktivitas workspace. */
function log_write(int $wsId, ?int $actorId, string $actorUsername, string $action, string $objectType, ?int $objectId, string $detail): void
{
    db()->prepare('INSERT INTO activity_logs (workspace_id, actor_id, actor_username, action, object_type, object_id, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$wsId, $actorId, $actorUsername, $action, $objectType, $objectId, $detail, date('Y-m-d H:i:s')]);
}

/** Daftar entri log sebuah workspace (terbaru dulu) — untuk halaman log (FR-033). */
function log_list(int $wsId, int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));
    $st = db()->prepare('SELECT * FROM activity_logs WHERE workspace_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
    $st->execute([$wsId]);
    return $st->fetchAll();
}
