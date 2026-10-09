<?php
/* Pencatat Keuangan — masuk dengan email/username & kata sandi. */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google.php';

if (auth_user() !== null) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $ident = trim((string) ($_POST['ident'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = auth_find_user_by_email_or_username($ident);

    if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
        flash_set('error', 'Email/username atau kata sandi salah.');
        redirect('/login.php');
    }

    if ((int) $user['is_verified'] !== 1) {
        $_SESSION['pending_email'] = (string) $user['email'];
        flash_set('error', 'Akun belum terverifikasi. Masukkan kode OTP atau kirim ulang kode.');
        redirect('/verify.php');
    }

    auth_login_user((int) $user['id']);
    flash_set('ok', 'Selamat datang kembali, ' . (string) $user['name'] . '!');
    redirect('/dashboard.php');
}

$page_title = 'Masuk — Pencatat Keuangan';
$page_desc = 'Masuk ke akun Pencatat Keuangan Anda dengan email atau username.';
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
                    <h1>Masuk</h1>
                    <p class="lead">Gunakan email atau username, lalu kata sandi akun Anda.</p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/login.php" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="ident">Email atau username</label>
                        <input class="input" type="text" id="ident" name="ident" required autocomplete="username" value="">
                    </div>
                    <div class="field">
                        <label for="password">Kata sandi</label>
                        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Masuk</button>
                </form>
                <?php if (google_oauth_enabled()): ?>
                <p class="form-note">atau</p>
                <div class="auth-form">
                    <a class="btn btn-ghost" href="<?= e(APP_BASE) ?>/google-login.php"><i class="fa-brands fa-google" aria-hidden="true"></i> Masuk dengan Google</a>
                </div>
                <?php endif; ?>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/lupa-password.php">Lupa kata sandi?</a></p>
                <p class="form-note">Belum punya akun? <a href="<?= e(APP_BASE) ?>/register.php">Daftar</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
