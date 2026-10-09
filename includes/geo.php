<?php
/**
 * Geolokasi IP untuk log aktivitas (FR-059).
 * - IP klien diambil dari request (X-Forwarded-For bila di belakang proxy, lalu REMOTE_ADDR).
 * - Lokasi geografis diambil dari ipwho.is (HTTPS, tanpa API key) lalu disimpan pada
 *   tabel `ip_locations` sebagai cache per IP — satu panggilan jaringan per alamat.
 * - Bila lookup gagal, lokasi diisi "Tidak diketahui" tanpa menghentikan pencatatan log.
 */
require_once __DIR__ . '/db.php';

/** IP publik klien untuk tujuan log. Menghormati X-Forwarded-For (proxy/reverse proxy). */
function geo_client_ip(): string
{
    $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        // Ambil entri pertama (klien asli), abaikan daftar proxy.
        $first = trim((string) explode(',', $forwarded)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
            return $first;
        }
    }

    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '-';
}

/** IP privat/lokal tidak perlu di-lookup (tidak ada lokasi publik). */
function geo_is_private(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return true;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * Lokasi geografis sebuah IP: "Kota, Negara" — hasil cache bila pernah di-lookup.
 * Mengembalikan "Tidak diketahui" bila lookup gagal (tanpa menyimpan kegagalan permanen
 * bila IP pernah berhasil), agar pencatatan log tidak pernah terhambat.
 */
function geo_location(string $ip): string
{
    if ($ip === '' || $ip === '-' || geo_is_private($ip)) {
        return $ip === '' || $ip === '-' ? 'Tidak diketahui' : 'Jaringan lokal';
    }

    $st = db()->prepare('SELECT location FROM ip_locations WHERE ip = ? LIMIT 1');
    $st->execute([$ip]);
    $cached = $st->fetchColumn();
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $location = geo_lookup($ip);

    // Hasil "Tidak diketahui" tidak ditetapkan permanen — coba lagi pada kunjungan berikut.
    if ($location !== 'Tidak diketahui') {
        db()->prepare('INSERT INTO ip_locations (ip, location, fetched_at) VALUES (?, ?, ?)
            ON CONFLICT(ip) DO UPDATE SET location = excluded.location, fetched_at = excluded.fetched_at')
            ->execute([$ip, $location, date('Y-m-d H:i:s')]);
    }

    return $location;
}

/** Panggil ipwho.is untuk satu IP; kembalikan "Kota, Negara" atau "Tidak diketahui". */
function geo_lookup(string $ip): string
{
    if (!function_exists('curl_init')) {
        return 'Tidak diketahui';
    }

    $ch = curl_init('https://ipwho.is/' . rawurlencode($ip));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status !== 200) {
        error_log('GEOLOCATION: gagal lookup ' . $ip . ' (' . ($error !== '' ? $error : 'HTTP ' . $status) . ')');
        return 'Tidak diketahui';
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data) || empty($data['success'])) {
        return 'Tidak diketahui';
    }

    $city = trim((string) ($data['city'] ?? ''));
    $country = trim((string) ($data['country'] ?? ''));
    $location = trim($city . ($city !== '' && $country !== '' ? ', ' : '') . $country);

    return $location !== '' ? $location : 'Tidak diketahui';
}
