<?php
/* Pencatat Keuangan — verifikasi email dengan kode OTP (Alur 1 PRD). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/mail.php';

if (auth_user() !== null) {
    redirect('/dashboard.php');
}

$email = (string) ($_SESSION['pending_email'] ?? '');
if ($email === '') {
    flash_set('error', 'Belum ada pendaftaran yang menunggu verifikasi.');
    redirect('/register.php');
}

$user = auth_find_user_by_email($email);
if ($user === null) {
    unset($_SESSION['pending_email'], $_SESSION['dev_otp'], $_SESSION['otp_sent_at']);
    flash_set('error', 'Data pendaftaran tidak ditemukan. Silakan daftar ulang.');
    redirect('/register.php');
}
if ((int) $user['is_verified'] === 1) {
    unset($_SESSION['pending_email'], $_SESSION['dev_otp'], $_SESSION['otp_sent_at']);
    flash_set('ok', 'Akun sudah terverifikasi. Silakan masuk.');
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($action === 'resend') {
        $last = (int) ($_SESSION['otp_sent_at'] ?? 0);
        if (time() - $last < 60) {
            flash_set('error', 'Tunggu sebentar sebelum mengirim ulang kode.');
            redirect('/verify.php');
        }
        $code = auth_issue_otp((int) $user['id']);
        $_SESSION['otp_sent_at'] = time();
        if (mail_send_otp($email, (string) $user['name'], $code)) {
            unset($_SESSION['dev_otp']);
            flash_set('ok', 'Kode baru telah dikirim ke email Anda.');
        } else {
            $_SESSION['dev_otp'] = $code;
            flash_set('info', 'SMTP belum aktif — kode baru ditampilkan (mode pengembangan).');
        }
        redirect('/verify.php');
    }

    $code = trim((string) ($_POST['code'] ?? ''));
    $error = auth_verify_otp((int) $user['id'], $code);
    if ($error === '') {
        unset($_SESSION['pending_email'], $_SESSION['dev_otp'], $_SESSION['otp_sent_at']);
        auth_login_user((int) $user['id']);
        flash_set('ok', 'Email terverifikasi. Selamat datang!');
        redirect('/dashboard.php');
    }

    flash_set('error', $error);
    redirect('/verify.php');
}

$page_title = 'Verifikasi Email — Pencatat Keuangan';
$page_desc = 'Masukkan kode OTP untuk memverifikasi akun Anda.';
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
                    <h1>Verifikasi email</h1>
                    <p class="lead">Kami mengirim kode 6 digit ke <strong><?= e($email) ?></strong>. Kode berlaku 10 menit.</p>
                </div>

                <?php if (!empty($_SESSION['dev_otp'])): ?>
                    <div class="flash flash-info">Mode pengembangan (SMTP nonaktif) — kode OTP: <strong class="mono"><?= e((string) $_SESSION['dev_otp']) ?></strong></div>
                <?php endif; ?>

                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/verify.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <div class="field">
                        <label for="code">Kode OTP</label>
                        <input class="input" type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Verifikasi</button>
                </form>

                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/verify.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="resend">
                    <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Kirim ulang kode</button>
                </form>

                <p class="form-note">Salah alamat email? <a href="<?= e(APP_BASE) ?>/register.php">Daftar ulang</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
