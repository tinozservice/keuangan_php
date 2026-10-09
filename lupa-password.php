<?php
/* Pencatat Keuangan — lupa kata sandi: email → kode OTP → kata sandi baru (pelengkap Alur 1 PRD). */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';

if (auth_user() !== null) {
    redirect('/dashboard.php');
}

// Mulai ulang alur (mis. salah alamat email).
if (isset($_GET['ulang'])) {
    unset(
        $_SESSION['pwd_reset_user_id'],
        $_SESSION['pwd_reset_email'],
        $_SESSION['pwd_reset_ok'],
        $_SESSION['pwd_reset_sent_at'],
        $_SESSION['pwd_reset_dev_otp']
    );
    redirect('/lupa-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? '');

    // Langkah 1 — kirim kode ke email terdaftar.
    if ($action === 'email') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $user = auth_find_user_by_email($email);
        if ($user === null) {
            flash_set('error', 'Email tidak terdaftar. Pastikan alamat email sesuai akun Anda.');
            redirect('/lupa-password.php');
        }

        $userId = (int) $user['id'];
        $last = (int) ($_SESSION['pwd_reset_sent_at'] ?? 0);
        if ((int) ($_SESSION['pwd_reset_user_id'] ?? 0) === $userId && time() - $last < 60) {
            flash_set('error', 'Kode sudah dikirim belum lama ini. Tunggu sebentar sebelum meminta kode baru.');
            redirect('/lupa-password.php');
        }

        $code = auth_issue_otp($userId);
        $_SESSION['pwd_reset_user_id'] = $userId;
        $_SESSION['pwd_reset_email'] = (string) $user['email'];
        $_SESSION['pwd_reset_ok'] = false;
        $_SESSION['pwd_reset_sent_at'] = time();

        if (mail_send_reset_otp((string) $user['email'], (string) $user['name'], $code)) {
            unset($_SESSION['pwd_reset_dev_otp']);
            flash_set('ok', 'Kode atur ulang telah dikirim ke email Anda.');
        } else {
            $_SESSION['pwd_reset_dev_otp'] = $code;
            flash_set('info', 'SMTP belum aktif — mode pengembangan: kode OTP ditampilkan di halaman ini.');
        }
        redirect('/lupa-password.php');
    }

    // Kirim ulang kode (jeda minimal 60 detik).
    if ($action === 'resend') {
        $resetEmail = (string) ($_SESSION['pwd_reset_email'] ?? '');
        $user = $resetEmail !== '' ? auth_find_user_by_email($resetEmail) : null;
        if ($user === null || (int) $user['id'] !== (int) ($_SESSION['pwd_reset_user_id'] ?? 0)) {
            flash_set('error', 'Sesi atur ulang tidak ditemukan. Silakan mulai dari awal.');
            redirect('/lupa-password.php?ulang=1');
        }

        $last = (int) ($_SESSION['pwd_reset_sent_at'] ?? 0);
        if (time() - $last < 60) {
            flash_set('error', 'Tunggu sebentar sebelum mengirim ulang kode.');
            redirect('/lupa-password.php');
        }

        $code = auth_issue_otp((int) $user['id']);
        $_SESSION['pwd_reset_ok'] = false;
        $_SESSION['pwd_reset_sent_at'] = time();
        if (mail_send_reset_otp((string) $user['email'], (string) $user['name'], $code)) {
            unset($_SESSION['pwd_reset_dev_otp']);
            flash_set('ok', 'Kode baru telah dikirim ke email Anda.');
        } else {
            $_SESSION['pwd_reset_dev_otp'] = $code;
            flash_set('info', 'SMTP belum aktif — kode baru ditampilkan (mode pengembangan).');
        }
        redirect('/lupa-password.php');
    }

    // Langkah 2 — verifikasi kode OTP.
    if ($action === 'verify') {
        $userId = (int) ($_SESSION['pwd_reset_user_id'] ?? 0);
        if ($userId <= 0) {
            flash_set('error', 'Sesi atur ulang tidak ditemukan. Silakan mulai dari awal.');
            redirect('/lupa-password.php?ulang=1');
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        $error = auth_verify_otp($userId, $code, false);
        if ($error !== '') {
            flash_set('error', $error);
            redirect('/lupa-password.php');
        }

        $_SESSION['pwd_reset_ok'] = true;
        unset($_SESSION['pwd_reset_dev_otp']);
        flash_set('ok', 'Kode benar. Silakan buat kata sandi baru Anda.');
        redirect('/lupa-password.php');
    }

    // Langkah 3 — simpan kata sandi baru & masuk dashboard.
    if ($action === 'password') {
        $userId = (int) ($_SESSION['pwd_reset_user_id'] ?? 0);
        if ($userId <= 0 || empty($_SESSION['pwd_reset_ok'])) {
            flash_set('error', 'Verifikasi kode OTP terlebih dahulu.');
            redirect('/lupa-password.php');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $error = null;
        if (strlen($password) < 8) {
            $error = 'Kata sandi minimal 8 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Ulangi kata sandi tidak cocok.';
        }
        if ($error !== null) {
            flash_set('error', $error);
            redirect('/lupa-password.php');
        }

        $pdo = db();
        $pdo->prepare('UPDATE users SET password_hash = ?, is_verified = 1, verified_at = COALESCE(verified_at, ?) WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $userId]);
        $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$userId]);

        unset(
            $_SESSION['pwd_reset_user_id'],
            $_SESSION['pwd_reset_email'],
            $_SESSION['pwd_reset_ok'],
            $_SESSION['pwd_reset_sent_at'],
            $_SESSION['pwd_reset_dev_otp']
        );
        auth_login_user($userId);
        flash_set('ok', 'Kata sandi berhasil diperbarui. Selamat datang!');
        redirect('/dashboard.php');
    }

    redirect('/lupa-password.php');
}

$resetUserId = (int) ($_SESSION['pwd_reset_user_id'] ?? 0);
$resetEmail = (string) ($_SESSION['pwd_reset_email'] ?? '');
$resetOk = !empty($_SESSION['pwd_reset_ok']);
$step = $resetOk ? 3 : ($resetUserId > 0 ? 2 : 1);

$page_title = 'Lupa Kata Sandi — Pencatat Keuangan';
$page_desc = 'Atur ulang kata sandi akun Pencatat Keuangan melalui kode OTP email.';
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
                    <h1>Lupa kata sandi?</h1>
                    <?php if ($step === 1): ?>
                        <p class="lead">Masukkan email akun Anda. Kami akan mengirim kode 6 digit untuk mengatur ulang kata sandi.</p>
                    <?php elseif ($step === 2): ?>
                        <p class="lead">Kami mengirim kode 6 digit ke <strong><?= e($resetEmail) ?></strong>. Kode berlaku 10 menit.</p>
                    <?php else: ?>
                        <p class="lead">Kode terverifikasi. Buat kata sandi baru untuk <strong><?= e($resetEmail) ?></strong>.</p>
                    <?php endif; ?>
                </div>

                <?php if ($step === 2 && !empty($_SESSION['pwd_reset_dev_otp'])): ?>
                    <div class="flash flash-info">Mode pengembangan (SMTP nonaktif) — kode OTP: <strong class="mono"><?= e((string) $_SESSION['pwd_reset_dev_otp']) ?></strong></div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/lupa-password.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="email">
                    <div class="field">
                        <label for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" required autocomplete="email" value="">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Kirim kode</button>
                </form>
                <?php elseif ($step === 2): ?>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/lupa-password.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <div class="field">
                        <label for="code">Kode OTP</label>
                        <input class="input" type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Verifikasi kode</button>
                </form>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/lupa-password.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="resend">
                    <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Kirim ulang kode</button>
                </form>
                <?php else: ?>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/lupa-password.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="field">
                        <label for="password">Kata sandi baru</label>
                        <input class="input" type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="password_confirm">Ulangi kata sandi baru</label>
                        <input class="input" type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-key" aria-hidden="true"></i> Simpan kata sandi baru</button>
                </form>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <p class="form-note">Ingat kata sandi? <a href="<?= e(APP_BASE) ?>/login.php">Masuk</a></p>
                <?php else: ?>
                    <p class="form-note">Salah alamat email? <a href="<?= e(APP_BASE) ?>/lupa-password.php?ulang=1">Ulangi dari awal</a></p>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
