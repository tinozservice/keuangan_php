<?php
/* Pencatat Keuangan — ubah nama rekening (CRUD Rekening). */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workspace.php';
require_once __DIR__ . '/includes/rekening.php';

$user = auth_require_login();
$uid = (int) $user['id'];
$username = (string) $user['username'];

$accId = (int) ($_GET['id'] ?? 0);
$acc = $accId > 0 ? rek_get($accId) : null;
if ($acc === null) {
    flash_set('error', 'Rekening tidak ditemukan.');
    redirect('/dashboard.php');
}
$wsId = (int) $acc['workspace_id'];
$ws = ws_get($wsId);
$role = $ws !== null ? ws_role_for_user($wsId, $uid) : null;
if ($ws === null || $role === null) {
    flash_set('error', 'Anda tidak memiliki akses ke rekening ini.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    [$ok, $message] = rek_update($wsId, $uid, $username, $accId, (string) ($_POST['nama_rekening'] ?? ''));
    flash_set($ok ? 'ok' : 'error', $message);
    redirect('/workspace.php?id=' . $wsId);
}

$page_title = 'Ubah Rekening — ' . (string) $ws['name'];
$page_desc = 'Ubah nama rekening workspace.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap auth-wrap">
            <section class="card auth-card">
                <div>
                    <h1>Ubah rekening</h1>
                    <p class="lead">Workspace: <?= e((string) $ws['name']) ?></p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/rekening-ubah.php?id=<?= (int) $acc['id'] ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="nama_rekening">Nama rekening</label>
                        <input class="input" type="text" id="nama_rekening" name="nama_rekening" maxlength="60" required value="<?= e((string) $acc['name']) ?>">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan nama</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">Batal, kembali ke workspace</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
