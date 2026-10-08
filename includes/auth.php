<?php
/** Autentikasi & verifikasi akun (sesi + OTP). */
require_once __DIR__ . '/db.php';

function auth_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        $user = null;
        return null;
    }

    $st = db()->prepare('SELECT id, name, username, email, is_verified, role, created_at FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $row = $st->fetch();

    if ($row === false) {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }

    $user = $row;
    return $user;
}

function auth_require_login(): array
{
    $user = auth_user();
    if ($user === null) {
        flash_set('error', 'Silakan masuk terlebih dahulu.');
        redirect('/login.php');
    }
    return $user;
}

function auth_require_admin(): array
{
    $user = auth_require_login();
    if (($user['role'] ?? 'user') !== 'admin') {
        flash_set('error', 'Halaman tersebut khusus admin.');
        redirect('/dashboard.php');
    }
    return $user;
}

function auth_login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Ganti ID sesi & hapus berkas sesi lama; cookie tetap hidup untuk flash keluar.
        session_regenerate_id(true);
    }
}

function auth_find_user_by_email(string $email): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([strtolower(trim($email))]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function auth_find_user_by_email_or_username(string $value): ?array
{
    $value = strtolower(trim($value));
    $st = db()->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
    $st->execute([$value, $value]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function auth_username_exists(string $username): bool
{
    $st = db()->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    $st->execute([strtolower(trim($username))]);
    return $st->fetch() !== false;
}

function auth_create_user(string $username, string $email, string $password): int
{
    $st = db()->prepare('INSERT INTO users (name, username, email, password_hash, is_verified, created_at) VALUES (?, ?, ?, ?, 0, ?)');
    $st->execute([$username, $username, strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
    return (int) db()->lastInsertId();
}

/** Terbitkan kode OTP baru (berlaku 10 menit) dan kembalikan kode mentahnya. */
function auth_issue_otp(int $userId): string
{
    $code = (string) random_int(100000, 999999);
    $pdo = db();
    $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$userId]);
    $st = $pdo->prepare('INSERT INTO otp_codes (user_id, code_hash, expires_at, attempts, created_at) VALUES (?, ?, ?, 0, ?)');
    $st->execute([$userId, password_hash($code, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 600), date('Y-m-d H:i:s')]);
    return $code;
}

/** Periksa kode OTP; kembalikan '' bila sukses atau pesan kesalahan. */
function auth_verify_otp(int $userId, string $code): string
{
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return 'Kode harus 6 digit angka.';
    }

    $st = db()->prepare('SELECT * FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();

    if ($row === false) {
        return 'Tidak ada kode aktif. Gunakan tombol "Kirim ulang kode".';
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return 'Kode sudah kedaluwarsa. Gunakan tombol "Kirim ulang kode".';
    }
    if ((int) $row['attempts'] >= 5) {
        return 'Terlalu banyak percobaan. Gunakan tombol "Kirim ulang kode".';
    }

    if (!password_verify($code, (string) $row['code_hash'])) {
        db()->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([(int) $row['id']]);
        return 'Kode salah. Sisa percobaan: ' . max(0, 4 - (int) $row['attempts']) . '.';
    }

    $pdo = db();
    $pdo->prepare('UPDATE users SET is_verified = 1, verified_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $userId]);
    $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$userId]);
    return '';
}
