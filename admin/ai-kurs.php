<?php
/* Pencatat Keuangan — panel admin: kurs USD→IDR untuk konversi biaya AI (FR-049). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$admin = auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $rateRaw = str_replace(',', '.', trim((string) ($_POST['rate'] ?? '')));
    if (!is_numeric($rateRaw) || (float) $rateRaw <= 0 || (float) $rateRaw > 10000000) {
        flash_set('error', 'Kurs harus berupa angka lebih dari 0 (cth: 15500).');
        redirect('/admin/ai-kurs.php');
    }
    ai_setting_set('usd_idr_rate', number_format((float) $rateRaw, 2, '.', ''));
    flash_set('ok', 'Kurs USD→IDR diperbarui menjadi Rp' . number_format((float) $rateRaw, 0, ',', '.') . ' / USD.');
    redirect('/admin/ai-kurs.php');
}

$rate = ai_usd_idr_rate();
$updatedAt = ai_setting_updated('usd_idr_rate');
$rateInput = $rate > 0 ? rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') : '';

$page_title = 'Kurs USD→IDR — Panel Admin';
$page_desc = 'Pengaturan kurs untuk konversi estimasi biaya token AI ke Rupiah.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require dirname(__DIR__) . '/includes/head.php'; ?>
</head>
<body>
    <?php require dirname(__DIR__) . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap auth-wrap">
            <section class="card auth-card">
                <div>
                    <h1>Kurs USD→IDR</h1>
                    <p class="lead">Kurs ini dipakai untuk konversi estimasi biaya token AI (USD) ke Rupiah pada halaman Usage.</p>
                </div>
                <p class="ws-meta">Kurs aktif:
                    <strong><?= $rate > 0 ? 'Rp' . e(number_format($rate, 0, ',', '.')) . ' / USD' : 'belum diatur' ?></strong>
                    <?php if ($updatedAt !== ''): ?>
                    <span>· diperbarui <?= e(date('d M Y H:i', strtotime($updatedAt))) ?></span>
                    <?php endif; ?>
                </p>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/admin/ai-kurs.php" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="rate">Nilai kurs (Rupiah per 1 USD)</label>
                        <input class="input" type="number" id="rate" name="rate" min="1" max="10000000" step="0.01" required value="<?= e($rateInput) ?>" placeholder="cth: 15500">
                        <span class="field-hint">Contoh: isi <span class="mono">15500</span> bila 1 USD = Rp15.500.</span>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan kurs</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/admin/ai-usage.php">Lihat Usage &amp; Biaya</a> · <a href="<?= e(APP_BASE) ?>/admin/">Panel Admin</a></p>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
