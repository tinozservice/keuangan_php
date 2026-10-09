<?php
/* Pencatat Keuangan — panel admin: tambah/ubah provider AI (FR-039). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$admin = auth_require_admin();

$id = (int) ($_GET['id'] ?? 0);
$provider = $id > 0 ? ai_provider_get($id) : null;
if ($id > 0 && $provider === null) {
    flash_set('error', 'Provider tidak ditemukan.');
    redirect('/admin/ai.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    [$ok, $message] = ai_provider_save(
        $id > 0 ? $id : null,
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['base_url'] ?? ''),
        (string) ($_POST['api_key'] ?? '')
    );
    flash_set($ok ? 'ok' : 'error', $message);
    redirect($ok ? '/admin/ai.php' : ('/admin/ai-provider.php' . ($id > 0 ? '?id=' . $id : '')));
}

$isEdit = $provider !== null;
$page_title = ($isEdit ? 'Ubah' : 'Tambah') . ' Provider AI — Panel Admin';
$page_desc = 'Provider OpenAI-compatible untuk pool fallback AI.';
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
                    <h1><?= $isEdit ? 'Ubah provider' : 'Tambah provider' ?></h1>
                    <p class="lead">Provider harus kompatibel dengan API OpenAI (chat/completions; audio/transcriptions untuk model audio).</p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/admin/ai-provider.php<?= $isEdit ? '?id=' . (int) $provider['id'] : '' ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="name">Nama provider</label>
                        <input class="input" type="text" id="name" name="name" maxlength="80" required value="<?= $isEdit ? e((string) $provider['name']) : '' ?>" placeholder="cth: OpenRouter">
                    </div>
                    <div class="field">
                        <label for="base_url">Base URL</label>
                        <input class="input" type="url" id="base_url" name="base_url" maxlength="200" required value="<?= $isEdit ? e((string) $provider['base_url']) : '' ?>" placeholder="cth: https://openrouter.ai/api/v1">
                        <span class="field-hint">Sertakan <span class="mono">/v1</span> bila provider memakai path tersebut.</span>
                    </div>
                    <div class="field">
                        <label for="api_key">API Key</label>
                        <input class="input" type="password" id="api_key" name="api_key" maxlength="300" autocomplete="off" <?= $isEdit ? '' : 'required' ?>>
                        <?php if ($isEdit): ?>
                        <span class="field-hint">Biarkan kosong bila tidak diubah. Tersimpan saat ini: <span class="mono"><?= e(ai_mask_key((string) $provider['api_key'])) ?></span></span>
                        <?php else: ?>
                        <span class="field-hint">Disimpan di basis data lokal dan tidak pernah ditampilkan utuh.</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan provider</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/admin/ai.php">Batal, kembali ke Pool AI</a></p>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
