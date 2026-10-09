<?php
/** Login & daftar via Google OAuth 2.0 — implementasi native tanpa vendor (FR-005). */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function google_client_id(): string
{
    return trim((string) env('GOOGLE_CLIENT_ID', ''));
}

function google_client_secret(): string
{
    return trim((string) env('GOOGLE_CLIENT_SECRET', ''));
}

function google_redirect_uri(): string
{
    return trim((string) env('GOOGLE_REDIRECT_URI', ''));
}

/** True bila kredensial Google OAuth tersedia lengkap di .env. */
function google_oauth_enabled(): bool
{
    return google_client_id() !== '' && google_client_secret() !== '' && google_redirect_uri() !== '';
}

/** URL izin Google (Authorization Code flow). */
function google_auth_url(string $state): string
{
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => google_client_id(),
        'redirect_uri' => google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
    ]);
}

/** Panggilan HTTP JSON memakai cURL; kembalikan [status, data] (status 0 bila koneksi gagal). */
function google_http_json(string $url, ?array $postFields = null, string $accessToken = ''): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($accessToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        error_log('GOOGLE OAUTH: ' . $error);
        return [0, null];
    }

    $data = json_decode((string) $body, true);
    return [$status, is_array($data) ? $data : null];
}

/** Tukar authorization code menjadi access token; kembalikan '' bila gagal. */
function google_exchange_code(string $code): string
{
    [$status, $data] = google_http_json('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => google_client_id(),
        'client_secret' => google_client_secret(),
        'redirect_uri' => google_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);

    if ($status !== 200 || $data === null || empty($data['access_token'])) {
        error_log('GOOGLE OAUTH: tukar kode gagal (status ' . $status . ') ' . json_encode($data));
        return '';
    }
    return (string) $data['access_token'];
}

/** Profil pengguna dari endpoint userinfo; null bila gagal. */
function google_userinfo(string $accessToken): ?array
{
    [$status, $data] = google_http_json('https://www.googleapis.com/oauth2/v3/userinfo', null, $accessToken);
    if ($status !== 200 || $data === null || empty($data['sub']) || empty($data['email'])) {
        error_log('GOOGLE OAUTH: userinfo gagal (status ' . $status . ')');
        return null;
    }
    return $data;
}

/** Username unik dari email (huruf kecil/angka/garis bawah, min 3 karakter). */
function google_username_from_email(string $email): string
{
    $base = strtolower((string) strstr($email, '@', true));
    $base = (string) preg_replace('/[^a-z0-9_]+/', '', $base);
    if (strlen($base) < 3) {
        $base = 'user';
    }
    $base = substr($base, 0, 24);

    $candidate = $base;
    $n = 0;
    $st = db()->prepare('SELECT 1 FROM users WHERE username = ? COLLATE NOCASE LIMIT 1');
    while (true) {
        $st->execute([$candidate]);
        if ($st->fetch() === false) {
            return $candidate;
        }
        $n++;
        $candidate = $base . $n;
    }
}

/**
 * Cocokkan akun Google: google_id → email terdaftar (tautkan & verifikasi) → akun baru (terverifikasi).
 * Kembalikan [ok, pesan]; saat sukses sesi pengguna sudah dibuat.
 */
function google_login_or_register(array $info): array
{
    if (empty($info['email_verified'])) {
        return [false, 'Email akun Google Anda belum terverifikasi oleh Google.'];
    }

    $googleId = (string) $info['sub'];
    $email = strtolower(trim((string) $info['email']));
    $name = trim((string) ($info['name'] ?? ''));
    if ($name === '') {
        $name = $email;
    }

    $pdo = db();

    // 1) Sudah pernah masuk dengan Google.
    $st = $pdo->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
    $st->execute([$googleId]);
    $user = $st->fetch();
    if ($user !== false) {
        auth_login_user((int) $user['id']);
        return [true, 'Selamat datang kembali, ' . (string) $user['name'] . '!'];
    }

    // 2) Email sudah terdaftar (akun manual) — tautkan Google & tandai terverifikasi.
    $user = auth_find_user_by_email($email);
    if ($user !== null) {
        $verifiedAt = (int) $user['is_verified'] === 1 && $user['verified_at'] !== null
            ? (string) $user['verified_at']
            : date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE users SET google_id = ?, is_verified = 1, verified_at = ? WHERE id = ?')
            ->execute([$googleId, $verifiedAt, (int) $user['id']]);
        auth_login_user((int) $user['id']);
        return [true, 'Akun tertaut dengan Google. Selamat datang, ' . (string) $user['name'] . '!'];
    }

    // 3) Pengguna baru — dibuat terverifikasi (email diverifikasi Google).
    $username = google_username_from_email($email);
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (name, username, email, password_hash, is_verified, verified_at, role, google_id, created_at) VALUES (?, ?, ?, ?, 1, ?, 'user', ?, ?)")
        ->execute([$name, $username, $email, $hash, date('Y-m-d H:i:s'), $googleId, date('Y-m-d H:i:s')]);
    $userId = (int) $pdo->lastInsertId();
    auth_login_user($userId);
    return [true, 'Akun dibuat dengan Google. Selamat datang, ' . $name . '!'];
}
