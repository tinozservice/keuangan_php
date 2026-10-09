<?php
/* Pencatat Keuangan — pengaturan akun: ubah nama tampilan (FR-009), kata sandi (FR-011), & buat kata sandi akun Google (FR-058). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';

$user = auth_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'nama') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            flash_set('error', 'Nama tampilan harus 2–80 karakter.');
        } else {
            db()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, (int) $user['id']]);
            flash_set('ok', 'Nama tampilan diperbarui.');
        }
        redirect('/pengaturan.php');
    }

    if ($aksi === 'sandi') {
        $lama = (string) ($_POST['sandi_lama'] ?? '');
        $baru = (string) ($_POST['sandi_baru'] ?? '');
        $ulang = (string) ($_POST['sandi_ulang'] ?? '');

        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $st->execute([(int) $user['id']]);
        $row = $st->fetch();

        if ($row === false || !password_verify($lama, (string) $row['password_hash'])) {
            flash_set('error', 'Kata sandi lama tidak cocok.');
        } elseif (strlen($baru) < 8) {
            flash_set('error', 'Kata sandi baru minimal 8 karakter.');
        } elseif ($baru !== $ulang) {
            flash_set('error', 'Ulangi kata sandi baru tidak cocok.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ?, has_password = 1 WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), (int) $user['id']]);
            flash_set('ok', 'Kata sandi diperbarui.');
        }
        redirect('/pengaturan.php');
    }

    if ($aksi === 'buat_sandi') {
        $baru = (string) ($_POST['sandi_baru'] ?? '');
        $ulang = (string) ($_POST['sandi_ulang'] ?? '');

        $st = db()->prepare('SELECT has_password FROM users WHERE id = ? LIMIT 1');
        $st->execute([(int) $user['id']]);

        if ((int) $st->fetchColumn() === 1) {
            flash_set('error', 'Akun Anda sudah memiliki kata sandi. Gunakan formulir Ganti kata sandi.');
        } elseif (strlen($baru) < 8) {
            flash_set('error', 'Kata sandi baru minimal 8 karakter.');
        } elseif ($baru !== $ulang) {
            flash_set('error', 'Ulangi kata sandi baru tidak cocok.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ?, has_password = 1 WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), (int) $user['id']]);
            flash_set('ok', 'Kata sandi berhasil dibuat. Anda kini dapat masuk dengan email & kata sandi.');
        }
        redirect('/pengaturan.php');
    }

    redirect('/pengaturan.php');
}

$page_title = 'Pengaturan Akun — Pencatat Keuangan';
$page_desc = 'Ubah nama tampilan dan kata sandi akun Anda.';
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
            <div class="stack">
                <section class="card auth-card">
                    <div>
                        <h1>Profil</h1>
                        <p class="lead">Username dan email tidak dapat diubah.</p>
                    </div>
                    <ul class="meta-list">
                        <li><span>Username</span><span class="mono">@<?= e((string) $user['username']) ?></span></li>
                        <li><span>Email</span><span class="mono"><?= e((string) $user['email']) ?></span></li>
                    </ul>
                    <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/pengaturan.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="nama">
                        <div class="field">
                            <label for="name">Nama tampilan</label>
                            <input class="input" type="text" id="name" name="name" minlength="2" maxlength="80" required value="<?= e((string) $user['name']) ?>">
                            <span class="field-hint">Nama ini tampil di dashboard dan daftar anggota workspace.</span>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan nama</button>
                    </form>
                </section>

                <?php if ((int) ($user['has_password'] ?? 1) === 0): ?>
                <section class="card auth-card">
                    <div>
                        <h1>Buat kata sandi</h1>
                        <p class="lead">Akun Anda dibuat melalui Google sehingga belum memiliki kata sandi. Buat kata sandi agar dapat masuk tanpa Google (email & kata sandi).</p>
                    </div>
                    <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/pengaturan.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="buat_sandi">
                        <div class="field">
                            <label for="sandi_baru">Kata sandi baru</label>
                            <input class="input" type="password" id="sandi_baru" name="sandi_baru" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label for="sandi_ulang">Ulangi kata sandi baru</label>
                            <input class="input" type="password" id="sandi_ulang" name="sandi_ulang" minlength="8" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-key" aria-hidden="true"></i> Buat kata sandi</button>
                    </form>
                </section>
                <?php else: ?>
                <section class="card auth-card">
                    <div>
                        <h1>Ganti kata sandi</h1>
                        <p class="lead">Masukkan kata sandi lama untuk mengonfirmasi.</p>
                    </div>
                    <form class="auth-form" method="post" action="<?= e(APP_BASE) ?>/pengaturan.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="sandi">
                        <div class="field">
                            <label for="sandi_lama">Kata sandi lama</label>
                            <input class="input" type="password" id="sandi_lama" name="sandi_lama" required autocomplete="current-password">
                        </div>
                        <div class="field">
                            <label for="sandi_baru">Kata sandi baru</label>
                            <input class="input" type="password" id="sandi_baru" name="sandi_baru" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label for="sandi_ulang">Ulangi kata sandi baru</label>
                            <input class="input" type="password" id="sandi_ulang" name="sandi_ulang" minlength="8" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-key" aria-hidden="true"></i> Perbarui kata sandi</button>
                    </form>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
