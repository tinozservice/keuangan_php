<?php
/* Pencatat Keuangan — unduh transaksi workspace: CSV (FR-034) & Excel .xlsx (FR-035). */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workspace.php';
require_once __DIR__ . '/includes/transaksi.php';
require_once __DIR__ . '/includes/ekspor.php';

$user = auth_require_login();
$uid = (int) $user['id'];

$id = (int) ($_GET['id'] ?? 0);
$ws = $id > 0 ? ws_get($id) : null;
if ($ws === null) {
    flash_set('error', 'Workspace tidak ditemukan.');
    redirect('/dashboard.php');
}
if (ws_role_for_user($id, $uid) === null) {
    flash_set('error', 'Anda tidak memiliki akses ke workspace ini.');
    redirect('/dashboard.php');
}

$format = (string) ($_GET['format'] ?? 'csv');
if (!in_array($format, ['csv', 'xlsx'], true)) {
    flash_set('error', 'Format ekspor tidak dikenali.');
    redirect('/transaksi.php?id=' . $id);
}

$filter = tx_filter_from_query($_GET);
$rows = tx_all($id, $filter);

$baseName = 'transaksi-' . export_slug((string) $ws['name']) . '-' . date('Ymd');
if (tx_filter_active($filter)) {
    $baseName .= '-filter';
}

if ($format === 'xlsx') {
    $body = export_tx_xlsx($rows);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
} else {
    $body = export_tx_csv($rows);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
}

session_write_close();
header('Content-Length: ' . strlen($body));
header('X-Content-Type-Options: nosniff');
echo $body;
