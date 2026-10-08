<?php
/** Modul Workspace & undangan kolaborator (Alur 2 PRD). */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/log.php';

function ws_get(int $id): ?array
{
    $st = db()->prepare('SELECT w.*, u.username AS owner_username, u.name AS owner_name FROM workspaces w JOIN users u ON u.id = w.owner_id WHERE w.id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function ws_role_for_user(int $wsId, int $userId): ?string
{
    $st = db()->prepare('SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ? LIMIT 1');
    $st->execute([$wsId, $userId]);
    $row = $st->fetch();
    return $row === false ? null : (string) $row['role'];
}

function ws_list_owned(int $userId): array
{
    $st = db()->prepare('SELECT * FROM workspaces WHERE owner_id = ? ORDER BY created_at DESC, id DESC');
    $st->execute([$userId]);
    return $st->fetchAll();
}

function ws_list_joined(int $userId): array
{
    $st = db()->prepare("SELECT w.id, w.name, w.created_at, u.username AS owner_username FROM workspaces w JOIN workspace_members m ON m.workspace_id = w.id JOIN users u ON u.id = w.owner_id WHERE m.user_id = ? AND m.role = 'collaborator' ORDER BY w.created_at DESC, w.id DESC");
    $st->execute([$userId]);
    return $st->fetchAll();
}

function ws_create(int $ownerId, string $name): int
{
    $pdo = db();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO workspaces (owner_id, name, created_at) VALUES (?, ?, ?)')->execute([$ownerId, $name, $now]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role, joined_at) VALUES (?, ?, 'owner', ?)")->execute([$id, $ownerId, $now]);
    return $id;
}

function ws_rename(int $wsId, string $name): void
{
    db()->prepare('UPDATE workspaces SET name = ? WHERE id = ?')->execute([$name, $wsId]);
}

function ws_delete(int $wsId): void
{
    db()->prepare('DELETE FROM workspaces WHERE id = ?')->execute([$wsId]);
}

function ws_leave(int $wsId, int $userId): void
{
    $st = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    $username = $row === false ? '' : (string) $row['username'];

    db()->prepare("DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND role = 'collaborator'")->execute([$wsId, $userId]);
    log_write($wsId, $userId, $username, 'anggota-keluar', 'keanggotaan', $userId, '@' . $username . ' keluar dari workspace');
}

function ws_members(int $wsId): array
{
    $st = db()->prepare("SELECT m.role, m.joined_at, u.id, u.username, u.name FROM workspace_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? ORDER BY CASE WHEN m.role = 'owner' THEN 0 ELSE 1 END, u.username ASC");
    $st->execute([$wsId]);
    return $st->fetchAll();
}

function ws_pending_invites(int $wsId): array
{
    $st = db()->prepare("SELECT i.id, i.created_at, u.username, u.name FROM workspace_invitations i JOIN users u ON u.id = i.invitee_id WHERE i.workspace_id = ? AND i.status = 'pending' ORDER BY i.created_at ASC");
    $st->execute([$wsId]);
    return $st->fetchAll();
}

function ws_invites_for_user(int $userId): array
{
    $st = db()->prepare("SELECT i.id, i.created_at, w.id AS workspace_id, w.name AS workspace_name, u.username AS inviter_username, u.name AS inviter_name FROM workspace_invitations i JOIN workspaces w ON w.id = i.workspace_id JOIN users u ON u.id = i.inviter_id WHERE i.invitee_id = ? AND i.status = 'pending' ORDER BY i.created_at ASC");
    $st->execute([$userId]);
    return $st->fetchAll();
}

/** Kirim undangan; kembalikan [false, pesan] atau [true, pesan]. */
function ws_invite(int $wsId, int $inviterId, string $target): array
{
    $target = trim($target);
    if ($target === '') {
        return [false, 'Masukkan email atau username calon kolaborator.'];
    }

    $invitee = auth_find_user_by_email_or_username($target);
    if ($invitee === null) {
        return [false, 'Akun tidak ditemukan: email atau username belum terdaftar.'];
    }
    if ((int) $invitee['is_verified'] !== 1) {
        return [false, 'Akun terdaftar tetapi belum terverifikasi — minta pemiliknya memverifikasi email terlebih dahulu.'];
    }

    $inviteeId = (int) $invitee['id'];
    if ($inviteeId === $inviterId) {
        return [false, 'Tidak dapat mengundang diri sendiri.'];
    }
    if (ws_role_for_user($wsId, $inviteeId) !== null) {
        return [false, 'Pengguna tersebut sudah menjadi anggota workspace ini.'];
    }

    $pdo = db();
    $st = $pdo->prepare('SELECT id, status FROM workspace_invitations WHERE workspace_id = ? AND invitee_id = ? LIMIT 1');
    $st->execute([$wsId, $inviteeId]);
    $existing = $st->fetch();

    if ($existing !== false && $existing['status'] === 'pending') {
        return [false, 'Undangan untuk pengguna tersebut masih menunggu jawaban.'];
    }

    $now = date('Y-m-d H:i:s');
    if ($existing !== false) {
        $pdo->prepare("UPDATE workspace_invitations SET status = 'pending', inviter_id = ?, created_at = ?, responded_at = NULL WHERE id = ?")
            ->execute([$inviterId, $now, (int) $existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO workspace_invitations (workspace_id, inviter_id, invitee_id, status, created_at) VALUES (?, ?, ?, 'pending', ?)")
            ->execute([$wsId, $inviterId, $inviteeId, $now]);
    }

    // Notifikasi email (asumsi PRD Alur 2: undangan dikirim via SMTP Brevo).
    $ws = ws_get($wsId);
    $inviter = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $inviter->execute([$inviterId]);
    $inviterRow = $inviter->fetch();
    $emailSent = mail_send_invitation(
        (string) $invitee['email'],
        (string) $invitee['name'],
        (string) ($ws['name'] ?? 'workspace'),
        (string) ($inviterRow['username'] ?? '')
    );

    return [true, $emailSent
        ? 'Undangan terkirim ke ' . (string) $invitee['username'] . ' (email pemberitahuan dikirim).'
        : 'Undangan dibuat di aplikasi, tetapi email pemberitahuan gagal dikirim — periksa konfigurasi SMTP.'];
}

/** Terima atau tolak undangan; kembalikan [ok, pesan]. */
function ws_respond_invitation(int $invitationId, int $userId, string $action): array
{
    $st = db()->prepare('SELECT * FROM workspace_invitations WHERE id = ? LIMIT 1');
    $st->execute([$invitationId]);
    $invitation = $st->fetch();

    if ($invitation === false || (int) $invitation['invitee_id'] !== $userId || $invitation['status'] !== 'pending') {
        return [false, 'Undangan tidak ditemukan atau sudah dijawab.'];
    }

    $pdo = db();
    $now = date('Y-m-d H:i:s');

    if ($action === 'terima') {
        if (ws_role_for_user((int) $invitation['workspace_id'], $userId) === null) {
            $pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role, joined_at) VALUES (?, ?, 'collaborator', ?)")
                ->execute([(int) $invitation['workspace_id'], $userId, $now]);
        }
        $pdo->prepare("UPDATE workspace_invitations SET status = 'accepted', responded_at = ? WHERE id = ?")->execute([$now, $invitationId]);

        $stUser = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $stUser->execute([$userId]);
        $rowUser = $stUser->fetch();
        $username = $rowUser === false ? '' : (string) $rowUser['username'];
        log_write((int) $invitation['workspace_id'], $userId, $username, 'anggota-masuk', 'keanggotaan', $userId, '@' . $username . ' bergabung sebagai kolaborator');

        return [true, 'Undangan diterima. Workspace kini muncul di daftar kolaborasi Anda.'];
    }

    $pdo->prepare("UPDATE workspace_invitations SET status = 'declined', responded_at = ? WHERE id = ?")->execute([$now, $invitationId]);
    return [true, 'Undangan ditolak.'];
}
