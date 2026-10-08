<?php
/* Pencatat Keuangan — terima/tolak undangan workspace (POST + CSRF). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';

$user = auth_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/dashboard.php');
}
csrf_require();

$id = (int) ($_POST['id'] ?? 0);
$aksi = ((string) ($_POST['aksi'] ?? '')) === 'terima' ? 'terima' : 'tolak';

[$ok, $message] = ws_respond_invitation($id, (int) $user['id'], $aksi);
flash_set($ok ? 'ok' : 'error', $message);
redirect('/dashboard.php');
