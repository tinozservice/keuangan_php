<?php
/* Pencatat Keuangan — callback OAuth Google (FR-005). URL ini yang didaftarkan di Google Console. */
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

// Dibatalkan pengguna / error dari Google.
if (!empty($_GET['error'])) {
    flash_set('error', 'Login Google dibatalkan atau gagal (' . (string) $_GET['error'] . ').');
    redirect('/login.php');
}

$state = (string) ($_GET['state'] ?? '');
$code = (string) ($_GET['code'] ?? '');
$expected = (string) ($_SESSION['google_oauth_state'] ?? '');
$startedAt = (int) ($_SESSION['google_oauth_state_at'] ?? 0);
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_state_at']);

if ($expected === '' || $state === '' || !hash_equals($expected, $state) || time() - $startedAt > 600) {
    flash_set('error', 'Sesi login Google tidak valid atau kedaluwarsa. Silakan coba lagi.');
    redirect('/login.php');
}

if ($code === '') {
    flash_set('error', 'Kode OAuth Google tidak ditemukan.');
    redirect('/login.php');
}

$accessToken = google_exchange_code($code);
if ($accessToken === '') {
    flash_set('error', 'Gagal menukar kode Google. Silakan coba lagi.');
    redirect('/login.php');
}

$info = google_userinfo($accessToken);
if ($info === null) {
    flash_set('error', 'Gagal mengambil profil Google. Silakan coba lagi.');
    redirect('/login.php');
}

[$ok, $message] = google_login_or_register($info);
if (!$ok) {
    flash_set('error', $message);
    redirect('/login.php');
}

flash_set('ok', $message);
redirect('/dashboard.php');
