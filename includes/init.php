<?php
/**
 * Inisialisasi bersama untuk seluruh halaman.
 * - strict types
 * - zona waktu proyek (Asia/Jakarta)
 * - konstanta APP_ROOT (jalur filesystem) & APP_BASE (URL dasar)
 * - helper escaping output e()
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

define('APP_ROOT', dirname(__DIR__));

// URL dasar relatif terhadap document root (aman untuk deploy di subfolder).
$docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$appRoot = str_replace('\\', '/', APP_ROOT);
$base = ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) ? substr($appRoot, strlen($docRoot)) : '';
define('APP_BASE', rtrim($base, '/'));

/** Escaping output HTML (aturan keamanan XSS). */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
