<?php
/* Pencatat Keuangan — buat workspace baru (FR-012). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';

$user = auth_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/dashboard.php');
}
csrf_require();

$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '' || mb_strlen($name) > 80) {
    flash_set('error', 'Nama workspace harus 1–80 karakter.');
    redirect('/dashboard.php');
}

$id = ws_create((int) $user['id'], $name);
flash_set('ok', 'Workspace "' . $name . '" dibuat.');
redirect('/workspace.php?id=' . $id);
