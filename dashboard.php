<?php
/* Pencatat Keuangan — dashboard: undangan, daftar workspace, buat workspace (Alur 2 PRD). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';

$user = auth_require_login();
$uid = (int) $user['id'];

$invites = ws_invites_for_user($uid);
$owned = ws_list_owned($uid);
$joined = ws_list_joined($uid);

$page_title = 'Dashboard — Pencatat Keuangan';
$page_desc = 'Daftar workspace dan undangan kolaborasi Anda.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main">
        <div class="wrap dash">
            <div class="dash-head">
                <div>
                    <h1>Halo, <?= e((string) $user['name']) ?></h1>
                    <p class="lead">Kelola workspace keuangan Anda di sini.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/pengaturan.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Pengaturan akun</a>
            </div>

            <?php if ($invites !== []): ?>
            <section class="dash-section">
                <h2>Undangan menunggu</h2>
                <?php foreach ($invites as $inv): ?>
                <div class="card invite-card">
                    <div>
                        <div><strong><?= e((string) $inv['workspace_name']) ?></strong></div>
                        <div class="ws-meta">Diundang oleh @<?= e((string) $inv['inviter_username']) ?> · <?= e(date('d M Y', strtotime((string) $inv['created_at']))) ?></div>
                    </div>
                    <form class="inline-actions" method="post" action="<?= e(APP_BASE) ?>/undangan.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit" name="aksi" value="terima"><i class="fa-solid fa-check" aria-hidden="true"></i> Terima</button>
                        <button class="btn btn-ghost btn-sm" type="submit" name="aksi" value="tolak">Tolak</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <section class="dash-section">
                <h2>Workspace saya</h2>
                <?php if ($owned === []): ?>
                    <p class="ws-meta">Belum ada workspace milik Anda — buat yang pertama di bawah.</p>
                <?php else: ?>
                <div class="dash-grid">
                    <?php foreach ($owned as $ws): ?>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>"><?= e((string) $ws['name']) ?></a></h3>
                        <div class="ws-meta">
                            <span class="badge badge-orange">Pemilik</span>
                            <span>Dibuat <?= e(date('d M Y', strtotime((string) $ws['created_at']))) ?></span>
                        </div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">Buka</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <section class="dash-section">
                <h2>Kolaborasi</h2>
                <?php if ($joined === []): ?>
                    <p class="ws-meta">Belum ada workspace kolaborasi. Anda akan melihatnya di sini setelah menerima undangan.</p>
                <?php else: ?>
                <div class="dash-grid">
                    <?php foreach ($joined as $ws): ?>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>"><?= e((string) $ws['name']) ?></a></h3>
                        <div class="ws-meta">
                            <span class="badge badge-yellow">Kolaborator</span>
                            <span>Milik @<?= e((string) $ws['owner_username']) ?></span>
                        </div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>">Buka</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <section class="dash-section">
                <h2>Buat workspace baru</h2>
                <form class="card ws-card" method="post" action="<?= e(APP_BASE) ?>/workspace-baru.php">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="name">Nama workspace</label>
                        <input class="input" type="text" id="name" name="name" maxlength="80" required placeholder="cth: Keuangan Keluarga">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Buat workspace</button>
                </form>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
