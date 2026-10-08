<?php
/* Pencatat Keuangan — ubah transaksi (FR-021; perubahan tercatat di log FR-032). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';
require __DIR__ . '/includes/transaksi.php';

$user = auth_require_login();
$uid = (int) $user['id'];
$username = (string) $user['username'];

$txId = (int) ($_GET['id'] ?? 0);
$tx = $txId > 0 ? tx_get($txId) : null;
if ($tx === null) {
    flash_set('error', 'Transaksi tidak ditemukan.');
    redirect('/dashboard.php');
}
$wsId = (int) $tx['workspace_id'];
$ws = ws_get($wsId);
$role = $ws !== null ? ws_role_for_user($wsId, $uid) : null;
if ($ws === null || $role === null) {
    flash_set('error', 'Anda tidak memiliki akses ke transaksi ini.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $date = trim((string) ($_POST['tx_date'] ?? ''));
    $type = ((string) ($_POST['type'] ?? '')) === 'masuk' ? 'masuk' : 'keluar';
    $amount = (int) preg_replace('/\D/', '', (string) ($_POST['amount'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
        flash_set('error', 'Tanggal tidak valid.');
    } elseif ($amount <= 0 || $amount > 999999999999) {
        flash_set('error', 'Nominal harus berupa angka lebih dari 0.');
    } elseif ($description === '' || mb_strlen($description) > 200) {
        flash_set('error', 'Deskripsi wajib diisi (maksimal 200 karakter).');
    } else {
        tx_update($wsId, $uid, $username, $txId, $date, $type, $amount, $description);
        flash_set('ok', 'Transaksi diperbarui.');
    }
    redirect('/transaksi.php?id=' . $wsId);
}

$page_title = 'Ubah Transaksi — ' . (string) $ws['name'];
$page_desc = 'Ubah transaksi workspace.';
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
                    <h1>Ubah transaksi</h1>
                    <p class="lead">Workspace: <?= e((string) $ws['name']) ?></p>
                </div>
                <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/transaksi-ubah.php?id=<?= (int) $tx['id'] ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="tx_date">Tanggal</label>
                        <input class="input" type="date" id="tx_date" name="tx_date" required value="<?= e((string) $tx['tx_date']) ?>">
                    </div>
                    <div class="field">
                        <label for="type">Jenis transaksi</label>
                        <select class="select" id="type" name="type" required>
                            <option value="keluar"<?= $tx['type'] === 'keluar' ? ' selected' : '' ?>>Pengeluaran (keluar)</option>
                            <option value="masuk"<?= $tx['type'] === 'masuk' ? ' selected' : '' ?>>Pemasukan (masuk)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="amount">Nominal (Rupiah)</label>
                        <input class="input" type="number" id="amount" name="amount" min="1" step="1" inputmode="numeric" required value="<?= (int) $tx['amount'] ?>">
                    </div>
                    <div class="field">
                        <label for="description">Deskripsi</label>
                        <input class="input" type="text" id="description" name="description" maxlength="200" required value="<?= e((string) $tx['description']) ?>">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan perubahan</button>
                </form>
                <p class="form-note"><a href="<?= e(APP_BASE) ?>/transaksi.php?id=<?= (int) $ws['id'] ?>">Batal, kembali ke daftar transaksi</a></p>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
