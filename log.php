<?php
/* Pencatat Keuangan — log aktivitas workspace (FR-033), berhalaman. */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workspace.php';
require_once __DIR__ . '/includes/log.php';
require_once __DIR__ . '/includes/pagination.php';

$user = auth_require_login();
$uid = (int) $user['id'];

$id = (int) ($_GET['id'] ?? 0);
$ws = $id > 0 ? ws_get($id) : null;
if ($ws === null) {
    flash_set('error', 'Workspace tidak ditemukan.');
    redirect('/dashboard.php');
}
$role = ws_role_for_user($id, $uid);
if ($role === null) {
    flash_set('error', 'Anda tidak memiliki akses ke workspace ini.');
    redirect('/dashboard.php');
}
$isOwner = $role === 'owner';

$page = page_current();
$total = log_count($id);
$totalPages = page_total($total);
$page = min($page, $totalPages);
$entries = log_list($id, PER_PAGE, page_offset($page));

$page_title = 'Log Aktivitas — ' . (string) $ws['name'];
$page_desc = 'Riwayat aktivitas workspace.';
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
                    <p class="ws-meta">
                        <span><?= e((string) $ws['name']) ?></span>
                        <span class="badge <?= $isOwner ? 'badge-orange' : 'badge-yellow' ?>"><?= $isOwner ? 'Pemilik' : 'Kolaborator' ?></span>
                    </p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Workspace</a>
            </div>

            <section class="dash-section">
                <h2>Riwayat aktivitas (<?= $total ?>)</h2>
                <?php if ($entries === []): ?>
                    <p class="ws-meta">Belum ada aktivitas di workspace ini.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pelaku</th>
                                <th>Aksi</th>
                                <th>Objek</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                            <?php [$actionLabel, $actionBadge] = log_action_badge((string) $entry['action']); ?>
                            <tr>
                                <td class="mono"><?= e(date('d M Y H:i', strtotime((string) $entry['created_at']))) ?></td>
                                <td class="mono">@<?= e((string) $entry['actor_username']) ?></td>
                                <td><span class="badge <?= e($actionBadge) ?>"><?= e($actionLabel) ?></span></td>
                                <td><?= e(log_object_label((string) $entry['object_type'])) ?></td>
                                <td><?= e((string) $entry['detail']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php page_render(APP_BASE . '/log.php?id=' . $id, $page, $totalPages); ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
