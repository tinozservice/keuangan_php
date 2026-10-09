<?php
/* Pencatat Keuangan — log aktivitas akun pengguna: login + buat/hapus workspace (FR-055). */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/log.php';
require_once __DIR__ . '/includes/pagination.php';

$user = auth_require_login();
$uid = (int) $user['id'];

$page = page_current();
$total = user_log_count($uid);
$totalPages = page_total($total);
$page = min($page, $totalPages);
$entries = user_log_list($uid, PER_PAGE, page_offset($page));

$page_title = 'Log Aktivitas — Pencatat Keuangan';
$page_desc = 'Riwayat aktivitas akun Anda: masuk aplikasi, pembuatan, dan penghapusan workspace.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap dash">
            <div class="dash-head">
                <div>
                    <h1>Log Aktivitas</h1>
                    <p class="lead">Riwayat akun Anda: masuk aplikasi serta pembuatan dan penghapusan workspace — tiap baris memuat lokasi &amp; IP address pelaku (FR-059).</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
            </div>

            <section class="dash-section">
                <h2>Riwayat aktivitas (<?= $total ?>)</h2>
                <?php if ($entries === []): ?>
                    <p class="ws-meta">Belum ada aktivitas pada akun Anda.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Aksi</th>
                                <th>Objek</th>
                                <th>Detail</th>
                                <th>Lokasi</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                            <?php [$actionLabel, $actionBadge] = log_action_badge((string) $entry['action']); ?>
                            <tr>
                                <td class="mono"><?= e(date('d M Y H:i', strtotime((string) $entry['created_at']))) ?></td>
                                <td><span class="badge <?= e($actionBadge) ?>"><?= e($actionLabel) ?></span></td>
                                <td><?= e(log_object_label((string) $entry['object_type'])) ?></td>
                                <td><?= e((string) $entry['detail']) ?></td>
                                <td><?= e((string) ($entry['location'] ?? '') !== '' ? (string) $entry['location'] : '—') ?></td>
                                <td class="mono"><?= e((string) ($entry['ip_address'] ?? '') !== '' ? (string) $entry['ip_address'] : '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php page_render(APP_BASE . '/aktivitas.php', $page, $totalPages); ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
