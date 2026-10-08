<?php
/**
 * Inisialisasi bersama untuk seluruh halaman.
 * - strict types & mode debug dari .env
 * - zona waktu proyek (Asia/Jakarta)
 * - konstanta APP_ROOT (jalur filesystem) & APP_BASE (URL dasar)
 * - pemuatan .env, sesi aman, CSRF, helper escaping/flash/redirect
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

define('APP_ROOT', dirname(__DIR__));

// URL dasar relatif terhadap document root (aman untuk deploy di subfolder).
$docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$appRoot = str_replace('\\', '/', APP_ROOT);
$base = ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) ? substr($appRoot, strlen($docRoot)) : '';
define('APP_BASE', rtrim($base, '/'));

require __DIR__ . '/env.php';
env_load(APP_ROOT . '/.env');

// Mode pengembangan: tampilkan seluruh error; produksi: sembunyikan.
error_reporting(E_ALL);
ini_set('display_errors', env('APP_DEBUG', 'false') === 'true' ? '1' : '0');

// Sesi aman (cookie httponly + SameSite Lax; secure otomatis saat HTTPS).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE === '' ? '/' : APP_BASE . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('KEUANGANSESS');
    session_start();
}

require __DIR__ . '/csrf.php';

/** Escaping output HTML (aturan keamanan XSS). */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Redirect internal (selalu relatif ke APP_BASE). */
function redirect(string $path): void
{
    header('Location: ' . APP_BASE . $path);
    exit;
}

/** Simpan pesan flash untuk ditampilkan sekali di halaman berikutnya. */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Ambil & hapus pesan flash. */
function flash_pull(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

/** Render pesan flash (aman terhadap XSS); tidak menulis apa pun bila kosong. */
function flash_render(): void
{
    $flash = flash_pull();
    if ($flash === null) {
        return;
    }
    $type = in_array($flash['type'] ?? '', ['ok', 'error', 'info'], true) ? (string) $flash['type'] : 'info';
    echo '<div class="flash flash-' . e($type) . '">' . e((string) ($flash['message'] ?? '')) . '</div>';
}
