<?php
/* Pencatat Keuangan — keluar dari sesi (POST + CSRF). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

csrf_require();
auth_logout();
flash_set('ok', 'Anda telah keluar.');
redirect('/');
