<?php
/* Pencatat Keuangan — mulai login/daftar dengan Google (FR-005, native tanpa vendor). */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google.php';

if (auth_user() !== null) {
    redirect('/dashboard.php');
}

if (!google_oauth_enabled()) {
    flash_set('error', 'Login Google belum dikonfigurasi (GOOGLE_CLIENT_ID/GOOGLE_CLIENT_SECRET/GOOGLE_REDIRECT_URI di .env).');
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    redirect('/login.php');
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_state_at'] = time();

header('Location: ' . google_auth_url($state));
exit;
