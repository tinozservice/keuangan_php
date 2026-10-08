<?php
/* Pencatat Keuangan — dashboard sederhana (halaman terproteksi pertama). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';

$user = auth_require_login();

$page_title = 'Dashboard — Pencatat Keuangan';
$page_desc = 'Ringkasan akun Anda.';
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
                    <h1>Halo, <?= e((string) $user['name']) ?></h1>
                    <p class="lead">Akun Anda aktif. Modul workspace &amp; pencatatan transaksi menyusul pada tahap berikutnya.</p>
                </div>
                <ul class="meta-list">
                    <li><span>Email</span><span class="mono"><?= e((string) $user['email']) ?></span></li>
                    <li><span>Status</span><span class="badge badge-yellow">Terverifikasi</span></li>
                    <li><span>Peran</span><span class="badge <?= (($user['role'] ?? 'user') === 'admin') ? 'badge-orange' : 'badge-yellow' ?>"><?= e((($user['role'] ?? 'user') === 'admin') ? 'Admin' : 'Pengguna') ?></span></li>
                    <li><span>Terdaftar</span><span class="mono"><?= e(date('d M Y', strtotime((string) $user['created_at']))) ?></span></li>
                </ul>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
