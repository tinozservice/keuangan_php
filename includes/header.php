<?php defined('APP_ROOT') || exit('Akses langsung tidak diizinkan.'); ?>
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
                <li><a href="#fitur">Fitur</a></li>
                <li><a href="#cara-kerja">Cara kerja</a></li>
                <li><a href="#mulai">Mulai</a></li>
            </ul>
            <div class="header-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Masuk</a>
                <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE) ?>/register.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Daftar</a>
            </div>
        </nav>
    </div>
</header>
