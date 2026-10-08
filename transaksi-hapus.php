<?php
/* Pencatat Keuangan — konfirmasi & hapus transaksi (perubahan tercatat di log FR-032). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';
require __DIR__ . '/includes/transaksi.php';

$user = auth_require_login();
$uid = (int) $user['id'];
$username = (string) $user['username'];

$txId = (int) ($_GET['id'] ?? 0);
$tx = $txId > 0 ? tx_get($txId) : null;
if ($tx === null) {
    flash_set('error', 'Transaksi tidak ditemukan.');
    redirect('/dashboard.php');
}
$wsId = (int) $tx['workspace_id'];
$ws = ws_get($wsId);
$role = $ws !== null ? ws_role_for_user($wsId, $uid) : null;
if ($ws === null || $role === null) {
    flash_set('error', 'Anda tidak memiliki akses ke transaksi ini.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    tx_delete($wsId, $uid, $username, $txId);
    flash_set('ok', 'Transaksi dihapus.');
    redirect('/transaksi.php?id=' . $wsId);
}

$page_title = 'Hapus Transaksi — ' . (string) $ws['name'];
$page_desc = 'Konfirmasi penghapusan transaksi.';
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
                    <h1>Hapus transaksi?</h1>
                    <p class="lead">
                        Transaksi <strong>[<?= $tx['type'] === 'masuk' ? 'masuk' : 'keluar' ?>] <?= e(rupiah((int) $tx['amount'])) ?> — <?= e((string) $tx['description']) ?></strong>
                        (<?= e(date('d M Y', strtotime((string) $tx['tx_date']))) ?>) akan dihapus dari workspace <strong><?= e((string) $ws['name']) ?></strong>.
                        Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                    </p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/transaksi-hapus.php?id=<?= (int) $tx['id'] ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i> Ya, hapus transaksi</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/transaksi.php?id=<?= (int) $ws['id'] ?>">Batal, kembali ke daftar transaksi</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
