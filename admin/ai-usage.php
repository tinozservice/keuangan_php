<?php
/* Pencatat Keuangan — panel admin: usage per model & estimasi biaya (FR-047–FR-049). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';
require_once dirname(__DIR__) . '/includes/pagination.php';

$admin = auth_require_admin();

[$from, $to, $rangeLabel, $preset] = ai_usage_range((string) ($_GET['rentang'] ?? '7-hari'));
$rows = ai_usage_by_model($from, $to);
$rate = ai_usd_idr_rate();
$rateDisplay = $rate > 0
    ? ($rate == (float) (int) $rate ? number_format($rate, 0, ',', '.') : number_format($rate, 2, ',', '.'))
    : '';

$page = page_current();
$totalCalls = ai_usage_count($from, $to);
$totalPages = page_total($totalCalls);
$page = min($page, $totalPages);
$recent = ai_usage_recent($from, $to, PER_PAGE, page_offset($page));

$sumUsd = 0.0;
$sumCalls = 0;
$sumIn = 0;
$sumOut = 0;
$hasUnpriced = false;
foreach ($rows as $row) {
    $sumCalls += $row['calls'];
    $sumIn += $row['tokens_in'];
    $sumOut += $row['tokens_out'];
    if ($row['usd'] === null) {
        $hasUnpriced = true;
    } else {
        $sumUsd += $row['usd'];
    }
}

$presets = [
    'hari-ini' => 'Hari ini',
    '7-hari' => '7 hari',
    '30-hari' => '30 hari',
    'bulan-ini' => 'Bulan ini',
];

$page_title = 'Usage & Biaya — Panel Admin';
$page_desc = 'Pemakaian token per model dan estimasi biaya.';
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
                    <h1>Usage &amp; Biaya</h1>
                    <p class="lead">Pemakaian model pool AI — <?= e($rangeLabel) ?>.</p>
                </div>
                <div class="inline-actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-kurs.php"><i class="fa-solid fa-dollar-sign" aria-hidden="true"></i> Kurs USD→IDR</a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Pool AI</a>
                </div>
            </div>

            <section class="dash-section">
                <div class="inline-actions">
                    <?php foreach ($presets as $key => $label): ?>
                    <a class="btn <?= $preset === $key ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-usage.php?rentang=<?= e($key) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <p class="ws-meta">Kurs aktif:
                    <?php if ($rate > 0): ?>
                    <strong>Rp<?= e($rateDisplay) ?> / USD</strong>
                    <?php else: ?>
                    belum diatur — <a href="<?= e(APP_BASE) ?>/admin/ai-kurs.php">atur kurs</a>
                    <?php endif; ?>
                </p>
            </section>

            <section class="dash-section">
                <h2>Per model (<?= count($rows) ?>)</h2>
                <?php if ($rows === []): ?>
                    <p class="ws-meta">Belum ada pemakaian pada rentang ini.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Provider</th>
                                <th>Panggilan</th>
                                <th>Token masuk</th>
                                <th>Token keluar</th>
                                <th>Estimasi USD</th>
                                <th>Estimasi IDR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="mono"><?= e($row['model_id']) ?></td>
                                <td><?= e($row['provider_name']) ?></td>
                                <td><?= $row['calls_ok'] ?> / <?= $row['calls'] ?></td>
                                <td class="mono"><?= number_format($row['tokens_in'], 0, ',', '.') ?></td>
                                <td class="mono"><?= number_format($row['tokens_out'], 0, ',', '.') ?></td>
                                <td class="mono">$<?= e(ai_usd_display((float) ($row['usd'] ?? 0))) ?><?= $row['usd'] === null ? ' (tanpa harga)' : '' ?></td>
                                <td class="mono"><?= ($row['usd'] !== null && $rate > 0) ? 'Rp' . e(number_format((float) $row['usd'] * $rate, 0, ',', '.')) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="is-total">
                                <td>Total</td>
                                <td>—</td>
                                <td><?= $sumCalls ?> panggilan</td>
                                <td class="mono"><?= number_format($sumIn, 0, ',', '.') ?></td>
                                <td class="mono"><?= number_format($sumOut, 0, ',', '.') ?></td>
                                <td class="mono">$<?= e(ai_usd_display($sumUsd)) ?><?= $hasUnpriced ? ' *' : '' ?></td>
                                <td class="mono"><?= $rate > 0 ? 'Rp' . e(number_format($sumUsd * $rate, 0, ',', '.')) : '—' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php if ($hasUnpriced): ?>
                <p class="ws-meta">* Sebagian model belum memiliki harga token — lengkapi di <a href="<?= e(APP_BASE) ?>/admin/ai.php">Pool AI</a> agar estimasi lengkap.</p>
                <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="dash-section">
                <h2>Panggilan terakhir (<?= $totalCalls ?>)</h2>
                <?php if ($recent === []): ?>
                    <p class="ws-meta">Belum ada panggilan pada rentang ini.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Model</th>
                                <th>Provider</th>
                                <th>Jenis</th>
                                <th>Status</th>
                                <th>Token (in/out)</th>
                                <th>Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $row): ?>
                            <tr>
                                <td class="mono"><?= e(date('d M Y H:i', strtotime((string) $row['created_at']))) ?></td>
                                <td class="mono"><?= e((string) $row['model_id']) ?></td>
                                <td><?= e((string) $row['provider_name']) ?></td>
                                <td><?= ((string) $row['kind']) === 'audio' ? 'Audio' : 'Chat' ?></td>
                                <td><span class="badge <?= ((string) $row['status']) === 'ok' ? 'badge-yellow' : 'badge-orange' ?>"><?= ((string) $row['status']) === 'ok' ? 'Sukses' : 'Gagal' ?></span></td>
                                <td class="mono"><?= (int) $row['tokens_in'] ?> / <?= (int) $row['tokens_out'] ?></td>
                                <td class="mono"><?= (int) $row['latency_ms'] ?> ms</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php page_render(APP_BASE . '/admin/ai-usage.php?rentang=' . e($preset), $page, $totalPages); ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
