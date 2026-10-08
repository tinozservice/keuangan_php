<?php
/* Pencatat Keuangan — panel admin: kelola pengguna (FR-036–FR-038 + atur status & peran). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/pagination.php';

$admin = auth_require_admin();
$adminId = (int) $admin['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'simpan') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $isVerified = ((string) ($_POST['is_verified'] ?? '0')) === '1' ? 1 : 0;
        $role = ((string) ($_POST['role'] ?? 'user')) === 'admin' ? 'admin' : 'user';

        $st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $st->execute([$targetId]);
        $target = $st->fetch();

        if ($target === false) {
            flash_set('error', 'Pengguna tidak ditemukan.');
        } elseif ($targetId === $adminId) {
            flash_set('error', 'Tidak dapat mengubah status/peran akun sendiri.');
        } else {
            $verifiedAt = $isVerified === 1
                ? ((string) ($target['verified_at'] ?? '') !== '' ? (string) $target['verified_at'] : date('Y-m-d H:i:s'))
                : null;
            db()->prepare('UPDATE users SET is_verified = ?, verified_at = ?, role = ? WHERE id = ?')
                ->execute([$isVerified, $verifiedAt, $role, $targetId]);
            flash_set('ok', 'Akun @' . (string) $target['username'] . ' diperbarui.');
        }
        redirect('/admin/user.php');
    }

    flash_set('error', 'Aksi tidak dikenali.');
    redirect('/admin/user.php');
}

$page = page_current();
$totalUsers = (int) db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
$totalPages = page_total($totalUsers);
$page = min($page, $totalPages);
$st = db()->prepare('SELECT id, name, username, email, is_verified, role FROM users ORDER BY id DESC LIMIT ' . PER_PAGE . ' OFFSET ' . page_offset($page));
$st->execute();
$users = $st->fetchAll();

$page_title = 'Kelola Pengguna — Panel Admin';
$page_desc = 'Daftar akun terdaftar: status verifikasi, peran, dan penghapusan.';
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
                    <h1>Kelola Pengguna</h1>
                    <p class="lead"><?= $totalUsers ?> akun terdaftar. Nama, username, dan email bersifat baca-saja.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Panel Admin</a>
            </div>

            <section class="dash-section">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Status Verifikasi</th>
                                <th>Role</th>
                                <th>Opsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <?php
                            $rowId = (int) $u['id'];
                            $isSelf = $rowId === $adminId;
                            $formId = 'baris-' . $rowId;
                            ?>
                            <tr>
                                <td><?= e((string) $u['name']) ?><?php if ($isSelf): ?> <span class="badge badge-yellow">Anda</span><?php endif; ?></td>
                                <td class="mono">@<?= e((string) $u['username']) ?></td>
                                <td class="mono"><?= e((string) $u['email']) ?></td>
                                <td>
                                    <?php if ($isSelf): ?>
                                        <span class="badge <?= ((int) $u['is_verified'] === 1) ? 'badge-yellow' : 'badge-orange' ?>"><?= ((int) $u['is_verified'] === 1) ? 'Terverifikasi' : 'Belum terverifikasi' ?></span>
                                    <?php else: ?>
                                        <select class="select" name="is_verified" form="<?= e($formId) ?>" aria-label="Status verifikasi">
                                            <option value="1"<?= ((int) $u['is_verified'] === 1) ? ' selected' : '' ?>>Terverifikasi</option>
                                            <option value="0"<?= ((int) $u['is_verified'] === 0) ? ' selected' : '' ?>>Belum terverifikasi</option>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isSelf): ?>
                                        <span class="badge <?= ($u['role'] === 'admin') ? 'badge-orange' : 'badge-yellow' ?>"><?= e((string) $u['role']) ?></span>
                                    <?php else: ?>
                                        <select class="select" name="role" form="<?= e($formId) ?>" aria-label="Role pengguna">
                                            <option value="user"<?= ($u['role'] === 'user') ? ' selected' : '' ?>>user</option>
                                            <option value="admin"<?= ($u['role'] === 'admin') ? ' selected' : '' ?>>admin</option>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isSelf): ?>
                                        <span class="ws-meta">—</span>
                                    <?php else: ?>
                                        <div class="row-form">
                                            <form id="<?= e($formId) ?>" method="post" action="<?= e(APP_BASE) ?>/admin/user.php">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="aksi" value="simpan">
                                                <input type="hidden" name="user_id" value="<?= $rowId ?>">
                                            </form>
                                            <button class="btn btn-ghost btn-sm" type="submit" form="<?= e($formId) ?>"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan</button>
                                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/user-hapus.php?id=<?= $rowId ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus…</a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php page_render(APP_BASE . '/admin/user.php', $page, $totalPages); ?>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
