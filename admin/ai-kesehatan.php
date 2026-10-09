<?php
/* Pencatat Keuangan — panel admin: pemeriksaan kesehatan pool AI (FR-043–FR-045). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$admin = auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    @set_time_limit(600);
    $result = ai_health_check_all();
    if ($result['total'] === 0) {
        flash_set('info', 'Tidak ada model aktif untuk diperiksa.');
    } else {
        flash_set('ok', 'Pemeriksaan selesai: ' . $result['total'] . ' model diperiksa — ' . $result['ok'] . ' sehat, ' . $result['error'] . ' bermasalah.');
    }
    redirect('/admin/ai-kesehatan.php');
}

$summary = ai_pool_summary();
$checked = db()->query('SELECT m.*, p.name AS provider_name FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id WHERE m.health_checked_at IS NOT NULL ORDER BY m.health_checked_at DESC, m.id ASC')->fetchAll();

$page_title = 'Kesehatan Pool AI — Panel Admin';
$page_desc = 'Pemeriksaan kesehatan massal seluruh model pool fallback.';
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
                    <h1>Kesehatan Pool AI</h1>
                    <p class="lead">Pemeriksaan massal memanggil setiap model aktif satu per satu (bisa memakan beberapa detik per model).</p>
                </div>
                <div class="inline-actions">
                    <form method="post" action="<?= e(APP_BASE) ?>/admin/ai-kesehatan.php">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i> Jalankan pemeriksaan</button>
                    </form>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Pool AI</a>
                </div>
            </div>

            <section class="dash-section">
                <div class="stat-grid">
                    <div class="card ws-card">
                        <div class="ws-meta">Provider</div>
                        <div class="stat-value"><?= $summary['providers'] ?></div>
                    </div>
                    <div class="card ws-card">
                        <div class="ws-meta">Model aktif</div>
                        <div class="stat-value"><?= $summary['active'] ?> / <?= $summary['models'] ?></div>
                    </div>
                    <div class="card ws-card">
                        <div class="ws-meta">Sehat</div>
                        <div class="stat-value in"><?= $summary['ok'] ?></div>
                    </div>
                    <div class="card ws-card">
                        <div class="ws-meta">Bermasalah</div>
                        <div class="stat-value"><?= $summary['error'] ?></div>
                    </div>
                    <div class="card ws-card">
                        <div class="ws-meta">Belum diperiksa</div>
                        <div class="stat-value"><?= $summary['unchecked'] ?></div>
                    </div>
                </div>
            </section>

            <section class="dash-section">
                <h2>Hasil pemeriksaan terakhir</h2>
                <?php if ($checked === []): ?>
                    <p class="ws-meta">Belum ada hasil. Jalankan pemeriksaan untuk mengisi status kesehatan setiap model.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Detail</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checked as $m): ?>
                            <tr>
                                <td class="mono"><?= e((string) $m['model_id']) ?></td>
                                <td><?= e((string) $m['provider_name']) ?><?= (int) $m['is_active'] === 1 ? '' : ' <span class="badge badge-orange">Nonaktif</span>' ?></td>
                                <td>
                                    <?php if ((string) $m['health_status'] === 'ok'): ?>
                                        <span class="badge badge-yellow">Sehat</span>
                                    <?php else: ?>
                                        <span class="badge badge-orange">Bermasalah</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) $m['health_detail']) ?></td>
                                <td class="mono"><?= e(date('d M Y H:i', strtotime((string) $m['health_checked_at']))) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
