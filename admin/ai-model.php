<?php
/* Pencatat Keuangan — panel admin: tambah/ubah model pool AI (FR-040–FR-042, FR-046). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$admin = auth_require_admin();

$id = (int) ($_GET['id'] ?? 0);
$model = $id > 0 ? ai_model_get($id) : null;
if ($id > 0 && $model === null) {
    flash_set('error', 'Model tidak ditemukan.');
    redirect('/admin/ai.php');
}

$providers = ai_provider_list();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $capsIn = array_map('strval', (array) ($_POST['caps_in'] ?? []));
    $capsOut = array_map('strval', (array) ($_POST['caps_out'] ?? []));
    $priorityRaw = trim((string) ($_POST['priority'] ?? ''));
    [$ok, $message] = ai_model_save(
        $id > 0 ? $id : null,
        (int) ($_POST['provider_id'] ?? 0),
        (string) ($_POST['model_id'] ?? ''),
        (string) ($_POST['label'] ?? ''),
        $capsIn,
        $capsOut,
        (float) str_replace(',', '.', (string) ($_POST['price_in'] ?? '0')),
        (float) str_replace(',', '.', (string) ($_POST['price_out'] ?? '0')),
        ((string) ($_POST['is_active'] ?? '0')) === '1',
        $priorityRaw === '' ? null : (int) $priorityRaw
    );
    flash_set($ok ? 'ok' : 'error', $message);
    redirect($ok ? '/admin/ai.php' : ('/admin/ai-model.php' . ($id > 0 ? '?id=' . $id : '')));
}

$isEdit = $model !== null;
$needProvider = $providers === [] && !$isEdit;
$selectedProvider = $isEdit ? (int) $model['provider_id'] : (int) ($providers[0]['id'] ?? 0);
$capsInSelected = $isEdit ? ai_model_cap_labels($model, 'input') : [];
$capsOutSelected = $isEdit ? ai_model_cap_labels($model, 'output') : [];
$capsInKeys = array_keys(ai_input_caps());
$capsOutKeys = array_keys(ai_output_caps());

$page_title = ($isEdit ? 'Ubah' : 'Tambah') . ' Model AI — Panel Admin';
$page_desc = 'Model fallback pool AI: kapabilitas input/output, harga token, dan status aktif.';
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
                    <h1><?= $isEdit ? 'Ubah model' : 'Tambah model' ?></h1>
                    <p class="lead">Model baru ditambahkan di urutan paling bawah; urutkan di halaman Pool AI.</p>
                </div>

                <?php if ($needProvider): ?>
                    <p class="flash flash-info">Belum ada provider. Tambahkan provider terlebih dahulu.</p>
                    <p class="form-note"><a href="<?= e(APP_BASE) ?>/admin/ai-provider.php">Tambah provider</a></p>
                <?php else: ?>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/admin/ai-model.php<?= $isEdit ? '?id=' . (int) $model['id'] : '' ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="provider_id">Provider</label>
                        <select class="select" id="provider_id" name="provider_id" required>
                            <?php foreach ($providers as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === $selectedProvider ? ' selected' : '' ?>><?= e((string) $p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="model_id">ID model</label>
                        <input class="input" type="text" id="model_id" name="model_id" maxlength="150" required value="<?= $isEdit ? e((string) $model['model_id']) : '' ?>" placeholder="cth: openai/gpt-4o-mini">
                        <span class="field-hint">Nama model persis seperti yang dikenali provider (boleh mengandung garis miring).</span>
                    </div>
                    <div class="field">
                        <label for="label">Label (opsional)</label>
                        <input class="input" type="text" id="label" name="label" maxlength="100" value="<?= $isEdit ? e((string) $model['label']) : '' ?>" placeholder="cth: GPT-4o mini (OpenRouter)">
                    </div>
                    <div class="field">
                        <label for="priority">Prioritas</label>
                        <input class="input" type="number" id="priority" name="priority" min="1" max="1000000" step="1" value="<?= $isEdit ? (int) $model['priority'] : '' ?>" placeholder="otomatis (urutan terakhir)">
                        <span class="field-hint">Urutan daftar = prioritas menaik. Isi angka untuk menyisipkan (cth: <span class="mono">11</span> menempatkan model sebelum prioritas 20). Tombol panah di daftar menormalkan prioritas menjadi 10, 20, 30, …</span>
                    </div>
                    <div class="field">
                        <label>Kapabilitas input</label>
                        <?php foreach (ai_input_caps() as $key => $label): ?>
                        <label class="row-form" style="gap:8px">
                            <input type="checkbox" name="caps_in[]" value="<?= e($key) ?>" <?= in_array($label, $capsInSelected, true) ? 'checked' : '' ?>>
                            <?= e($label) ?>
                        </label>
                        <?php endforeach; ?>
                        <span class="field-hint">Minimal satu kapabilitas input. Audio-saja diuji lewat audio/transcriptions.</span>
                    </div>
                    <div class="field">
                        <label>Kapabilitas output</label>
                        <?php foreach (ai_output_caps() as $key => $label): ?>
                        <label class="row-form" style="gap:8px">
                            <input type="checkbox" name="caps_out[]" value="<?= e($key) ?>" <?= in_array($label, $capsOutSelected, true) ? 'checked' : '' ?>>
                            <?= e($label) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="field">
                        <label for="price_in">Harga input (USD / 1 juta token)</label>
                        <input class="input" type="number" id="price_in" name="price_in" min="0" max="100000" step="0.0001" value="<?= $isEdit ? e((string) (float) $model['price_in']) : '0' ?>">
                    </div>
                    <div class="field">
                        <label for="price_out">Harga output (USD / 1 juta token)</label>
                        <input class="input" type="number" id="price_out" name="price_out" min="0" max="100000" step="0.0001" value="<?= $isEdit ? e((string) (float) $model['price_out']) : '0' ?>">
                    </div>
                    <div class="field">
                        <label class="row-form" style="gap:8px">
                            <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || (int) $model['is_active'] === 1) ? 'checked' : '' ?>>
                            Aktif (ikut urutan percobaan fallback)
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan model</button>
                </form>
                <?php endif; ?>

                <p class="form-note"><a href="<?= e(APP_BASE) ?>/admin/ai.php">Batal, kembali ke Pool AI</a></p>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
