<?php
/* Pencatat Keuangan — panel admin: beranda. */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';

$user = auth_require_admin();

$page_title = 'Panel Admin — Pencatat Keuangan';
$page_desc = 'Panel admin Pencatat Keuangan.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require dirname(__DIR__) . '/includes/head.php'; ?>
</head>
<body>
    <?php require dirname(__DIR__) . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap dash">
            <div class="dash-head">
                <div>
                    <h1>Panel Admin</h1>
                    <p class="lead">Masuk sebagai @<?= e((string) $user['username']) ?>.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/dashboard.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Dashboard</a>
            </div>

            <section class="dash-section">
                <h2>Modul tersedia</h2>
                <div class="dash-grid">
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/user.php">Kelola Pengguna</a></h3>
                        <div class="ws-meta">Daftar akun, atur status verifikasi &amp; peran, serta hapus akun dengan konfirmasi.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/user.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/login-gagal.php">Percobaan Masuk</a></h3>
                        <div class="ws-meta">Log percobaan masuk ke identifier yang belum terdaftar, lengkap dengan lokasi &amp; IP address.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/login-gagal.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/ai.php">Pool AI</a></h3>
                        <div class="ws-meta">Provider OpenAI-compatible, model fallback (kapabilitas &amp; harga token), urutan prioritas, dan pemeriksaan kesehatan massal.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/ai-usage.php">Usage &amp; Biaya</a></h3>
                        <div class="ws-meta">Pemakaian token per model, estimasi biaya USD (+Rupiah), dan riwayat panggilan model.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-usage.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/ai-kurs.php">Kurs USD→IDR</a></h3>
                        <div class="ws-meta">Nilai kurs untuk konversi estimasi biaya token AI ke Rupiah.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-kurs.php">Buka</a>
                        </div>
                    </article>
                </div>
            </section>

            <section class="dash-section">
                <h2>Menyusul</h2>
                <p class="ws-meta">Input suara/foto (FR-022–026) dan pencarian bahasa alami (FR-027–031) akan hadir pada tahap berikutnya sesuai PRD.</p>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
