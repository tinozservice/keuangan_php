<?php
/* Pencatat Keuangan — konfirmasi & hapus rekening (CRUD Rekening). */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workspace.php';
require_once __DIR__ . '/includes/rekening.php';

$user = auth_require_login();
$uid = (int) $user['id'];
$username = (string) $user['username'];

$accId = (int) ($_GET['id'] ?? 0);
$acc = $accId > 0 ? rek_get($accId) : null;
if ($acc === null) {
    flash_set('error', 'Rekening tidak ditemukan.');
    redirect('/dashboard.php');
}
$wsId = (int) $acc['workspace_id'];
$ws = ws_get($wsId);
$role = $ws !== null ? ws_role_for_user($wsId, $uid) : null;
if ($ws === null || $role === null) {
    flash_set('error', 'Anda tidak memiliki akses ke rekening ini.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    rek_delete($wsId, $uid, $username, $accId);
    flash_set('ok', 'Rekening "' . (string) $acc['name'] . '" dihapus.');
    redirect('/workspace.php?id=' . $wsId);
}

$txCount = rek_tx_count($accId);

$page_title = 'Hapus Rekening — ' . (string) $ws['name'];
$page_desc = 'Konfirmasi penghapusan rekening.';
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
                    <h1>Hapus rekening?</h1>
                    <p class="lead">
                        Rekening <strong><?= e((string) $acc['name']) ?></strong> akan dihapus dari workspace <strong><?= e((string) $ws['name']) ?></strong>.
                        <?php if ($txCount > 0): ?>
                            <br><?= $txCount ?> transaksi yang memakai rekening ini tetap tersimpan — hanya kehilangan penanda rekening.
                        <?php endif; ?>
                        <br>Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                    </p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/rekening-hapus.php?id=<?= (int) $acc['id'] ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i> Ya, hapus rekening</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">Batal, kembali ke workspace</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
