<?php
/** Autentikasi & verifikasi akun (sesi + OTP). */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';

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

    $st = db()->prepare('SELECT id, name, username, email, has_password, is_verified, role, created_at FROM users WHERE id = ? LIMIT 1');
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
    user_log_write($userId, 'masuk', 'akun', 'Masuk ke aplikasi');
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
    $st = db()->prepare('INSERT INTO users (name, username, email, password_hash, has_password, is_verified, created_at) VALUES (?, ?, ?, ?, 1, 0, ?)');
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
function auth_verify_otp(int $userId, string $code, bool $markVerified = true): string
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
    if ($markVerified) {
        $pdo->prepare('UPDATE users SET is_verified = 1, verified_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $userId]);
    }
    $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$userId]);
    return '';
}

// --- Pembatasan percobaan login (NFR-021) ---------------------------------

/** NFR-021: maksimum percobaan gagal per akun/identifier dalam satu jendela. */
const AUTH_LOGIN_MAX_FAILURES = 5;
/** NFR-021: panjang jendela pembatasan sekaligus lama kunci sementara (detik). */
const AUTH_LOGIN_LOCK_WINDOW = 900;

/** Normalisasi identifier untuk pembatasan login (huruf kecil, tanpa spasi tepi). */
function auth_login_identifier_key(string $identifier): string
{
    return strtolower(trim($identifier));
}

/** Cakupan riwayat kegagalan: per akun bila terdaftar, per identifier bila tidak. */
function auth_login_attempt_scope(?array $user, string $identifier): array
{
    if ($user !== null) {
        return ['user_id = ?', [(int) $user['id']]];
    }
    return ['user_id IS NULL AND identifier = ?', [auth_login_identifier_key($identifier)]];
}

/**
 * Status pembatasan login (NFR-021).
 * Terkunci bila ada >= 5 kegagalan dalam jendela 15 menit; kunci berakhir
 * 15 menit setelah kegagalan pemicu (percobaan saat terkunci tidak dicatat
 * ulang sehingga kunci tidak diperpanjang tanpa batas dari percobaan yang sama).
 *
 * @return array{locked: bool, remaining: int, left: int} `remaining` = detik sisa kunci; `left` = sisa percobaan.
 */
function auth_login_lock_state(?array $user, string $identifier): array
{
    [$where, $params] = auth_login_attempt_scope($user, $identifier);

    $st = db()->prepare('SELECT created_at FROM login_attempts WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT 1');
    $st->execute($params);
    $last = $st->fetchColumn();

    if ($last === false) {
        return ['locked' => false, 'remaining' => 0, 'left' => AUTH_LOGIN_MAX_FAILURES];
    }

    $lastTs = (int) strtotime((string) $last);
    $lockedUntil = $lastTs + AUTH_LOGIN_LOCK_WINDOW;

    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ' . $where . ' AND created_at >= ?');
    $st->execute([...$params, date('Y-m-d H:i:s', $lastTs - AUTH_LOGIN_LOCK_WINDOW)]);
    $cluster = (int) $st->fetchColumn();

    if ($cluster >= AUTH_LOGIN_MAX_FAILURES && time() < $lockedUntil) {
        return ['locked' => true, 'remaining' => $lockedUntil - time(), 'left' => 0];
    }

    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ' . $where . ' AND created_at >= ?');
    $st->execute([...$params, date('Y-m-d H:i:s', time() - AUTH_LOGIN_LOCK_WINDOW)]);
    $recent = (int) $st->fetchColumn();

    return ['locked' => false, 'remaining' => 0, 'left' => max(0, AUTH_LOGIN_MAX_FAILURES - $recent)];
}

/** Catat satu kegagalan login untuk pembatasan (NFR-021); riwayat lama dibersihkan. */
function auth_login_record_failure(?array $user, string $identifier): void
{
    db()->prepare('INSERT INTO login_attempts (user_id, identifier, ip_address, created_at) VALUES (?, ?, ?, ?)')
        ->execute([
            $user === null ? null : (int) $user['id'],
            auth_login_identifier_key($identifier),
            geo_client_ip(),
            date('Y-m-d H:i:s'),
        ]);
    db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

/** Hapus riwayat kegagalan setelah kredensial benar — hitungan dimulai bersih (NFR-021). */
function auth_login_clear_failures(?array $user, string $identifier): void
{
    [$where, $params] = auth_login_attempt_scope($user, $identifier);
    db()->prepare('DELETE FROM login_attempts WHERE ' . $where)->execute($params);
}

/** Pesan kunci sementara (NFR-021) — membulatkan sisa detik ke atas dalam menit. */
function auth_login_lock_message(int $remainingSeconds): string
{
    $minutes = max(1, (int) ceil(max(0, $remainingSeconds) / 60));
    return 'Terlalu banyak percobaan gagal. Demi keamanan, coba lagi dalam ' . $minutes . ' menit.';
}
