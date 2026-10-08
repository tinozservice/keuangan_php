<?php
/* Pencatat Keuangan — pendaftaran akun manual (Alur 1 PRD; field: username, email, password). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/mail.php';

if (auth_user() !== null) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    $error = null;
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        $error = 'Username harus 3–30 karakter dan hanya berisi huruf kecil, angka, atau garis bawah.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Kata sandi minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Ulangi kata sandi tidak cocok.';
    } elseif (auth_username_exists($username)) {
        $error = 'Username sudah dipakai.';
    } elseif (auth_find_user_by_email($email) !== null) {
        $error = 'Email sudah terdaftar. Coba masuk.';
    }

    if ($error !== null) {
        flash_set('error', $error);
        redirect('/register.php');
    }

    $userId = auth_create_user($username, $email, $password);
    $code = auth_issue_otp($userId);
    $sent = mail_send_otp($email, $username, $code);

    $_SESSION['pending_email'] = $email;
    $_SESSION['otp_sent_at'] = time();

    if ($sent) {
        unset($_SESSION['dev_otp']);
        flash_set('ok', 'Kode verifikasi telah dikirim ke email Anda.');
    } else {
        $_SESSION['dev_otp'] = $code;
        flash_set('info', 'SMTP belum aktif — mode pengembangan: kode OTP ditampilkan di halaman verifikasi.');
    }
    redirect('/verify.php');
}

$page_title = 'Daftar — Pencatat Keuangan';
$page_desc = 'Buat akun Pencatat Keuangan untuk mulai mencatat keuangan.';
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
                    <h1>Buat akun</h1>
                    <p class="lead">Pilih username, lalu verifikasi lewat kode yang dikirim ke email Anda.</p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/register.php" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="username">Username</label>
                        <input class="input" type="text" id="username" name="username" minlength="3" maxlength="30" pattern="[a-z0-9_]{3,30}" autocomplete="username" required value="">
                        <span class="field-hint">Huruf kecil, angka, dan garis bawah (3–30 karakter).</span>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" required value="">
                    </div>
                    <div class="field">
                        <label for="password">Kata sandi</label>
                        <input class="input" type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="password_confirm">Ulangi kata sandi</label>
                        <input class="input" type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Daftar</button>
                </form>
                <p class="form-note">Sudah punya akun? <a href="<?= e(APP_BASE) ?>/login.php">Masuk</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
