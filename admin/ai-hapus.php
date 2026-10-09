<?php
/* Pencatat Keuangan — panel admin: konfirmasi hapus provider/model pool AI. */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$admin = auth_require_admin();

$jenis = (string) ($_GET['jenis'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

// Pertahankan posisi halaman Pool AI saat kembali.
$backQuery = http_build_query(array_filter([
    'hal' => (int) ($_GET['hal'] ?? 0) > 1 ? (int) $_GET['hal'] : null,
    'halp' => (int) ($_GET['halp'] ?? 0) > 1 ? (int) $_GET['halp'] : null,
]));
$backUrl = '/admin/ai.php' . ($backQuery !== '' ? '?' . $backQuery : '');

if ($jenis === 'provider') {
    $provider = $id > 0 ? ai_provider_get($id) : null;
    if ($provider === null) {
        flash_set('error', 'Provider tidak ditemukan.');
        redirect($backUrl);
    }
    $modelCount = (int) db()->query('SELECT COUNT(*) AS c FROM ai_models WHERE provider_id = ' . $id)->fetch()['c'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_require();
        ai_provider_delete($id);
        flash_set('ok', 'Provider "' . (string) $provider['name'] . '" beserta ' . $modelCount . ' model di dalamnya dihapus.');
        redirect($backUrl);
    }

    $judul = 'Hapus provider?';
    $pesan = 'Provider <strong>' . e((string) $provider['name']) . '</strong> (' . e((string) $provider['base_url']) . ')'
        . ' akan dihapus permanen' . ($modelCount > 0 ? ' beserta <strong>' . $modelCount . ' model</strong> di dalamnya' : '') . '.';
} elseif ($jenis === 'model') {
    $model = $id > 0 ? ai_model_get($id) : null;
    if ($model === null) {
        flash_set('error', 'Model tidak ditemukan.');
        redirect($backUrl);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_require();
        ai_model_delete($id);
        flash_set('ok', 'Model "' . (string) $model['model_id'] . '" dihapus dari pool.');
        redirect($backUrl);
    }

    $judul = 'Hapus model?';
    $pesan = 'Model <strong class="mono">' . e((string) $model['model_id']) . '</strong> (provider ' . e((string) $model['provider_name']) . ') akan dihapus dari pool fallback.';
} else {
    flash_set('error', 'Jenis penghapusan tidak dikenali.');
    redirect($backUrl);
}

$page_title = $judul . ' — Panel Admin';
$page_desc = 'Konfirmasi penghapusan pada pool AI.';
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
                    <h1><?= e($judul) ?></h1>
                    <p class="lead"><?= $pesan ?> Tindakan ini <strong>tidak dapat dibatalkan</strong>.</p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/admin/ai-hapus.php?jenis=<?= e($jenis) ?>&amp;id=<?= $id ?><?= $backQuery !== '' ? '&amp;' . e($backQuery) : '' ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i> Ya, hapus</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE . $backUrl) ?>">Batal, kembali ke Pool AI</a></p>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
