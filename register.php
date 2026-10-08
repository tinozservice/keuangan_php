<?php
/* Pencatat Keuangan — pendaftaran akun manual (Alur 1 PRD). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/mail.php';

if (auth_user() !== null) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    $error = null;
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        $error = 'Nama harus 2–80 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Kata sandi minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Ulangi kata sandi tidak cocok.';
    } elseif (auth_find_user_by_email($email) !== null) {
        $error = 'Email sudah terdaftar. Coba masuk.';
    }

    if ($error !== null) {
        flash_set('error', $error);
        redirect('/register.php');
    }

    $userId = auth_create_user($name, $email, $password);
    $code = auth_issue_otp($userId);
    $sent = mail_send_otp($email, $name, $code);

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
                    <p class="lead">Isi data di bawah, lalu masukkan kode verifikasi yang dikirim ke email Anda.</p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/register.php" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="name">Nama</label>
                        <input class="input" type="text" id="name" name="name" maxlength="80" required value="">
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
