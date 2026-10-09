<?php
/* Pencatat Keuangan — panel admin: beranda. */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$user = auth_require_admin();

// ---------------- Ringkasan admin (FR-061) ----------------
$pdo = db();

$userStat = $pdo->query("SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END), 0) AS verified,
        COALESCE(SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END), 0) AS admins
    FROM users")->fetch();
$userTotal = (int) $userStat['total'];
$userVerified = (int) $userStat['verified'];
$userAdmins = (int) $userStat['admins'];
$recentUsers = $pdo->query('SELECT name, username, email, role, is_verified, created_at FROM users ORDER BY id DESC LIMIT 5')->fetchAll();

$wsTotal = (int) $pdo->query('SELECT COUNT(*) AS c FROM workspaces')->fetch()['c'];
$memberTotal = (int) $pdo->query('SELECT COUNT(*) AS c FROM workspace_members')->fetch()['c'];
$invitePending = (int) $pdo->query("SELECT COUNT(*) AS c FROM workspace_invitations WHERE status = 'pending'")->fetch()['c'];

$txStat = $pdo->query("SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN type = 'masuk' THEN amount ELSE 0 END), 0) AS masuk,
        COALESCE(SUM(CASE WHEN type = 'keluar' THEN amount ELSE 0 END), 0) AS keluar
    FROM transactions")->fetch();
$txTotal = (int) $txStat['total'];

$pool = ai_pool_summary();
$poolErrors = $pdo->query("SELECT m.model_id, m.label, m.is_active, m.health_detail, m.health_checked_at, p.name AS provider_name
        FROM ai_models m
        JOIN ai_providers p ON p.id = m.provider_id
        WHERE m.health_status = 'error'
        ORDER BY m.health_checked_at DESC, m.priority ASC, m.model_id ASC
        LIMIT 5")->fetchAll();

/** Jumlahkan baris agregat usage (chat + audio) untuk kartu ringkasan. */
$usageAgg = static function (array $rows): array {
    $agg = ['calls' => 0, 'ok' => 0, 'usd' => 0.0, 'unpriced' => false];
    foreach ($rows as $row) {
        $agg['calls'] += (int) $row['calls'];
        $agg['ok'] += (int) $row['calls_ok'];
        if ($row['usd'] === null) {
            $agg['unpriced'] = true;
        } else {
            $agg['usd'] += (float) $row['usd'];
        }
    }
    return $agg;
};
[$usageFromMonth, $usageToMonth] = ai_usage_range('bulan-ini');
$usageMonth = $usageAgg(ai_usage_by_model($usageFromMonth, $usageToMonth));
[$usageFromWeek, $usageToWeek] = ai_usage_range('7-hari');
$usageWeek = $usageAgg(ai_usage_by_model($usageFromWeek, $usageToWeek));
$usdRate = ai_usd_idr_rate();

$failStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE created_at >= ?');
$failStmt->execute([date('Y-m-d H:i:s', time() - 86400)]);
$fail24h = (int) $failStmt->fetch()['c'];
$unknownStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE user_id IS NULL AND created_at >= ?');
$unknownStmt->execute([date('Y-m-d H:i:s', time() - 86400)]);
$unknown24h = (int) $unknownStmt->fetch()['c'];
$lockedStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM (
        SELECT CASE WHEN user_id IS NOT NULL THEN 'u:' || user_id ELSE 'i:' || identifier END AS scope_key, COUNT(*) AS n
        FROM login_attempts
        WHERE created_at >= ?
        GROUP BY scope_key
    ) AS g WHERE g.n >= ?");
$lockedStmt->execute([date('Y-m-d H:i:s', time() - AUTH_LOGIN_LOCK_WINDOW), AUTH_LOGIN_MAX_FAILURES]);
$lockedNow = (int) $lockedStmt->fetch()['c'];
$recentFails = $pdo->query('SELECT identifier, user_id, ip_address, created_at FROM login_attempts ORDER BY id DESC LIMIT 5')->fetchAll();

$page_title = 'Panel Admin — Pencatat Keuangan';
$page_desc = 'Panel admin Pencatat Keuangan.';
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
                    <h1>Panel Admin</h1>
                    <p class="lead">Masuk sebagai @<?= e((string) $user['username']) ?>.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/dashboard.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Dashboard</a>
            </div>

            <section class="dash-section">
                <h2>Ringkasan</h2>
                <div class="stack">
                    <div class="stat-grid">
                        <div class="card ws-card">
                            <div class="ws-meta">Akun pengguna</div>
                            <div class="stat-value"><?= number_format($userTotal, 0, ',', '.') ?></div>
                            <div class="ws-meta">
                                <span><?= $userVerified ?> terverifikasi · <?= $userTotal - $userVerified ?> belum</span>
                                <span><?= $userAdmins ?> admin · <?= $userTotal - $userAdmins ?> pengguna</span>
                            </div>
                        </div>
                        <div class="card ws-card">
                            <div class="ws-meta">Workspace</div>
                            <div class="stat-value"><?= number_format($wsTotal, 0, ',', '.') ?></div>
                            <div class="ws-meta">
                                <span><?= $memberTotal ?> anggota</span>
                                <span><?= $invitePending ?> undangan menunggu</span>
                            </div>
                        </div>
                        <div class="card ws-card">
                            <div class="ws-meta">Transaksi (semua workspace)</div>
                            <div class="stat-value"><?= number_format($txTotal, 0, ',', '.') ?></div>
                            <div class="ws-meta">
                                <span>+<?= e(rupiah((int) $txStat['masuk'])) ?></span>
                                <span>-<?= e(rupiah((int) $txStat['keluar'])) ?></span>
                            </div>
                        </div>
                        <div class="card ws-card">
                            <div class="ws-meta">Pool AI</div>
                            <div class="stat-value"><?= $pool['active'] ?>/<?= $pool['models'] ?></div>
                            <div class="ws-meta">
                                <span><?= $pool['providers'] ?> provider</span>
                                <span><?= $pool['error'] ?> bermasalah</span>
                                <span><?= $pool['unchecked'] ?> belum diperiksa</span>
                            </div>
                        </div>
                        <div class="card ws-card">
                            <div class="ws-meta">Usage AI — bulan ini</div>
                            <div class="stat-value">$<?= e(ai_usd_display($usageMonth['usd'])) ?><?= $usageMonth['unpriced'] ? '*' : '' ?></div>
                            <div class="ws-meta">
                                <?php if ($usdRate > 0): ?>
                                <span>≈ Rp<?= e(number_format($usageMonth['usd'] * $usdRate, 0, ',', '.')) ?></span>
                                <?php else: ?>
                                <span><a href="<?= e(APP_BASE) ?>/admin/ai-kurs.php">Atur kurs</a> untuk Rupiah</span>
                                <?php endif; ?>
                                <span>7 hari: $<?= e(ai_usd_display($usageWeek['usd'])) ?></span>
                                <span><?= $usageMonth['calls'] ?> panggilan · <?= $usageMonth['calls'] - $usageMonth['ok'] ?> gagal</span>
                            </div>
                        </div>
                        <div class="card ws-card">
                            <div class="ws-meta">Keamanan login (24 jam)</div>
                            <div class="stat-value"><?= number_format($fail24h, 0, ',', '.') ?></div>
                            <div class="ws-meta">
                                <span><?= $lockedNow ?> terkunci saat ini</span>
                                <span><?= $unknown24h ?> identifier tak dikenal</span>
                                <span><a href="<?= e(APP_BASE) ?>/admin/login-gagal.php">Percobaan Masuk</a></span>
                            </div>
                        </div>
                    </div>

                    <div class="dash-grid dash-grid--auto">
                        <div class="card ws-card">
                            <h3>Pendaftaran terbaru</h3>
                            <div class="member-list">
                                <?php foreach ($recentUsers as $row): ?>
                                <div class="member-row">
                                    <div>
                                        <div><?= e((string) $row['name']) ?></div>
                                        <div class="ws-meta"><?= e((string) ($row['username'] ?? '') !== '' ? '@' . (string) $row['username'] : (string) $row['email']) ?></div>
                                    </div>
                                    <div class="ws-meta">
                                        <span class="badge <?= $row['role'] === 'admin' ? 'badge-orange' : 'badge-yellow' ?>"><?= $row['role'] === 'admin' ? 'Admin' : 'Pengguna' ?></span>
                                        <span class="badge <?= (int) $row['is_verified'] === 1 ? 'badge-yellow' : 'badge-orange' ?>"><?= (int) $row['is_verified'] === 1 ? 'Terverifikasi' : 'Belum terverifikasi' ?></span>
                                        <span><?= e(date('d M Y', strtotime((string) $row['created_at']))) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ws-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/user.php">Kelola Pengguna</a>
                            </div>
                        </div>

                        <div class="card ws-card">
                            <h3>Model AI bermasalah</h3>
                            <?php if ($poolErrors === []): ?>
                            <p class="ws-meta"><?= $pool['models'] > 0 ? 'Tidak ada model berstatus error dari pemeriksaan terakhir.' : 'Belum ada model terdaftar di pool.' ?></p>
                            <?php else: ?>
                            <div class="member-list">
                                <?php foreach ($poolErrors as $row): ?>
                                <div class="member-row">
                                    <div>
                                        <div class="mono"><?= e((string) $row['model_id']) ?></div>
                                        <div class="ws-meta"><?= e((string) $row['provider_name']) ?><?= (int) $row['is_active'] === 0 ? ' · nonaktif' : '' ?></div>
                                    </div>
                                    <div class="ws-meta">
                                        <?php $detail = trim((string) $row['health_detail']); ?>
                                        <?php if ($detail !== ''): ?>
                                        <span title="<?= e($detail) ?>"><?= e(mb_substr($detail, 0, 90)) ?><?= mb_strlen($detail) > 90 ? '…' : '' ?></span>
                                        <?php endif; ?>
                                        <?php if (($row['health_checked_at'] ?? null) !== null): ?>
                                        <span><?= e(date('d M Y H:i', strtotime((string) $row['health_checked_at']))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="ws-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai.php">Pool AI</a>
                            </div>
                        </div>

                        <div class="card ws-card">
                            <h3>Percobaan masuk gagal terbaru</h3>
                            <?php if ($recentFails === []): ?>
                            <p class="ws-meta">Tidak ada percobaan masuk gagal tercatat.</p>
                            <?php else: ?>
                            <div class="member-list">
                                <?php foreach ($recentFails as $row): ?>
                                <div class="member-row">
                                    <div>
                                        <div class="mono"><?= e((string) $row['identifier']) ?></div>
                                        <div class="ws-meta"><?= $row['user_id'] !== null ? 'Akun terdaftar' : 'Identifier tak dikenal' ?></div>
                                    </div>
                                    <div class="ws-meta">
                                        <span class="mono"><?= e((string) ($row['ip_address'] ?? '—')) ?></span>
                                        <span><?= e(date('d M Y H:i', strtotime((string) $row['created_at']))) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="ws-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/login-gagal.php">Percobaan Masuk</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dash-section">
                <h2>Modul tersedia</h2>
                <div class="dash-grid">
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/user.php">Kelola Pengguna</a></h3>
                        <div class="ws-meta">Daftar akun, atur status verifikasi &amp; peran, serta hapus akun dengan konfirmasi.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/user.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/login-gagal.php">Percobaan Masuk</a></h3>
                        <div class="ws-meta">Log percobaan masuk ke identifier yang belum terdaftar, lengkap dengan lokasi &amp; IP address.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/login-gagal.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/ai.php">Pool AI</a></h3>
                        <div class="ws-meta">Provider OpenAI-compatible, model fallback (kapabilitas &amp; harga token), urutan prioritas, dan pemeriksaan kesehatan massal.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/ai-usage.php">Usage &amp; Biaya</a></h3>
                        <div class="ws-meta">Pemakaian token per model, estimasi biaya USD (+Rupiah), dan riwayat panggilan model.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-usage.php">Buka</a>
                        </div>
                    </article>
                    <article class="card ws-card">
                        <h3><a href="<?= e(APP_BASE) ?>/admin/ai-kurs.php">Kurs USD→IDR</a></h3>
                        <div class="ws-meta">Nilai kurs untuk konversi estimasi biaya token AI ke Rupiah.</div>
                        <div class="ws-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/admin/ai-kurs.php">Buka</a>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>

    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
