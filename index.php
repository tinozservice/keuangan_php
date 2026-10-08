<?php
/* Pencatat Keuangan — halaman utama (landing). Halaman diletakkan langsung di root sesuai konvensi proyek. */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$page_title = 'Pencatat Keuangan — Catat, Cari, dan Kelola Keuangan';
$page_desc  = 'Catat pemasukan dan pengeluaran lewat ketik, suara, atau foto struk. Cari dengan kalimat sehari-hari — sendiri atau bersama orang yang Anda percaya.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main">
        <section class="hero">
            <div class="wrap hero-grid">
                <div>
                    <span class="kicker">Pencatat keuangan &amp; workspace</span>
                    <h1>Keuangan rapi, tanpa mengetik panjang.</h1>
                    <p class="hero-lead">Catat pemasukan dan pengeluaran lewat suara, foto struk, atau ketik manual. Temukan data dengan kalimat sehari-hari — sendiri atau bersama orang yang Anda percaya.</p>
                    <div class="hero-cta">
                        <a class="btn btn-primary" href="<?= e(APP_BASE) ?>/register.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Mulai sekarang</a>
                        <a class="btn btn-ghost" href="#fitur">Lihat fitur</a>
                    </div>
                    <p class="hero-note mono">PHP native · SQLite · tanpa langganan</p>
                </div>

                <div>
                    <div class="panel" aria-hidden="true">
                        <div class="panel-head">
                            <span><i class="fa-solid fa-wallet" aria-hidden="true"></i> Ringkasan bulan ini</span>
                            <span class="mono">Okt 2026</span>
                        </div>
                        <div class="panel-body">
                            <div class="search-pill">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <span>“pengeluaran makan minggu lalu”</span>
                            </div>
                            <ul class="tx-list">
                                <li class="tx-row">
                                    <div>
                                        <div class="tx-title">Gaji mingguan</div>
                                        <div class="tx-meta"><span class="badge badge-yellow">Masuk</span><span>· 06 Okt</span></div>
                                    </div>
                                    <div class="tx-amount in">+Rp2.450.000</div>
                                </li>
                                <li class="tx-row">
                                    <div>
                                        <div class="tx-title">Belanja dapur</div>
                                        <div class="tx-meta"><span class="badge badge-orange">Keluar</span><span>· 05 Okt</span></div>
                                    </div>
                                    <div class="tx-amount">-Rp1.180.000</div>
                                </li>
                                <li class="tx-row">
                                    <div>
                                        <div class="tx-title">Transport</div>
                                        <div class="tx-meta"><span class="badge badge-orange">Keluar</span><span>· 04 Okt</span></div>
                                    </div>
                                    <div class="tx-amount">-Rp350.000</div>
                                </li>
                            </ul>
                        </div>
                        <div class="panel-foot">
                            <span>Selisih bulan ini</span>
                            <span class="mono">+Rp920.000</span>
                        </div>
                    </div>
                    <p class="panel-caption mono">Contoh tampilan — bukan data nyata</p>
                </div>
            </div>
        </section>

        <section class="section" id="fitur">
            <div class="wrap">
                <div class="section-title">
                    <span class="kicker">Fitur</span>
                    <h2>Semua yang dibutuhkan untuk mencatat dengan tenang</h2>
                </div>
                <div class="feature-grid">
                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-microphone-lines"></i></span>
                        <h3>Catat tanpa mengetik penuh</h3>
                        <p>Rekam suara atau foto struk — hasilnya masuk ke formulir untuk Anda konfirmasi sebelum disimpan.</p>
                    </article>
                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <h3>Pencarian bahasa alami</h3>
                        <p>Cukup tulis atau ucapkan “pengeluaran kemarin”; hasilnya setara dengan filter manual.</p>
                    </article>
                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                        <h3>Kolaborasi terkendali</h3>
                        <p>Undang pengguna terdaftar yang sudah terverifikasi ke workspace — aktivitas tercatat transparan.</p>
                    </article>
                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>
                        <h3>Tahan gangguan layanan AI</h3>
                        <p>Pool model lintas penyedia dengan urutan prioritas dan failover otomatis saat ada gangguan.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section" id="cara-kerja">
            <div class="wrap">
                <div class="section-title">
                    <span class="kicker">Cara kerja</span>
                    <h2>Tiga langkah, selesai</h2>
                </div>
                <div class="steps">
                    <article class="step-card">
                        <span class="step-n">LANGKAH 1</span>
                        <h3>Buat workspace</h3>
                        <p>Untuk diri sendiri atau keluarga dan tim. Undang anggota kapan pun diperlukan.</p>
                    </article>
                    <article class="step-card">
                        <span class="step-n">LANGKAH 2</span>
                        <h3>Catat &amp; konfirmasi</h3>
                        <p>Ketik, ucapkan, atau foto struk. Hasil ekstraksi ditampilkan untuk Anda periksa dulu.</p>
                    </article>
                    <article class="step-card">
                        <span class="step-n">LANGKAH 3</span>
                        <h3>Cari &amp; ekspor</h3>
                        <p>Temukan transaksi dengan kalimat biasa, lalu ekspor ke CSV atau Excel bila perlu.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section" id="mulai">
            <div class="wrap">
                <div class="cta">
                    <h2>Siap merapikan keuangan Anda?</h2>
                    <p>Mulai gratis dengan email Anda. Verifikasi singkat lewat kode OTP, lalu langsung bekerja.</p>
                    <a class="btn btn-primary" href="<?= e(APP_BASE) ?>/register.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Daftar sekarang</a>
                </div>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
