<?php
/* Pencatat Keuangan — detail workspace: anggota, undangan, ubah nama, kelola (Alur 2 PRD). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';
require __DIR__ . '/includes/transaksi.php';
require_once __DIR__ . '/includes/rekening.php';
require_once __DIR__ . '/includes/log.php';

$user = auth_require_login();
$uid = (int) $user['id'];
$username = (string) $user['username'];

$id = (int) ($_GET['id'] ?? 0);
$ws = $id > 0 ? ws_get($id) : null;
if ($ws === null) {
    flash_set('error', 'Workspace tidak ditemukan.');
    redirect('/dashboard.php');
}

$role = ws_role_for_user($id, $uid);
if ($role === null) {
    flash_set('error', 'Anda tidak memiliki akses ke workspace ini.');
    redirect('/dashboard.php');
}
$isOwner = $role === 'owner';

// Aksi POST (ubah nama & undang) — hanya pemilik (FR-013, FR-017; penolakan FR-015).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'ubah-nama' && $isOwner) {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            flash_set('error', 'Nama workspace harus 1–80 karakter.');
        } else {
            ws_rename($id, $name);
            flash_set('ok', 'Nama workspace diperbarui.');
        }
        redirect('/workspace.php?id=' . $id);
    }

    if ($aksi === 'undang' && $isOwner) {
        [$ok, $message] = ws_invite($id, $uid, (string) ($_POST['target'] ?? ''));
        flash_set($ok ? 'ok' : 'error', $message);
        redirect('/workspace.php?id=' . $id);
    }

    if ($aksi === 'rekening-tambah') {
        [$ok, $message] = rek_add($id, $uid, $username, (string) ($_POST['nama_rekening'] ?? ''));
        flash_set($ok ? 'ok' : 'error', $message);
        redirect('/workspace.php?id=' . $id);
    }

    flash_set('error', 'Aksi tidak dikenali atau Anda tidak berwenang.');
    redirect('/workspace.php?id=' . $id);
}

$members = ws_members($id);
$txCount = tx_count($id);
$accounts = rek_list($id);
$logCount = log_count($id);
$pendingInvites = $isOwner ? ws_pending_invites($id) : [];

$page_title = (string) $ws['name'] . ' — Pencatat Keuangan';
$page_desc = 'Detail workspace: anggota, undangan, dan pengaturan.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap dash">
            <div class="dash-head">
                <div>
                    <h1><?= e((string) $ws['name']) ?></h1>
                    <p class="ws-meta">
                        <span class="badge <?= $isOwner ? 'badge-orange' : 'badge-yellow' ?>"><?= $isOwner ? 'Pemilik' : 'Kolaborator' ?></span>
                        <span>Pemilik @<?= e((string) $ws['owner_username']) ?></span>
                        <span>· Dibuat <?= e(date('d M Y', strtotime((string) $ws['created_at']))) ?></span>
                    </p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/dashboard.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Dashboard</a>
            </div>

            <section class="dash-section">
                <h2>Anggota (<?= count($members) ?>)</h2>
                <div class="member-list">
                    <?php foreach ($members as $m): ?>
                    <div class="member-row">
                        <span><strong>@<?= e((string) $m['username']) ?></strong> <span class="ws-meta">(<?= e((string) $m['name']) ?>)</span></span>
                        <span class="badge <?= $m['role'] === 'owner' ? 'badge-orange' : 'badge-yellow' ?>"><?= $m['role'] === 'owner' ? 'Pemilik' : 'Kolaborator' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="dash-section">
                <h2>Rekening (<?= count($accounts) ?>)</h2>
                <?php if ($accounts === []): ?>
                    <p class="ws-meta">Belum ada rekening. Tambahkan rekening (bank, e-wallet, kas) agar transaksi dapat ditandai.</p>
                <?php else: ?>
                    <div class="member-list">
                        <?php foreach ($accounts as $acc): ?>
                        <div class="member-row">
                            <span><i class="fa-solid fa-wallet" aria-hidden="true"></i> <?= e((string) $acc['name']) ?></span>
                            <div class="row-form">
                                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/rekening-ubah.php?id=<?= (int) $acc['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Ubah</a>
                                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/rekening-hapus.php?id=<?= (int) $acc['id'] ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus…</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form class="card ws-card" method="post" action="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="rekening-tambah">
                    <div class="field">
                        <label for="nama_rekening">Tambah rekening</label>
                        <input class="input" type="text" id="nama_rekening" name="nama_rekening" maxlength="60" required placeholder="cth: BCA, Dompet Digital, Kas">
                    </div>
                    <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah rekening</button>
                </form>
            </section>

            <section class="dash-section">
                <h2>Transaksi</h2>
                <div class="card invite-card">
                    <div>
                        <?php if ($txCount === 0): ?>
                            <strong>Workspace kosong</strong>
                            <div class="ws-meta">Belum ada transaksi di workspace ini.</div>
                        <?php else: ?>
                            <strong><?= $txCount ?> transaksi tercatat</strong>
                            <div class="ws-meta">Kelola daftar, tambah, ubah, atau hapus transaksi workspace.</div>
                        <?php endif; ?>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/transaksi.php?id=<?= (int) $ws['id'] ?>"><i class="fa-solid fa-receipt" aria-hidden="true"></i> <?= $txCount === 0 ? 'Tambah transaksi' : 'Kelola transaksi' ?></a>
                </div>
            </section>

            <section class="dash-section">
                <h2>Log Aktivitas</h2>
                <div class="card invite-card">
                    <div>
                        <strong><?= $logCount ?> entri aktivitas</strong>
                        <div class="ws-meta">Riwayat perubahan transaksi, rekening &amp; keanggotaan workspace.</div>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/log.php?id=<?= (int) $ws['id'] ?>"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Buka log</a>
                </div>
            </section>

            <?php if ($isOwner): ?>
            <section class="dash-section">
                <h2>Undangan menunggu</h2>
                <?php if ($pendingInvites === []): ?>
                    <p class="ws-meta">Tidak ada undangan yang menunggu jawaban.</p>
                <?php else: ?>
                    <?php foreach ($pendingInvites as $pi): ?>
                    <div class="card invite-card">
                        <div>
                            <div><strong>@<?= e((string) $pi['username']) ?></strong></div>
                            <div class="ws-meta">Dikirim <?= e(date('d M Y', strtotime((string) $pi['created_at']))) ?> · menunggu jawaban</div>
                        </div>
                        <span class="badge badge-yellow">Menunggu</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="dash-section">
                <h2>Undang kolaborator</h2>
                <form class="card ws-card" method="post" action="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="undang">
                    <div class="field">
                        <label for="target">Email atau username</label>
                        <input class="input" type="text" id="target" name="target" maxlength="120" required>
                        <span class="field-hint">Hanya akun terdaftar &amp; terverifikasi yang dapat diundang. Undangan juga dikirim lewat email.</span>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Undang</button>
                </form>
            </section>

            <section class="dash-section">
                <h2>Ubah nama workspace</h2>
                <form class="card ws-card" method="post" action="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="ubah-nama">
                    <div class="field">
                        <label for="name">Nama workspace</label>
                        <input class="input" type="text" id="name" name="name" maxlength="80" required value="<?= e((string) $ws['name']) ?>">
                    </div>
                    <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan nama</button>
                </form>
            </section>

            <section class="dash-section">
                <h2>Hapus workspace</h2>
                <div class="card invite-card">
                    <div>
                        <strong>Hapus workspace ini?</strong>
                        <div class="ws-meta">Tindakan ini memerlukan konfirmasi dan tidak dapat dibatalkan.</div>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/workspace-konfirmasi.php?id=<?= (int) $ws['id'] ?>&amp;aksi=hapus"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus…</a>
                </div>
            </section>
            <?php else: ?>
            <section class="dash-section">
                <h2>Keluar dari workspace</h2>
                <div class="card invite-card">
                    <div>
                        <strong>Keluar dari workspace ini?</strong>
                        <div class="ws-meta">Anda akan keluar sebagai kolaborator. Transaksi yang pernah Anda catat tetap tersimpan.</div>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/workspace-konfirmasi.php?id=<?= (int) $ws['id'] ?>&amp;aksi=keluar"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Keluar…</a>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
