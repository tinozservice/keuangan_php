<?php
/* Pencatat Keuangan — panel admin: konfirmasi & hapus akun pengguna (FR-038). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';

$admin = auth_require_admin();
$adminId = (int) $admin['id'];

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([$id]);
$target = $st->fetch();

if ($target === false) {
    flash_set('error', 'Pengguna tidak ditemukan.');
    redirect('/admin/user.php');
}
if ($id === $adminId) {
    flash_set('error', 'Tidak dapat menghapus akun sendiri.');
    redirect('/admin/user.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    flash_set('ok', 'Akun @' . (string) $target['username'] . ' dihapus.');
    redirect('/admin/user.php');
}

$page_title = 'Hapus Pengguna — Panel Admin';
$page_desc = 'Konfirmasi penghapusan akun pengguna.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require dirname(__DIR__) . '/includes/head.php'; ?>
</head>
<body>
    <?php require dirname(__DIR__) . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap auth-wrap">
            <section class="card auth-card">
                <div>
                    <h1>Hapus akun?</h1>
                    <p class="lead">
                        Akun <strong>@<?= e((string) $target['username']) ?></strong> (<?= e((string) $target['email']) ?> — <?= e((string) $target['name']) ?>)
                        akan dihapus permanen, termasuk seluruh workspace miliknya, keanggotaan, dan undangan terkait.
                        Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                    </p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/admin/user-hapus.php?id=<?= (int) $target['id'] ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i> Ya, hapus akun ini</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/admin/user.php">Batal, kembali ke daftar pengguna</a></p>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
