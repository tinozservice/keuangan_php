<?php
/* Pencatat Keuangan — panel admin: log percobaan masuk identifier tak terdaftar (FR-060). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/log.php';
require_once dirname(__DIR__) . '/includes/pagination.php';

$admin = auth_require_admin();

$page = page_current();
$totalUnknown = user_log_unknown_count();
$totalPages = page_total($totalUnknown);
$page = min($page, $totalPages);
$entries = user_log_unknown_list(PER_PAGE, page_offset($page));

$page_title = 'Percobaan Masuk — Panel Admin';
$page_desc = 'Log percobaan masuk dengan email/username yang belum terdaftar, lengkap dengan lokasi dan IP address.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require dirname(__DIR__) . '/includes/head.php'; ?>
</head>
<body>
    <?php require dirname(__DIR__) . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap dash">
            <div class="dash-head">
                <div>
                    <h1>Percobaan Masuk</h1>
                    <p class="lead"><?= $totalUnknown ?> percobaan masuk ke identifier yang belum terdaftar. Percobaan pada akun terdaftar tampil pada Log Aktivitas masing-masing pengguna.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Panel Admin</a>
            </div>

            <section class="dash-section">
                <h2>Riwayat percobaan (<?= $totalUnknown ?>)</h2>
                <?php if ($entries === []): ?>
                    <p class="ws-meta">Belum ada percobaan masuk ke identifier tak terdaftar.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Identifier</th>
                                <th>Detail</th>
                                <th>Lokasi</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td class="mono"><?= e(date('d M Y H:i', strtotime((string) $entry['created_at']))) ?></td>
                                <td class="mono"><?= e((string) $entry['identifier']) ?></td>
                                <td><?= e((string) $entry['detail']) ?></td>
                                <td><?= e((string) ($entry['location'] ?? '') !== '' ? (string) $entry['location'] : '—') ?></td>
                                <td class="mono"><?= e((string) ($entry['ip_address'] ?? '') !== '' ? (string) $entry['ip_address'] : '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php page_render(APP_BASE . '/admin/login-gagal.php', $page, $totalPages); ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
