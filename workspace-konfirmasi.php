<?php
/* Pencatat Keuangan — konfirmasi hapus workspace (FR-014) / keluar sebagai kolaborator (FR-016). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';

$user = auth_require_login();
$uid = (int) $user['id'];

$id = (int) ($_GET['id'] ?? 0);
$aksi = (string) ($_GET['aksi'] ?? '');

$ws = $id > 0 ? ws_get($id) : null;
$role = $ws !== null ? ws_role_for_user($id, $uid) : null;
if ($ws === null || $role === null) {
    flash_set('error', 'Workspace tidak ditemukan.');
    redirect('/dashboard.php');
}
$isOwner = $role === 'owner';

if ($aksi === 'hapus' && !$isOwner) {
    flash_set('error', 'Hanya pemilik yang dapat menghapus workspace.');
    redirect('/workspace.php?id=' . $id);
}
if ($aksi === 'keluar' && $isOwner) {
    flash_set('error', 'Pemilik tidak dapat keluar dari workspace sendiri — hapus workspace bila sudah tidak diperlukan.');
    redirect('/workspace.php?id=' . $id);
}
if ($aksi !== 'hapus' && $aksi !== 'keluar') {
    redirect('/workspace.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if ($aksi === 'hapus') {
        ws_delete($id);
        flash_set('ok', 'Workspace "' . (string) $ws['name'] . '" dihapus.');
    } else {
        ws_leave($id, $uid);
        flash_set('ok', 'Anda keluar dari workspace "' . (string) $ws['name'] . '".');
    }
    redirect('/dashboard.php');
}

$page_title = ($aksi === 'hapus' ? 'Hapus' : 'Keluar dari') . ' workspace — Pencatat Keuangan';
$page_desc = 'Konfirmasi tindakan workspace.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap auth-wrap">
            <section class="card auth-card">
                <div>
                    <h1><?= $aksi === 'hapus' ? 'Hapus workspace?' : 'Keluar dari workspace?' ?></h1>
                    <p class="lead">
                        <?php if ($aksi === 'hapus'): ?>
                            Workspace <strong><?= e((string) $ws['name']) ?></strong> beserta seluruh datanya akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        <?php else: ?>
                            Anda akan keluar dari workspace <strong><?= e((string) $ws['name']) ?></strong>. Transaksi yang pernah Anda catat tetap tersimpan di workspace milik pemilik.
                        <?php endif; ?>
                    </p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/workspace-konfirmasi.php?id=<?= (int) $ws['id'] ?>&amp;aksi=<?= e($aksi) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><?= $aksi === 'hapus' ? 'Ya, hapus workspace' : 'Ya, keluar' ?></button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">Batal, kembali ke workspace</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
