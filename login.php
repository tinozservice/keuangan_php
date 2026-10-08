<?php
/* Pencatat Keuangan — masuk dengan email & kata sandi. */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';

if (auth_user() !== null) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $user = auth_find_user_by_email($email);

    if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
        flash_set('error', 'Email atau kata sandi salah.');
        redirect('/login.php');
    }

    if ((int) $user['is_verified'] !== 1) {
        $_SESSION['pending_email'] = $email;
        flash_set('error', 'Akun belum terverifikasi. Masukkan kode OTP atau kirim ulang kode.');
        redirect('/verify.php');
    }

    auth_login_user((int) $user['id']);
    flash_set('ok', 'Selamat datang kembali, ' . (string) $user['name'] . '!');
    redirect('/dashboard.php');
}

$page_title = 'Masuk — Pencatat Keuangan';
$page_desc = 'Masuk ke akun Pencatat Keuangan Anda.';
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
                    <p class="lead">Gunakan email dan kata sandi akun Anda.</p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/login.php" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" required autocomplete="email">
                    </div>
                    <div class="field">
                        <label for="password">Kata sandi</label>
                        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Masuk</button>
                </form>
                <p class="form-note">Belum punya akun? <a href="<?= e(APP_BASE) ?>/register.php">Daftar</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
