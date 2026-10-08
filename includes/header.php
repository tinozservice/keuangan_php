<?php
defined('APP_ROOT') || exit('Akses langsung tidak diizinkan.');
require_once __DIR__ . '/auth.php';
$header_user = auth_user();
?>
<a class="skip-link" href="#main">Lewati ke konten</a>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?= e(APP_BASE) ?>/">
            <span class="logo-mark" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span>
            <span class="brand-text">
                <span class="brand-name">Pencatat<span> Keuangan</span></span>
                <span class="brand-tagline">Catat · Cari · Kelola</span>
            </span>
        </a>
        <button class="icon-btn nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="nav-menu">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
            <span class="sr-only">Buka menu</span>
        </button>
        <nav class="site-nav" id="nav-menu" aria-label="Navigasi utama">
            <ul class="nav-links">
                <li><a href="<?= e(APP_BASE) ?>/#fitur">Fitur</a></li>
                <li><a href="<?= e(APP_BASE) ?>/#cara-kerja">Cara kerja</a></li>
                <li><a href="<?= e(APP_BASE) ?>/#mulai">Mulai</a></li>
            </ul>
            <div class="header-actions">
                <?php if ($header_user !== null): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
                    <form method="post" action="<?= e(APP_BASE) ?>/logout.php">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Keluar</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Masuk</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE) ?>/register.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Daftar</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
<div class="wrap flash-slot"><?php flash_render(); ?></div>
