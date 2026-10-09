<?php
defined('APP_ROOT') || exit('Akses langsung tidak diizinkan.');
require_once __DIR__ . '/auth.php';
$header_user = auth_user();

// Deteksi halaman aktif untuk tab navigasi.
$current_script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$current_file = basename($current_script);
$in_admin_area = str_contains($current_script, '/admin/');
$is_admin = $header_user !== null && ($header_user['role'] ?? 'user') === 'admin';
$tab_admin_home = $in_admin_area && $current_file === 'index.php';
$tab_admin_user = $in_admin_area && in_array($current_file, ['user.php', 'user-hapus.php'], true);
$tab_admin_login = $in_admin_area && $current_file === 'login-gagal.php';
$tab_admin_ai = $in_admin_area && in_array($current_file, ['ai.php', 'ai-provider.php', 'ai-model.php', 'ai-hapus.php', 'ai-kesehatan.php'], true);
$tab_admin_usage = $in_admin_area && $current_file === 'ai-usage.php';
$tab_admin_kurs = $in_admin_area && $current_file === 'ai-kurs.php';
$tab_user_home = !$in_admin_area && $current_file === 'dashboard.php';
$tab_user_log = !$in_admin_area && $current_file === 'aktivitas.php';
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
                    <?php if ($is_admin): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Admin</a>
                    <?php endif; ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Masuk</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE) ?>/register.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Daftar</a>
                <?php endif; ?>
            </div>
        </nav>
        <?php if ($header_user !== null): ?>
        <div class="avatar-menu">
            <button class="avatar-btn" type="button" data-avatar-toggle aria-expanded="false" aria-haspopup="true" aria-label="Menu akun">
                <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
            </button>
            <div class="avatar-dropdown">
                <a href="<?= e(APP_BASE) ?>/pengaturan.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Pengaturan Akun</a>
                <hr class="dropdown-divider">
                <form method="post" action="<?= e(APP_BASE) ?>/logout.php">
                    <?= csrf_field() ?>
                    <button type="submit"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Keluar</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>
<?php if ($header_user !== null): ?>
<nav class="tabbar" aria-label="Navigasi aplikasi">
    <div class="wrap tabbar-inner">
        <?php if ($is_admin && $in_admin_area): ?>
            <a class="tab<?= $tab_admin_home ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/admin/">Home Admin</a>
            <a class="tab<?= $tab_admin_user ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/admin/user.php">User</a>
            <a class="tab<?= $tab_admin_login ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/admin/login-gagal.php"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Percobaan Masuk</a>
            <a class="tab<?= $tab_admin_ai ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/admin/ai.php">Pool AI</a>
            <a class="tab<?= $tab_admin_usage ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/admin/ai-usage.php">Usage</a>
            <a class="tab<?= $tab_admin_kurs ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/admin/ai-kurs.php">Kurs</a>
        <?php else: ?>
            <a class="tab<?= $tab_user_home ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/dashboard.php">Home</a>
            <a class="tab<?= $tab_user_log ? ' is-active' : '' ?>" href="<?= e(APP_BASE) ?>/aktivitas.php">Log Aktivitas</a>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>
<div class="wrap flash-slot"><?php flash_render(); ?></div>
