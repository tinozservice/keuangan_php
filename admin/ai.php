<?php
/* Pencatat Keuangan — panel admin: pool AI (provider & model fallback, FR-039–FR-046). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$admin = auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $modelId = (int) ($_POST['model_id'] ?? 0);
    $model = $modelId > 0 ? ai_model_get($modelId) : null;

    if ($model === null) {
        flash_set('error', 'Model tidak ditemukan.');
        redirect('/admin/ai.php');
    }

    if ($aksi === 'toggle') {
        ai_model_toggle($modelId);
        flash_set('ok', 'Model "' . (string) $model['model_id'] . '" ' . ((int) $model['is_active'] === 1 ? 'dinonaktifkan — dilewati pada percobaan fallback.' : 'diaktifkan — ikut pada percobaan fallback.'));
        redirect('/admin/ai.php');
    }
    if ($aksi === 'naik' || $aksi === 'turun') {
        ai_model_move($modelId, $aksi);
        flash_set('ok', 'Urutan model "' . (string) $model['model_id'] . '" digeser ' . ($aksi === 'naik' ? 'ke atas' : 'ke bawah') . '.');
        redirect('/admin/ai.php');
    }

    flash_set('error', 'Aksi tidak dikenali.');
    redirect('/admin/ai.php');
}

$providers = ai_provider_list();
$models = ai_model_list();
$summary = ai_pool_summary();
$totalModels = count($models);

$page_title = 'Pool AI — Panel Admin';
$page_desc = 'Kelola provider OpenAI-compatible dan model fallback beserta urutan prioritas.';
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
                    <h1>Pool AI</h1>
                    <p class="lead"><?= count($providers) ?> provider · <?= $summary['models'] ?> model (<?= $summary['active'] ?> aktif). Urutan baris = urutan percobaan fallback (FR-025).</p>
                </div>
                <div class="inline-actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-kesehatan.php"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i> Periksa kesehatan</a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Panel Admin</a>
                </div>
            </div>

            <section class="dash-section">
                <div class="dash-head">
                    <h2>Provider (<?= count($providers) ?>)</h2>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-provider.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah provider</a>
                </div>
                <?php if ($providers === []): ?>
                    <p class="ws-meta">Belum ada provider. Tambahkan provider OpenAI-compatible (mis. OpenRouter, Groq, OpenAI) dengan Base URL (sertakan <span class="mono">/v1</span> bila berlaku) dan API Key.</p>
                <?php else: ?>
                <div class="member-list">
                    <?php foreach ($providers as $p): ?>
                    <article class="card ws-card">
                        <div class="dash-head">
                            <div>
                                <strong><?= e((string) $p['name']) ?></strong>
                                <div class="ws-meta mono"><?= e((string) $p['base_url']) ?></div>
                            </div>
                            <div class="inline-actions">
                                <span class="badge badge-yellow"><?= (int) $p['model_count'] ?> model</span>
                            </div>
                        </div>
                        <div class="ws-meta">API Key: <span class="mono"><?= e(ai_mask_key((string) $p['api_key'])) ?></span></div>
                        <div class="inline-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-provider.php?id=<?= (int) $p['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Ubah</a>
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-hapus.php?jenis=provider&amp;id=<?= (int) $p['id'] ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus…</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <section class="dash-section">
                <div class="dash-head">
                    <h2>Model fallback (<?= $totalModels ?>)</h2>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-model.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah model</a>
                </div>
                <?php if ($models === []): ?>
                    <p class="ws-meta">Belum ada model. Tambahkan model pada salah satu provider, lengkapi kapabilitas input/output (FR-041) dan harga token per 1 juta (untuk estimasi biaya).</p>
                <?php else: ?>
                <div class="member-list">
                    <?php $position = 0; foreach ($models as $m): $position++; ?>
                    <?php
                    $modelRowId = (int) $m['id'];
                    $inLabels = ai_model_cap_labels($m, 'input');
                    $outLabels = ai_model_cap_labels($m, 'output');
                    ?>
                    <article class="card ws-card">
                        <div class="dash-head">
                            <div class="row-form">
                                <span class="badge badge-orange">#<?= $position ?></span>
                                <form method="post" action="<?= e(APP_BASE) ?>/admin/ai.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="naik">
                                    <input type="hidden" name="model_id" value="<?= $modelRowId ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit" <?= $position === 1 ? 'disabled' : '' ?> aria-label="Naikkan urutan"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                                </form>
                                <form method="post" action="<?= e(APP_BASE) ?>/admin/ai.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="turun">
                                    <input type="hidden" name="model_id" value="<?= $modelRowId ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit" <?= $position === $totalModels ? 'disabled' : '' ?> aria-label="Turunkan urutan"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                                </form>
                                <span class="mono"><strong><?= e((string) $m['model_id']) ?></strong></span>
                            </div>
                            <div class="inline-actions">
                                <span class="badge <?= (int) $m['is_active'] === 1 ? 'badge-yellow' : 'badge-orange' ?>"><?= (int) $m['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
                                <?php if ((string) $m['health_status'] === 'ok'): ?>
                                    <span class="badge badge-yellow">Sehat</span>
                                <?php elseif ((string) $m['health_status'] === 'error'): ?>
                                    <span class="badge badge-orange">Bermasalah</span>
                                <?php else: ?>
                                    <span class="ws-meta">Belum diperiksa</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ws-meta">
                            <span><?= e((string) $m['provider_name']) ?></span>
                            <?php if (trim((string) $m['label']) !== ''): ?>
                            <span>·</span>
                            <span><?= e((string) $m['label']) ?></span>
                            <?php endif; ?>
                            <span>·</span>
                            <span>prioritas <?= (int) $m['priority'] ?></span>
                        </div>
                        <div class="ws-meta">
                            <span>In: <?php foreach ($inLabels as $label): ?><span class="badge badge-yellow"><?= e($label) ?></span> <?php endforeach; ?><?= $inLabels === [] ? '—' : '' ?></span>
                            <span>Out: <?php foreach ($outLabels as $label): ?><span class="badge badge-orange"><?= e($label) ?></span> <?php endforeach; ?><?= $outLabels === [] ? '—' : '' ?></span>
                        </div>
                        <div class="ws-meta">
                            <span>Harga: <span class="mono"><?= e(ai_price_display((float) $m['price_in'])) ?> / <?= e(ai_price_display((float) $m['price_out'])) ?></span> USD per 1 juta token (in/out)</span>
                        </div>
                        <?php if ((string) ($m['health_checked_at'] ?? '') !== '' || (string) ($m['health_detail'] ?? '') !== ''): ?>
                        <div class="ws-meta">
                            <?php if ((string) ($m['health_checked_at'] ?? '') !== ''): ?>
                            <span>Diperiksa <?= e(date('d M Y H:i', strtotime((string) $m['health_checked_at']))) ?></span>
                            <?php endif; ?>
                            <?php if ((string) ($m['health_detail'] ?? '') !== ''): ?>
                            <span>· <?= e((string) $m['health_detail']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="inline-actions">
                            <form method="post" action="<?= e(APP_BASE) ?>/admin/ai.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="toggle">
                                <input type="hidden" name="model_id" value="<?= $modelRowId ?>">
                                <button class="btn btn-ghost btn-sm" type="submit"><i class="fa-solid <?= (int) $m['is_active'] === 1 ? 'fa-pause' : 'fa-play' ?>" aria-hidden="true"></i> <?= (int) $m['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-model.php?id=<?= $modelRowId ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Ubah</a>
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-hapus.php?jenis=model&amp;id=<?= $modelRowId ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus…</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
