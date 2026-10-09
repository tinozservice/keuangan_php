<?php
/* Pencatat Keuangan — daftar & input transaksi workspace (FR-021). */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/workspace.php';
require __DIR__ . '/includes/transaksi.php';
require_once __DIR__ . '/includes/rekening.php';
require_once __DIR__ . '/includes/pagination.php';

$user = auth_require_login();
$uid = (int) $user['id'];
$username = (string) $user['username'];

$id = (int) ($_GET['id'] ?? 0);
$ws = $id > 0 ? ws_get($id) : null;
if ($ws === null) {
    flash_set('error', 'Workspace tidak ditemukan.');
    redirect('/dashboard.php');
}
$role = ws_role_for_user($id, $uid);
if ($role === null) {
    flash_set('error', 'Anda tidak memiliki akses ke workspace ini.');
    redirect('/dashboard.php');
}
$isOwner = $role === 'owner';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'tambah') {
        $date = trim((string) ($_POST['tx_date'] ?? ''));
        $type = ((string) ($_POST['type'] ?? '')) === 'masuk' ? 'masuk' : 'keluar';
        $amount = (int) preg_replace('/\D/', '', (string) ($_POST['amount'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $accountId = (int) ($_POST['account_id'] ?? 0);

        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            flash_set('error', 'Tanggal tidak valid.');
        } elseif ($amount <= 0 || $amount > 999999999999) {
            flash_set('error', 'Nominal harus berupa angka lebih dari 0.');
        } elseif ($description === '' || mb_strlen($description) > 200) {
            flash_set('error', 'Deskripsi wajib diisi (maksimal 200 karakter).');
        } elseif ($accountId > 0 && !rek_exists($id, $accountId)) {
            flash_set('error', 'Rekening tidak valid.');
        } else {
            tx_add($id, $uid, $username, $date, $type, $amount, $description, $accountId > 0 ? $accountId : null);
            flash_set('ok', 'Transaksi ditambahkan.');
        }
        redirect('/transaksi.php?id=' . $id);
    }

    flash_set('error', 'Aksi tidak dikenali.');
    redirect('/transaksi.php?id=' . $id);
}

// Tampilan bawaan (aturan pengguna): seluruh transaksi tanpa filter;
// filter hanya diterapkan bila pengguna mengisi/mencari sendiri.
$filter = tx_filter_from_query($_GET);
$filterExplicit = tx_filter_active($filter);
$filterQuery = tx_filter_query($filter);

$page = page_current();
$totalTx = tx_count($id, $filter);
$totalPages = page_total($totalTx);
$page = min($page, $totalPages);
$transactions = tx_list($id, PER_PAGE, page_offset($page), $filter);
$totals = tx_totals($id, $filter);
$byAccount = tx_totals_by_account($id, $filter);
$accounts = rek_list($id);

$listUrl = APP_BASE . '/transaksi.php?' . http_build_query(array_merge(['id' => $id], $filterQuery));
$exportCsv = APP_BASE . '/transaksi-ekspor.php?' . http_build_query(array_merge(['id' => $id, 'format' => 'csv'], $filterQuery));
$exportXlsx = APP_BASE . '/transaksi-ekspor.php?' . http_build_query(array_merge(['id' => $id, 'format' => 'xlsx'], $filterQuery));

$page_title = 'Transaksi — ' . (string) $ws['name'];
$page_desc = 'Daftar dan input transaksi workspace.';
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
                    <h1>Transaksi</h1>
                    <p class="ws-meta">
                        <span><?= e((string) $ws['name']) ?></span>
                        <span class="badge <?= $isOwner ? 'badge-orange' : 'badge-yellow' ?>"><?= $isOwner ? 'Pemilik' : 'Kolaborator' ?></span>
                    </p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/workspace.php?id=<?= (int) $ws['id'] ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Workspace</a>
            </div>

            <section class="dash-section">
                <?php if ($filterExplicit): ?>
                <p class="ws-meta">Angka mengikuti filter pencarian aktif.</p>
                <?php endif; ?>
                <div class="stat-grid">
                    <div class="card ws-card">
                        <div class="ws-meta">Total masuk</div>
                        <div class="stat-value in">+<?= e(rupiah($totals['masuk'])) ?></div>
                    </div>
                    <div class="card ws-card">
                        <div class="ws-meta">Total keluar</div>
                        <div class="stat-value">-<?= e(rupiah($totals['keluar'])) ?></div>
                    </div>
                    <div class="card ws-card">
                        <div class="ws-meta">Selisih</div>
                        <div class="stat-value"><?= $totals['selisih'] < 0 ? '-' : '+' ?><?= e(rupiah(abs($totals['selisih']))) ?></div>
                    </div>
                </div>
            </section>

            <section class="dash-section">
                <h2>Rincian per rekening</h2>
                <?php if ($filterExplicit): ?>
                <p class="ws-meta">Angka mengikuti filter pencarian aktif.</p>
                <?php endif; ?>
                <?php if ($byAccount === []): ?>
                    <p class="ws-meta">Belum ada transaksi untuk dirinci.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Rekening</th>
                                <th>Masuk</th>
                                <th>Keluar</th>
                                <th>Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byAccount as $row): ?>
                            <tr>
                                <td><?= $row['name'] === null ? 'Tanpa rekening' : e((string) $row['name']) ?></td>
                                <td class="tx-amount in">+<?= e(rupiah($row['masuk'])) ?></td>
                                <td class="tx-amount">-<?= e(rupiah($row['keluar'])) ?></td>
                                <td class="tx-amount"><?= $row['selisih'] < 0 ? '-' : '+' ?><?= e(rupiah(abs($row['selisih']))) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="is-total">
                                <td>Total (semua rekening)</td>
                                <td class="tx-amount in">+<?= e(rupiah($totals['masuk'])) ?></td>
                                <td class="tx-amount">-<?= e(rupiah($totals['keluar'])) ?></td>
                                <td class="tx-amount"><?= $totals['selisih'] < 0 ? '-' : '+' ?><?= e(rupiah(abs($totals['selisih']))) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <section class="dash-section">
                <h2>Tambah transaksi</h2>
                <form class="card ws-card" method="post" action="<?= e(APP_BASE) ?>/transaksi.php?id=<?= (int) $ws['id'] ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="field" data-ai-extract data-endpoint="<?= e(APP_BASE) ?>/transaksi-ekstrak.php?id=<?= (int) $ws['id'] ?>">
                        <label>Input cepat dengan AI (opsional)</label>
                        <div class="inline-actions">
                            <button class="btn btn-ghost btn-sm" type="button" data-ai-record aria-pressed="false"><i class="fa-solid fa-microphone" aria-hidden="true"></i> <span data-ai-record-label>Rekam suara</span></button>
                            <button class="btn btn-ghost btn-sm" type="button" data-ai-photo><i class="fa-solid fa-camera" aria-hidden="true"></i> Foto struk</button>
                            <input class="sr-only" type="file" accept="image/*" capture="environment" data-ai-file tabindex="-1">
                        </div>
                        <span class="field-hint" data-ai-status>Rekam suara atau ambil foto struk — hasil otomatis mengisi isian di bawah dan tetap bisa disunting sebelum disimpan.</span>
                    </div>
                    <div class="field">
                        <label for="tx_date">Tanggal</label>
                        <input class="input" type="date" id="tx_date" name="tx_date" required value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="field">
                        <label for="type">Jenis transaksi</label>
                        <select class="select" id="type" name="type" required>
                            <option value="keluar">Pengeluaran (keluar)</option>
                            <option value="masuk">Pemasukan (masuk)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="account_id">Rekening</label>
                        <select class="select" id="account_id" name="account_id">
                            <option value="0">— Tanpa rekening —</option>
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>"><?= e((string) $acc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($accounts === []): ?>
                        <span class="field-hint">Belum ada rekening — tambahkan di halaman workspace.</span>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="amount">Nominal (Rupiah)</label>
                        <input class="input" type="number" id="amount" name="amount" min="1" step="1" inputmode="numeric" required placeholder="cth: 50000">
                    </div>
                    <div class="field">
                        <label for="description">Deskripsi</label>
                        <input class="input" type="text" id="description" name="description" maxlength="200" required placeholder="cth: Belanja dapur mingguan">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Simpan transaksi</button>
                </form>
            </section>

            <section class="dash-section">
                <h2>Cari transaksi</h2>
                <form class="card ws-card" method="get" action="<?= e(APP_BASE) ?>/transaksi.php">
                    <input type="hidden" name="id" value="<?= (int) $ws['id'] ?>">
                    <div class="field">
                        <label for="dari">Dari tanggal</label>
                        <input class="input" type="date" id="dari" name="dari" value="<?= e((string) $filter['dari']) ?>">
                    </div>
                    <div class="field">
                        <label for="sampai">Sampai tanggal</label>
                        <input class="input" type="date" id="sampai" name="sampai" value="<?= e((string) $filter['sampai']) ?>">
                    </div>
                    <div class="field">
                        <label for="q">Kata kunci deskripsi</label>
                        <input class="input" type="text" id="q" name="q" maxlength="100" value="<?= e((string) $filter['q']) ?>" placeholder="cth: listrik">
                    </div>
                    <div class="field">
                        <label for="rekening">Rekening</label>
                        <select class="select" id="rekening" name="rekening">
                            <option value="0">— Semua rekening —</option>
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>"<?= (int) $filter['rekening'] === (int) $acc['id'] ? ' selected' : '' ?>><?= e((string) $acc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="inline-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Cari</button>
                        <?php if ($filterExplicit): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/transaksi.php?id=<?= (int) $ws['id'] ?>">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="card ws-card" data-ai-search data-endpoint="<?= e(APP_BASE) ?>/transaksi-cari-ai.php?id=<?= (int) $ws['id'] ?>">
                    <div class="field">
                        <label for="ai_cari">Cari dengan AI (ketik atau ucapkan)</label>
                        <input class="input" type="text" id="ai_cari" maxlength="200" placeholder="cth: carikan semua pengeluaran tanggal 2 oktober pada rekening Ibu">
                        <span class="field-hint">Contoh: "semua pengeluaran bulan lalu rekening Kas Rumah" — hasil mengisi filter di atas secara otomatis.</span>
                    </div>
                    <div class="inline-actions">
                        <button class="btn btn-ghost btn-sm" type="button" data-ai-search-go><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Cari dengan AI</button>
                        <button class="btn btn-ghost btn-sm" type="button" data-ai-search-mic aria-pressed="false"><i class="fa-solid fa-microphone" aria-hidden="true"></i> <span data-ai-search-mic-label>Cari dengan suara</span></button>
                    </div>
                    <span class="field-hint" data-ai-search-status></span>
                </div>
            </section>

            <section class="dash-section">
                <div class="dash-head">
                    <div>
                        <h2><?= $filterExplicit ? 'Hasil pencarian' : 'Daftar transaksi' ?> (<?= $totalTx ?>)</h2>
                        <?php if ($filterExplicit): ?>
                        <p class="ws-meta">Filter: <?= e(tx_filter_label($filter)) ?></p>
                        <?php else: ?>
                        <p class="ws-meta">Menampilkan seluruh transaksi.</p>
                        <?php endif; ?>
                    </div>
                    <div class="inline-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e($exportCsv) ?>"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Unduh CSV</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e($exportXlsx) ?>"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> Unduh Excel</a>
                    </div>
                </div>
                <?php if ($transactions === []): ?>
                    <p class="ws-meta"><?= $filterExplicit ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Belum ada transaksi di workspace ini.' ?></p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Deskripsi</th>
                                <th>Rekening</th>
                                <th>Nominal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="mono"><?= e(date('d M Y', strtotime((string) $tx['tx_date']))) ?></td>
                                <td><span class="badge <?= $tx['type'] === 'masuk' ? 'badge-yellow' : 'badge-orange' ?>"><?= $tx['type'] === 'masuk' ? 'Masuk' : 'Keluar' ?></span></td>
                                <td><?= e((string) $tx['description']) ?></td>
                                <td><?= (($tx['account_name'] ?? null) !== null) ? e((string) $tx['account_name']) : '—' ?></td>
                                <td class="tx-amount<?= $tx['type'] === 'masuk' ? ' in' : '' ?>"><?= $tx['type'] === 'masuk' ? '+' : '-' ?><?= e(rupiah((int) $tx['amount'])) ?></td>
                                <td>
                                    <div class="row-form">
                                        <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/transaksi-ubah.php?id=<?= (int) $tx['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Ubah</a>
                                        <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE) ?>/transaksi-hapus.php?id=<?= (int) $tx['id'] ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus…</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php page_render($listUrl, $page, $totalPages); ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
