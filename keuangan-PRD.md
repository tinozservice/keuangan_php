---
title: "Pencatat Keuangan"
prd_version: 1.01
generated_by: prdmaker
generated_at: 2026-10-08T21:01:28+07:00
language: id
sections: 11
---

# PRD — Pencatat Keuangan

## 1. Ringkasan Eksekutif

Pencatat Keuangan adalah aplikasi web untuk mencatat keuangan di dalam ruang kerja (workspace) yang dapat dipakai sendiri atau bersama orang lain. Pengguna membuat workspace, mencatat pemasukan dan pengeluaran melalui ketik manual, suara, atau foto struk belanja, mencari data dengan kalimat biasa, dan mengundang pengguna terdaftar yang sudah terverifikasi untuk berkolaborasi. Aplikasi dilengkapi panel admin untuk mengelola pengguna dan mengelola kumpulan model AI cadangan (pool fallback) lintas penyedia.

Nilai jual utama:

| No | Nilai jual | Inti pembeda |
|---|---|---|
| 1 | Pencatatan tanpa mengetik penuh | Input suara dan foto struk mendampingi input manual |
| 2 | Kolaborasi terkendali | Undangan hanya untuk pengguna terdaftar dan terverifikasi |
| 3 | Pencarian bahasa alami | Cukup mengetik atau berbicara, hasil setara filter manual |
| 4 | Transparansi aktivitas | Log aktivitas terlihat oleh semua anggota workspace |
| 5 | Ketahanan layanan AI | Pool model lintas penyedia dengan urutan prioritas bebas penyedia dan pemantauan kesehatan massal |

Target pengguna: individu (pemakaian pribadi), keluarga (pencatatan bersama), dan anak (dalam pendampingan), ditambah satu peran admin yang mengelola instance.

Model bisnis: Asumsi: aplikasi dijalankan sebagai instance mandiri tanpa langganan berbayar; biaya hosting dan kredit API AI ditanggung pemilik instance. Asumsi: monetisasi berbayar berada di luar lingkup dokumen ini.

## 2. Latar Belakang & Problem Statement

Masalah yang diselesaikan:

| No | Masalah | Dampak yang timbul |
|---|---|---|
| 1 | Mencatat transaksi secara manual memakan waktu dan mudah ditinggalkan | Catatan keuangan tidak lengkap sehingga tidak dapat dipercaya |
| 2 | Menemukan kembali satu transaksi menuntut kombinasi filter yang berlapis | Waktu terbuang dan pengguna berhenti menelusuri riwayat |
| 3 | Pencatatan keuangan bersama tidak menunjukkan siapa mengubah apa dan kapan | Anggota keluarga saling mencurigai dan data mudah berubah tanpa penjelasan |
| 4 | Pencatatan bersama sulit dikendalikan karena tidak ada batas siapa yang boleh diundang | Data keuangan keluarga terekspos ke pihak yang tidak berhak |
| 5 | Layanan AI bergantung pada satu penyedia atau satu model | Pencatatan dan pencarian berhenti total saat model tersebut bermasalah |
| 6 | Biaya menjalankan layanan berbasis AI sulit dikendalikan pemilik layanan | Layanan berhenti atau kualitasnya turun tanpa pemberitahuan |

Pihak yang terdampak: individu yang mencatat keuangan sendiri, keluarga yang mencatat pengeluaran bersama, anak yang baru belajar mengelola uang, dan admin yang menanggung operasional layanan.

Mengapa masalah ini nyata: pencatatan manual yang tidak selesai membuat data keuangan tidak dapat dipakai untuk keputusan apa pun, sedangkan pencatatan bersama tanpa jejak perubahan menghilangkan kepercayaan antaranggota. Ketergantungan pada satu model AI membuat fitur utama berhenti tanpa kendali pengguna.

Mengapa sekarang: model AI multimodal dan layanan yang kompatibel dengan antarmuka OpenAI sudah tersedia secara umum sehingga input suara, foto struk, dan pencarian bahasa alami dapat dipakai tanpa membangun model sendiri, sementara hosting murah berbasis cPanel dan basis data ringan sudah cukup untuk menjalankan aplikasi bagi pemakaian pribadi dan keluarga.

## 3. Tujuan & Non-Tujuan

Tujuan terukur:

| ID | Tujuan | Indikator | Target |
|---|---|---|---|
| G-01 | Pencatatan transaksi cepat dari sisi pengguna | Waktu rata-rata dari membuka form sampai transaksi tersimpan | Asumsi: ≤ 20 detik untuk input manual dan ≤ 30 detik untuk input suara atau foto |
| G-02 | Ekstraksi struk dapat dipakai langsung | Persentase field wajib (tanggal, nominal, deskripsi) yang benar tanpa koreksi manual pada uji foto struk | Asumsi: ≥ 80% |
| G-03 | Pencarian bahasa alami setara filter manual | Persentase permintaan uji yang hasilnya sama dengan hasil filter manual yang setara | Asumsi: ≥ 90% |
| G-04 | Pencatatan tetap berjalan saat satu model AI gagal | Persentase permintaan AI yang berhasil dilayani pool setelah perpindahan otomatis ke model berikutnya pada urutan prioritas | Asumsi: ≥ 99% |
| G-05 | Jejak perubahan lengkap | Persentase aksi tulis (tambah, ubah, hapus) yang menghasilkan entri log aktivitas | 100% |
| G-06 | Ekspor data utuh | Persentase baris transaksi workspace yang terekspor ke CSV dan Excel tanpa kolom hilang | 100% |
| G-07 | Verifikasi akun selesai tanpa bantuan admin | Persentase kode OTP yang terkirim melalui SMTP Brevo dalam 2 menit sejak pendaftaran | Asumsi: ≥ 95% |
| G-08 | Tampilan nyaman di ponsel | Persentase halaman utama yang tampil tanpa gulir horizontal pada lebar viewport 360 px | 100% |

Non-tujuan:

| No | Non-tujuan | Alasan |
|---|---|---|
| 1 | Aplikasi mobile native (Android maupun iOS) | Platform target adalah website dan batasan hosting adalah cPanel; tampilan responsif sudah memenuhi kebutuhan ponsel |
| 2 | Integrasi langsung ke rekening bank atau dompet digital | Tidak diminta pada brief dan menuntut kredensial pihak ketiga serta kepatuhan tambahan |
| 3 | Dukungan banyak mata uang dan konversi kurs | Tidak diminta pada brief dan menambah kerumitan perhitungan yang tidak sepadan untuk pemakaian pribadi dan keluarga |
| 4 | Anggaran, target tabungan, dan pelaporan pajak | Fokus produk adalah pencatatan, kolaborasi, pencarian, dan administrasi; fitur analisis lanjutan menunda rilis inti |
| 5 | Aplikasi desktop terpisah | Tidak diminta pada brief dan menduplikasi antarmuka web yang sudah ada |

## 4. Target Pengguna & Persona

| Persona | Peran | Kebutuhan konkret | Pemicu | Sensitivitas harga |
|---|---|---|---|---|
| Pemilik Workspace Pribadi | Pemilik dan pencatat tunggal pada workspace miliknya | 1) Mencatat pengeluaran harian tanpa mengetik panjang.<br>2) Mencari transaksi lama dengan kalimat biasa.<br>3) Mengekspor catatan ke CSV dan Excel.<br>4) Mengganti nama tampilan, kata sandi, dan mengelola workspace sendiri. | Ingin mengetahui ke mana uang pergi pada periode berjalan | Tinggi — Asumsi: mau memakai selama tidak ada biaya langganan atau biaya yang ditanggung sepadan dengan manfaat |
| Anggota Keluarga Kolaborator | Anggota workspace keluarga dengan hak kelola keuangan, bukan pemilik | 1) Mencatat belanja harian langsung dari ponsel.<br>2) Melihat log aktivitas untuk mengetahui siapa mengubah data.<br>3) Keluar dari workspace kolaborasi bila tidak lagi terlibat. | Pencatatan belanja bersama pada periode bulanan | Rendah — Asumsi: memakai akun yang sudah disediakan pemilik workspace |
| Anak dalam Pendampingan | Pengguna termuda pada workspace keluarga | 1) Mencatat uang saku dengan suara, bukan mengetik.<br>2) Mencari catatan dengan pertanyaan sederhana.<br>3) Tampilan yang terbaca di layar ponsel kecil. | Mulai belajar mengatur uang saku | Tidak berlaku — Asumsi: memakai akun dan workspace milik orang tua, sehingga verifikasi akun dilakukan orang tua |
| Admin Pengelola Instance | Pengelola seluruh pengguna dan pool AI cadangan | 1) Memverifikasi pengguna yang mendaftar manual.<br>2) Menghapus pengguna setelah peringatan tampil.<br>3) Menambah provider dan banyak model beserta tanda kemampuan modalities input dan output.<br>4) Mengatur urutan prioritas model lintas provider.<br>5) Menjalankan cek kesehatan pool secara massal dan membaca ringkasan total provider, total model, model sehat, dan model bermasalah. | Muncul model AI yang error atau ada pengguna menunggu verifikasi | Tinggi — Asumsi: menanggung biaya operasional termasuk kredit API AI sehingga pemakaian model harus dapat dipantau dan diatur |

## 6. Ruang Lingkup & Prioritas

## Klasifikasi MoSCoW dan Fase

MVP ditetapkan sebagai Fase 1 dan mencakup seluruh kebutuhan berprioritas **Must**. Fase 2 dijalankan setelah Fase 1 stabil. Fase 3 bersifat opsional dan hanya dikerjakan bila kapasitas tersedia.

| No | Fitur | MoSCoW | Fase | Catatan |
|---|---|---|---|---|
| 1 | Registrasi manual (username, email, password, ulangi password) dengan verifikasi OTP melalui SMTP Brevo | Must | Fase 1 | Prasyarat seluruh fitur berbayar akun |
| 2 | Login dan registrasi melalui Google OAuth | Must | Fase 1 | Akun Google dianggap sudah terverifikasi |
| 3 | Dashboard akun: ganti nama tampilan dan ganti kata sandi | Must | Fase 1 | Username dan email bersifat hanya-baca |
| 4 | Pembuatan dan pengelolaan workspace milik sendiri (CRUD) | Must | Fase 1 | Dasar seluruh pencatatan |
| 5 | Undangan kolaborator ke workspace | Must | Fase 1 | Hanya user terdaftar dan terverifikasi |
| 6 | Keluar dari workspace kolaborasi | Must | Fase 1 | Disertai konfirmasi |
| 7 | Pencatatan transaksi secara manual | Must | Fase 1 | Jalur pencatatan dasar |
| 8 | Filter manual pencarian data keuangan (waktu/hari, deskripsi, jenis, nominal) | Must | Fase 1 | Menjadi acuan hasil pencarian AI |
| 9 | Log aktivitas workspace | Must | Fase 1 | Dapat dilihat seluruh kolaborator |
| 10 | Ekspor workspace ke CSV dan Excel | Must | Fase 1 | Dua format keluaran |
| 11 | Admin: daftar seluruh user, verifikasi manual, hapus user dengan peringatan | Must | Fase 1 | Termasuk penanganan user pendaftar manual |
| 12 | Admin: pengelolaan pool AI (provider Custom OpenAI Compatible, banyak model per provider, checkbox modalitas masukan dan keluaran) | Must | Fase 1 | Prasyarat fitur AI Fase 2 |
| 13 | Admin: pengaturan urutan prioritas model lintas provider | Must | Fase 1 | Urutan tidak terikat provider |
| 14 | Admin: cek kesehatan pool secara massal dan tabel ringkasan | Must | Fase 1 | Berisi total provider, total model, model sehat, model bermasalah |
| 15 | Pencatatan transaksi melalui suara (mikrofon) | Should | Fase 2 | Bergantung pada pool AI Fase 1 |
| 16 | Pencatatan transaksi melalui foto struk belanja | Should | Fase 2 | Bergantung pada pool AI Fase 1 |
| 17 | Pencarian data keuangan dengan bahasa alami (ketik atau suara) | Should | Fase 2 | Hasil setara filter manual |
| 18 | Penonaktifan model pada pool tanpa menghapus baris model | Could | Fase 3 | Asumsi: pool memisahkan status aktif/nonaktif dari status sehat/error |
| 19 | Riwayat kesehatan pool dalam bentuk deret waktu | Could | Fase 3 | Asumsi: brief hanya menyebut cek kesehatan saat ini, bukan riwayat |

## Hal yang Berada di Luar Cakupan

| Di luar cakupan | Alasan |
|---|---|
| Aplikasi mobile native (Android/iOS) | Platform target hanya Website |
| Integrasi otomatis ke rekening bank atau dompet digital | Tidak disebutkan pada brief |
| Impor data keuangan dari CSV atau Excel | Brief hanya menyebut ekspor |
| Peran anggota granular (misalnya hanya-lihat) | Brief hanya menyebut kolaborator dengan akses kelola keuangan |
| Undangan ke alamat email yang belum terdaftar atau belum terverifikasi | Brief mensyaratkan hanya user terdaftar dan terverifikasi yang dapat diundang |
| Integrasi provider AI selain Custom OpenAI Compatible | Brief menetapkan satu mekanisme integrasi |
| Notifikasi email selain kode OTP | Tidak disebutkan pada brief |
| Mode offline atau instalasi sebagai aplikasi terpasang | Tidak disebutkan pada brief |
| Konversi mata uang dan kurs | Tidak disebutkan pada brief |
| Pembayaran atau langganan di dalam aplikasi | Tidak disebutkan pada brief |
| Login SSO selain Google | Brief hanya menyebut Google |

## 7. User Stories & Acceptance Criteria

| No | User Story | Acceptance Criteria |
|---|---|---|
| 1 | Sebagai **Pemilik Workspace**, saya ingin membuat workspace baru sehingga saya dapat memulai pencatatan keuangan terpisah dari pengguna lain. | - Saya dapat mengakses formulir pembuatan workspace dari dashboard.<br>- Saya dapat memberi nama workspace dan menekan tombol "Buat".<br>- Setelah berhasil, workspace muncul dalam daftar workspace milik saya.<br>- Saya dapat membuka workspace yang baru dibuat dan melihat pesan "Workspace kosong" jika belum ada transaksi. |
| 2 | Sebagai **User Terdaftar dan Terverifikasi**, saya ingin diundang ke workspace kolaborasi sehingga saya dapat mencatat keuangan bersama anggota keluarga atau tim lain. | - Saya menerima notifikasi undangan melalui sistem notifikasi dalam aplikasi.<br>- Saya dapat menerima atau menolak undangan.<br>- Setelah menerima, workspace kolaborasi muncul dalam daftar workspace saya.<br>- Saya dapat melihat daftar anggota kolaborasi di dalam workspace. |
| 3 | Sebagai **Pencatat Keuangan**, saya ingin mencatat transaksi menggunakan suara sehingga saya tidak perlu mengetik saat sedang sibuk. | - Saya dapat membuka formulir transaksi dan memilih ikon rekam suara.<br>- Saya dapat merekam suara saya menggambarkan transaksi (misalnya: "Belanja ke sampai Rp 50.000" ).<br>- Sistem mengubah suara ke teks dan mengisi otomatis field deskripsi.<br>- Saya dapat menyimpan transaksi setelah konfirmasi data. |
| 4 | Sebagai **Pencatat Keuangan**, saya ingin memasukkan data menggunakan foto struk belanja sehingga saya tidak perlu memasukkan data secara manual. | - Saya dapat membuka formulir transaksi dan memilih opsi unggah foto struk.<br>- Saya dapat mengambil foto struk menggunakan kamera atau mengunggah dari galeri.<br>- Sistem mengekstrak tanggal, nominal, dan keterangan dari struk.<br>- Field transaksi terisi otomatis dan saya dapat mengoreksi sebelum menyimpan. |
| 5 | Sebagai **Pencari Data Keuangan**, saya ingin mencari transaksi menggunakan bahasa alami seperti kalimat santai sehingga saya tidak perlu mengingat struktur filter. | - Saya dapat membuka halaman pencarian transaksi.<br>- Saya dapat mengetik atau merekam kalimat seperti "Tampilkan pengeluaran kemarin" atau "Transaksi untuk makan" .<br>- Hasil pencarian ditampilkan dalam tabel yang sama seperti filter manual.<br>- Saya dapat menggunakan hasil pencarian untuk mengekspor atau mengedit transaksi. |
| 6 | Sebagai **Anggota Kolaborasi**, saya ingin melihat log aktivitas di dalam workspace sehingga saya tahu siapa yang melakukan perubahan apa pada catatan keuangan. | - Saya dapat mengakses halaman log dari dalam workspace.<br>- Setiap entri log menampilkan waktu, nama pengguna, dan tindakan yang dilakukan.<br>- Log terurut dari terbaru ke terlama.<br>- Semua anggota kolaborasi dapat melihat log yang sama. |
| 7 | Sebagai **User Pribadi**, saya ingin mengelola akun saya termasuk ganti nama dan password sehingga saya dapat menjaga keamanan dan privasi akun. | - Saya dapat membuka halaman pengaturan akun dari dashboard.<br>- Saya dapat mengganti nama tampilan saya dan menekan "Simpan".<br>- Saya dapat mengganti password dengan memasukkan password lama dan baru.<br>- Username dan email ditampilkan sebagai baca-saja dan tidak dapat diedit. |
| 8 | Sebagai **Admin**, saya ingin mengelola seluruh pengguna sehingga saya dapat memastikan hanya pengguna yang valid dan terverifikasi yang mengakses aplikasi. | - Saya dapat membuka panel admin dan melihat daftar semua pengguna.<br>- Saya dapat memverifikasi pengguna manual yang belum diverifikasi.<br>- Saya dapat menghapus pengguna dengan konfirmasi dialog peringatan sebelumnya.<br>- Setiap aksi pengelolaan pengguna tercatat di log sistem. |

## 8. Functional Requirements

## Modul Autentikasi dan Verifikasi Akun

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-001 | Sistem menyediakan formulir pendaftaran manual dengan empat field: username, email, password, dan ulangi password. | M |
| FR-002 | Sistem menolak penyimpanan akun dan menampilkan pesan kesalahan spesifik bila salah satu field pendaftaran kosong, format email tidak valid, atau nilai password tidak sama dengan ulangi password. | M |
| FR-003 | Sistem menyimpan akun hasil pendaftaran manual dengan status belum terverifikasi, lalu mengirim satu kode OTP ke alamat email pengguna melalui SMTP Brevo. | M |
| FR-004 | Sistem mengubah status akun menjadi terverifikasi hanya bila kode OTP yang dimasukkan pengguna cocok dengan kode yang dikirim. Asumsi: kode OTP berlaku 10 menit dan maksimal 5 kali percobaan per kode. | M |
| FR-005 | Sistem menyediakan tombol login dan daftar melalui Google OAuth yang menghasilkan sesi login tanpa pengisian formulir manual. Asumsi: akun hasil Google OAuth berstatus terverifikasi karena email telah diverifikasi Google. | M |
| FR-006 | Sistem menolak login akun berstatus belum terverifikasi dan menampilkan opsi kirim ulang kode OTP pada halaman yang sama. | M |
| FR-007 | Sistem membatasi pengiriman ulang kode OTP. Asumsi: maksimal 1 kali per 60 detik per akun. | S |
| FR-008 | Sistem mengakhiri sesi pengguna saat pengguna menekan tombol keluar, dan setelah itu halaman workspace tidak dapat diakses tanpa login ulang. | M |

## Modul Dashboard Pengguna

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-009 | Dashboard pengguna menampilkan field nama yang dapat diubah dan menyimpan hasil perubahan ke profil pengguna. | M |
| FR-010 | Dashboard pengguna menampilkan username dan email sebagai field hanya-baca yang tidak dapat diubah dari antarmuka. | M |
| FR-011 | Dashboard pengguna menyediakan formulir ganti password yang mewajibkan pengisian password lama; password baru disimpan hanya bila password lama cocok. | M |

## Modul Workspace

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-012 | Pengguna dapat membuat workspace baru dengan mengisi nama workspace, dan workspace tersebut tercatat sebagai milik pengguna pembuatnya. | M |
| FR-013 | Pemilik workspace dapat mengubah nama workspace miliknya dan hasil perubahan langsung tampil pada daftar workspace. | M |
| FR-014 | Pemilik workspace dapat menghapus workspace miliknya setelah muncul peringatan konfirmasi, dan workspace tersebut hilang dari daftar setelah dikonfirmasi. | M |
| FR-015 | Workspace milik pengguna lain yang diikuti sebagai kolaborator hanya dapat dibuka untuk pengelolaan keuangan; tombol ubah dan hapus tidak ditampilkan dan permintaan ubah/hapus dari pengguna tersebut ditolak server. | M |
| FR-016 | Kolaborator dapat keluar dari workspace milik pengguna lain, dan setelah keluar workspace tersebut hilang dari daftar workspace pengguna. Asumsi: transaksi yang pernah dibuat kolaborator tetap tersimpan di workspace. | M |

## Modul Kolaborasi dan Undangan

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-017 | Pemilik workspace dapat mengundang pengguna lain ke workspace miliknya dengan memasukkan alamat email atau username calon kolaborator. | M |
| FR-018 | Sistem menolak undangan dan menampilkan pesan yang membedakan dua kondisi: email/username tidak terdaftar, dan akun terdaftar tetapi belum terverifikasi. | M |
| FR-019 | Sistem menambahkan pengguna sebagai anggota workspace hanya bila akun calon kolaborator berstatus terdaftar dan terverifikasi. | M |
| FR-020 | Daftar anggota workspace menampilkan nama dan peran setiap anggota (pemilik atau kolaborator). | S |

## Modul Pencatatan Transaksi

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-021 | Sistem menyediakan formulir input manual transaksi dengan field tanggal, jenis transaksi, nominal, dan deskripsi, serta menyimpan transaksi ke workspace yang sedang dibuka. | M |
| FR-022 | Sistem menyediakan input suara melalui mikrofon peramban yang mengirimkan berkas audio ke model AI berkemampuan audio-to-text untuk diubah menjadi field transaksi. | M |
| FR-023 | Sistem menyediakan input foto struk belanja yang mengirimkan berkas gambar ke model AI berkemampuan vision-to-text untuk diubah menjadi field transaksi. | M |
| FR-024 | Hasil ekstraksi AI dari suara atau foto ditampilkan pada formulir transaksi dalam keadaan dapat disunting, dan transaksi hanya tersimpan setelah pengguna menekan tombol simpan. | M |
| FR-025 | Sistem memilih model AI berdasarkan urutan prioritas pool fallback; bila model terpilih gagal memberikan hasil, sistem otomatis mencoba model berikutnya pada urutan tersebut. | M |
| FR-026 | Bila seluruh model pada pool fallback gagal, sistem menampilkan pesan kegagalan dan mempertahankan isi formulir yang sudah diisi pengguna tanpa menghapusnya. | S |

## Modul Pencarian Data Keuangan

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-027 | Sistem menyediakan pencarian bahasa alami melalui input teks dan input suara pada halaman daftar transaksi workspace. | M |
| FR-028 | Sistem mengubah permintaan pencarian bahasa alami menjadi parameter filter yang sama dengan filter manual, meliputi minimal rentang waktu atau hari dan deskripsi. | M |
| FR-029 | Hasil pencarian bahasa alami ditampilkan pada tampilan daftar hasil yang sama dengan hasil filter manual, sehingga kolom dan urutan data identik. | M |
| FR-030 | Sistem tetap menyediakan filter manual (rentang waktu atau hari dan deskripsi) yang dapat dipakai tanpa melalui AI. | M |
| FR-031 | Bila permintaan pencarian bahasa alami tidak dapat diterjemahkan menjadi parameter filter, sistem menampilkan pesan kegagalan dan mengarahkan pengguna ke filter manual tanpa menampilkan daftar transaksi acak. | S |

## Modul Log Aktivitas

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-032 | Sistem mencatat satu entri log setiap kali terjadi perubahan data pada workspace, memuat waktu kejadian, identitas pelaku, jenis aksi (tambah, ubah, hapus), dan objek yang diubah. | M |
| FR-033 | Semua anggota workspace, termasuk kolaborator, dapat membuka halaman log aktivitas dan melihat seluruh entri log workspace tersebut. | M |

## Modul Ekspor

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-034 | Pemilik dan kolaborator workspace dapat mengunduh seluruh data transaksi workspace dalam format CSV. | M |
| FR-035 | Pemilik dan kolaborator workspace dapat mengunduh seluruh data transaksi workspace dalam format Excel. Asumsi: berkas berformat .xlsx. | M |

## Modul Admin Dashboard

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-036 | Admin dapat membuka daftar seluruh akun terdaftar yang memuat username, email, dan status verifikasi setiap akun. | M |
| FR-037 | Admin dapat mengubah status akun pengguna dari belum terverifikasi menjadi terverifikasi secara langsung, dan status baru langsung tampil pada daftar akun. | M |
| FR-038 | Admin dapat menghapus akun pengguna, dan sistem menampilkan dialog peringatan yang harus dikonfirmasi sebelum penghapusan dijalankan. | M |
| FR-039 | Admin dapat menambah provider AI baru dengan mengisi data koneksi Custom OpenAI Compatible, dan provider tersebut tampil pada halaman pengelolaan pool AI. | M |
| FR-040 | Admin dapat menambahkan lebih dari satu model pada satu provider, dan setiap model tampil sebagai baris tersendiri pada tabel pool AI. | M |
| FR-041 | Saat menambahkan atau mengubah baris model, admin dapat mencentang kapabilitas input model (teks, vision/image, audio, pdf/file) dan kapabilitas output model (teks, audio, image, video), serta pilihan tersebut tersimpan dan tampil kembali saat baris model dibuka. | M |
| FR-042 | Admin dapat menyusun ulang urutan prioritas model pada pool fallback secara lintas provider, dan urutan yang tersimpan dipakai sistem sebagai urutan percobaan FR-025. | M |
| FR-043 | Admin dapat menjalankan pemeriksaan kesehatan seluruh pool fallback sekaligus dalam satu aksi massal, tanpa membuka halaman model satu per satu. | M |
| FR-044 | Halaman pemeriksaan kesehatan menampilkan tabel ringkasan berisi total provider, total model, total model aktif/sehat, dan total model error/bermasalah. | M |
| FR-045 | Sistem menyimpan hasil pemeriksaan kesehatan terakhir pada setiap model dan menampilkannya pada baris model di halaman pool AI. | S |
| FR-046 | Admin dapat menandai sebuah model sebagai tidak aktif tanpa menghapusnya dari pool, dan model bertanda tidak aktif dilewati oleh sistem pada FR-025. Asumsi: fitur nonaktif sementara ini bersifat opsional. | C |

## 9. Alur Pengguna

## Alur 1 — Pendaftaran Manual dan Verifikasi OTP

1. Calon pengguna membuka halaman daftar dan memilih opsi daftar manual.
2. Calon pengguna mengisi username, email, password, dan ulangi password, lalu menekan tombol daftar (FR-001).
3. Sistem memvalidasi isian; bila valid, sistem menyimpan akun berstatus belum terverifikasi dan mengirim kode OTP ke email melalui SMTP Brevo (FR-003).
4. Calon pengguna membuka email dan memasukkan kode OTP pada halaman verifikasi.
5. Sistem mencocokkan kode OTP; bila cocok, status akun berubah menjadi terverifikasi (FR-004).
6. Pengguna masuk ke dashboard dan dapat membuat workspace (FR-012).

**Alur alternatif**
- Pendaftaran atau login melalui Google OAuth: pengguna menekan tombol Google, menyelesaikan persetujuan akun Google, dan langsung memperoleh sesi login tanpa mengisi formulir manual (FR-005).
- Kode OTP tidak sampai ke kotak masuk: pengguna menekan kirim ulang kode dengan jeda minimal sesuai batas pengiriman ulang (FR-007).

**Pengecualian**
- Isian tidak valid atau password tidak sama dengan ulangi password: akun tidak disimpan dan sistem menampilkan pesan kesalahan spesifik (FR-002).
- Kode OTP salah atau melewati masa berlaku: akun tetap berstatus belum terverifikasi dan pengguna diminta meminta kode baru.
- Pengguna mencoba login sebelum verifikasi selesai: sistem menolak login dan menampilkan opsi kirim ulang kode OTP (FR-006).
- Pengiriman email melalui SMTP Brevo gagal: akun tetap tersimpan dengan status belum terverifikasi, dan pengguna dapat meminta pengiriman ulang kode.

## Alur 2 — Membuat Workspace dan Mengundang Kolaborator

1. Pengguna terverifikasi membuka dashboard dan memilih buat workspace baru.
2. Pengguna mengisi nama workspace dan menyimpan; workspace tercatat sebagai miliknya (FR-012).
3. Pengguna membuka halaman anggota workspace dan memasukkan email atau username calon kolaborator (FR-017).
4. Sistem memeriksa status akun calon kolaborator: harus terdaftar dan terverifikasi (FR-019).
5. Sistem menambahkan calon kolaborator sebagai anggota workspace, dan pengguna tersebut dapat membuka workspace untuk mengelola keuangan (FR-015).
6. Kolaborator membuka workspace, mencatat atau mengubah transaksi, dan setiap perubahan tercatat pada log aktivitas workspace (FR-032).

**Alur alternatif**
- Calon kolaborator belum terdaftar: sistem menolak undangan dan menampilkan pesan bahwa akun tidak ditemukan (FR-018).
- Calon kolaborator terdaftar tetapi belum terverifikasi: sistem menolak undangan dan menampilkan pesan bahwa akun belum terverifikasi (FR-018).
- Undangan dikirim melalui email kepada calon kolaborator. Asumsi: pengiriman undangan memakai SMTP Brevo.

**Pengecualian**
- Kolaborator membuka workspace milik pengguna lain: tombol ubah dan hapus workspace tidak tersedia dan permintaan ubah/hapus ditolak server (FR-015).
- Kolaborator keluar dari workspace: workspace hilang dari daftar workspace miliknya, sedangkan transaksi yang pernah dibuat tetap tersimpan di workspace (FR-016).
- Pemilik menghapus workspace miliknya: sistem meminta konfirmasi lebih dahulu, dan setelah dikonfirmasi workspace hilang dari daftar (FR-014).

## Alur 3 — Mencatat Transaksi Melalui Suara atau Foto Struk dengan Fallback AI

1. Pengguna membuka workspace dan memilih metode pencatatan: ketik manual, suara, atau foto struk.
2. Untuk metode suara, pengguna menekan ikon mikrofon dan berbicara; untuk metode foto, pengguna memilih atau mengambil foto struk (FR-022, FR-023).
3. Sistem mengirim berkas audio atau gambar dari sisi server ke model AI sesuai urutan prioritas pool fallback (FR-025).
4. Model mengembalikan hasil ekstraksi berupa tanggal, nominal, dan deskripsi.
5. Sistem menampilkan hasil ekstraksi pada formulir transaksi dalam keadaan dapat disunting (FR-024).
6. Pengguna memeriksa dan menyunting bila perlu, lalu menekan tombol simpan; transaksi tersimpan di workspace.
7. Sistem mencatat entri log aktivitas untuk transaksi yang baru dibuat (FR-032).

**Alur alternatif**
- Pengguna memilih ketik manual: pengguna mengisi tanggal, jenis transaksi, nominal, dan deskripsi langsung pada formulir, lalu menyimpan (FR-021).
- Pencatatan lewat suara tidak tersedia pada peramban pengguna: pengguna diarahkan memakai input manual atau foto struk. Asumsi: akses mikrofon memerlukan izin peramban.

**Pengecualian**
- Model AI pada urutan terpilih gagal: sistem otomatis mencoba model berikutnya pada urutan prioritas pool fallback (FR-025).
- Seluruh model pada pool fallback gagal: sistem menampilkan pesan kegagalan dan mempertahankan isi formulir yang sudah diisi agar pengguna dapat menyimpan secara manual (FR-026).
- Pengguna menutup formulir sebelum menekan tombol simpan: transaksi tidak tersimpan dan tidak muncul pada daftar transaksi.

## Alur 4 — Pencarian Data Keuangan dengan Bahasa Alami

1. Pengguna membuka daftar transaksi pada sebuah workspace.
2. Pengguna mengetik permintaan, misalnya rentang waktu atau kata kunci deskripsi, atau menekan ikon mikrofon untuk mengucapkan permintaan (FR-027).
3. Sistem mengubah permintaan tersebut menjadi parameter filter yang setara dengan filter manual (FR-028).
4. Sistem menampilkan hasil pada tampilan daftar hasil yang sama dengan hasil filter manual (FR-029).
5. Pengguna dapat menyunting atau menghapus filter yang terbentuk untuk mempersempit hasil.

**Alur alternatif**
- Pengguna memakai filter manual tanpa AI: pengguna memilih rentang waktu atau hari dan mengisi deskripsi, lalu menekan tombol cari (FR-030).
- Permintaan bahasa alami tidak dapat diterjemahkan: sistem menampilkan pesan kegagalan dan mengarahkan pengguna ke filter manual tanpa menampilkan daftar transaksi acak (FR-031).

**Pengecualian**
- Permintaan bahasa alami menghasilkan nol kecocokan: sistem menampilkan keadaan kosong pada daftar hasil, bukan pesan kesalahan sistem.
- Seluruh model pool fallback gagal saat menerjemahkan permintaan: pencarian bahasa alami gagal dan pengguna diarahkan memakai filter manual (FR-026, FR-031).

## Alur 5 — Meninjau Log Aktivitas dan Mengekspor Workspace

1. Pengguna membuka workspace, baik sebagai pemilik maupun kolaborator.
2. Pengguna membuka halaman log aktivitas workspace.
3. Sistem menampilkan seluruh entri log yang memuat waktu, pelaku, jenis aksi, dan objek yang diubah (FR-032, FR-033).
4. Pengguna memilih menu ekspor dan memilih format CSV atau Excel.
5. Sistem menyiapkan berkas berisi data transaksi workspace dan mengirimkannya sebagai unduhan (FR-034, FR-035).

**Alur alternatif**
- Pengguna hanya ingin melihat log tanpa mengekspor: alur berhenti pada langkah 3.
- Pengguna mengekspor hanya salah satu format: berkas yang diunduh hanya berisi format yang dipilih.

**Pengecualian**
- Workspace belum memiliki transaksi: berkas ekspor tetap dihasilkan dengan baris judul kolom saja. Asumsi: berkas berformat .xlsx untuk pilihan Excel.
- Pengguna sudah keluar dari workspace kolaborasi: halaman log dan menu ekspor workspace tersebut tidak lagi dapat diakses (FR-016).

## Alur 6 — Pengelolaan Pengguna dan Pool AI oleh Admin

1. Admin masuk ke dashboard admin dan membuka daftar seluruh akun terdaftar (FR-036).
2. Admin dapat memverifikasi akun pengguna secara langsung sehingga status akun berubah menjadi terverifikasi (FR-037).
3. Admin dapat menghapus akun pengguna; sistem menampilkan dialog peringatan yang harus dikonfirmasi sebelum akun dihapus (FR-038).
4. Admin membuka halaman pengelolaan pool AI, menambahkan provider Custom OpenAI Compatible, lalu menambahkan beberapa baris model pada provider tersebut (FR-039, FR-040).
5. Pada setiap baris model, admin mencentang kapabilitas input dan output model agar kemampuan model tercatat untuk pemakaian berikutnya (FR-041).
6. Admin menyusun urutan prioritas model secara lintas provider dengan memindahkan baris model (FR-042).
7. Admin menjalankan pemeriksaan kesehatan massal seluruh pool, lalu membaca tabel ringkasan total provider, total model, model sehat, dan model bermasalah (FR-043, FR-044).

**Alur alternatif**
- Admin hanya memeriksa satu model: admin membuka baris model pada halaman pool dan menjalankan pemeriksaan satu per satu, dengan hasil terakhir tersimpan pada baris tersebut (FR-045).
- Admin menandai model bermasalah sebagai tidak aktif tanpa menghapusnya, sehingga model dilewati pada urutan percobaan sistem (FR-046).

**Pengecualian**
- Kredensial provider salah atau titik akhir tidak merespons: model tercatat sebagai bermasalah dan menambah nilai total model error pada tabel ringkasan (FR-044).
- Admin menutup dialog peringatan hapus akun tanpa mengonfirmasi: akun tidak dihapus.
- Urutan prioritas pool tidak diisi: sistem memakai urutan default berdasarkan urutan penambahan model. Asumsi: urutan default mengikuti waktu penambahan model.

## 10. Non-Functional Requirements

## Performa

| ID | Kebutuhan | Target terukur | Cara verifikasi |
|---|---|---|---|
| NFR-001 | Waktu muat halaman pertama (dashboard dan daftar transaksi) | Asumsi: Largest Contentful Paint ≤ 2,5 detik pada jaringan 4G | Lighthouse pada perangkat mobile, 3 kali uji, ambil median |
| NFR-002 | Waktu tanggap permintaan server untuk halaman non-AI | Asumsi: persentil ke-95 ≤ 1,5 detik untuk 50 permintaan berturut-turut | Log durasi permintaan pada server |
| NFR-003 | Waktu simpan transaksi input manual | Asumsi: ≤ 2 detik dari tombol simpan sampai konfirmasi tampil | Uji fungsional terukur dengan stopwatch otomatis |
| NFR-004 | Waktu proses input suara sampai transaksi terisi | Asumsi: ≤ 15 detik untuk rekaman berdurasi ≤ 30 detik | Uji 20 rekaman contoh |
| NFR-005 | Waktu proses ekstraksi foto struk sampai form terisi | Asumsi: ≤ 20 detik untuk berkas ≤ 5 MB | Uji 20 foto struk contoh |
| NFR-006 | Waktu tanggap pencarian bahasa alami | Asumsi: ≤ 8 detik dari kirim kueri sampai hasil tampil | Uji 20 kueri contoh |
| NFR-007 | Waktu ekspor CSV dan Excel | Asumsi: ≤ 10 detik untuk 10.000 baris dan ≤ 30 detik untuk 50.000 baris | Uji ekspor pada data uji berskala |
| NFR-008 | Waktu pemeriksaan kesehatan pool AI secara massal | Asumsi: ≤ 60 detik untuk 100 model | Halaman kelola pool AI, ukur durasi proses |
| NFR-009 | Ukuran halaman agar hemat kuota | Asumsi: total aset CSS dan JS ≤ 500 KB terkompresi per halaman | Pengukuran Network pada browser |
| NFR-010 | Responsivitas antarmuka | Target sentuh minimum 44 × 44 piksel; tanpa gulir horizontal pada lebar 360 piksel | Uji perangkat 360 px, 768 px, 1280 px |

## Keandalan

| ID | Kebutuhan | Target terukur | Cara verifikasi |
|---|---|---|---|
| NFR-011 | Ketersediaan aplikasi | Asumsi: ≥ 99% per bulan, di luar pemeliharaan terjadwal yang diumumkan ≥ 24 jam sebelumnya | Pemantauan uptime eksternal per menit |
| NFR-012 | Tingkat galat server | Asumsi: respons galat 5xx ≤ 1% dari total permintaan per bulan | Log galat server |
| NFR-013 | Keutuhan data transaksi | 0 kejadian kehilangan transaksi tersimpan akibat kegagalan proses; setiap penyimpanan bersifat transaksional (commit atau rollback penuh) | Uji gangguan proses saat penyimpanan |
| NFR-014 | Failover pool AI | Bila model aktif gagal, sistem berpindah ke model berikutnya menurut urutan prioritas dalam ≤ 3 detik, maksimum 3 percobaan per permintaan | Uji simulasi model gagal |
| NFR-015 | Tingkat keberhasilan permintaan AI | Asumsi: ≥ 95% permintaan AI selesai tanpa galat yang terlihat pengguna | Log permintaan AI per model |
| NFR-016 | Cadangan basis data | Asumsi: salinan basis data otomatis minimal 1 kali per hari, retensi 7 hari | Daftar berkas cadangan di penyimpanan |
| NFR-017 | Pemulihan setelah gangguan | Asumsi: pemulihan layanan ≤ 4 jam sejak gangguan terdeteksi | Uji pemulihan terjadwal per kuartal |
| NFR-018 | Kelengkapan log aktivitas | 100% aksi ubah, tambah, dan hapus pada workspace tercatat dengan pencatat waktu dan pelaku | Pemeriksaan banding antara aksi uji dan isi log |

## Keamanan

| ID | Kebutuhan | Target terukur | Cara verifikasi |
|---|---|---|---|
| NFR-019 | Pengelolaan kata sandi | Kata sandi disimpan dengan algoritma hashing satu arah; panjang minimum 8 karakter | Pemeriksaan struktur basis data dan uji validasi form |
| NFR-020 | Masa berlaku kode OTP | Kode OTP berlaku 10 menit; maksimum 5 percobaan verifikasi per kode | Uji kedaluwarsa dan uji batas percobaan |
| NFR-021 | Pembatasan percobaan login | Maksimum 5 percobaan gagal per akun per 15 menit; akun terkunci sementara 15 menit setelahnya | Uji otomatis percobaan login berulang |
| NFR-022 | Perlindungan permintaan | Seluruh permintaan pengubah data menyertakan token anti-CSRF dan wajib lolos validasi | Uji kirim permintaan tanpa token |
| NFR-023 | Perlindungan injeksi basis data | 100% kueri memakai pernyataan siap pakai dengan parameter terikat | Pemeriksaan kode dan uji masukan berbahaya |
| NFR-024 | Isolasi akses workspace | Pengguna non-anggota mendapat penolakan pada 100% permintaan baca dan tulis data workspace | Matriks uji hak akses per peran |
| NFR-025 | Kanal transportasi | Seluruh trafik memakai HTTPS; pengalihan otomatis dari HTTP dalam ≤ 1 detik | Pemeriksaan sertifikat dan uji pengalihan |
| NFR-026 | Sesi pengguna | Sesi berakhir otomatis setelah 30 menit tanpa aktivitas; token sesi diperbarui setelah login berhasil | Uji kedaluwarsa sesi |
| NFR-027 | Kerahasiaan kunci API penyedia AI | Kunci API disimpan di sisi server, tidak pernah dikirim ke browser, dan disamarkan pada antarmuka admin | Pemeriksaan respons jaringan halaman pengelolaan pool |
| NFR-028 | Hak akses panel admin | Hanya akun berperan admin yang dapat mengakses 100% halaman pengelolaan pengguna dan pool AI | Uji akses dengan akun non-admin |
| NFR-029 | Keterlacakan perubahan | Log aktivitas tidak dapat diubah atau dihapus oleh pengguna biasa; 100% entri memuat waktu, pelaku, dan jenis aksi | Uji coba ubah dan hapus entri log |
| NFR-030 | Verifikasi akun | Akun manual berstatus belum terverifikasi tidak dapat diundang ke workspace dan tidak dapat memakai fitur AI | Uji undangan terhadap akun belum terverifikasi |

## Skalabilitas

| ID | Kebutuhan | Target terukur | Cara verifikasi |
|---|---|---|---|
| NFR-031 | Jumlah pengguna terdaftar | Asumsi: melayani 500 pengguna terdaftar tanpa perubahan arsitektur | Uji beban dengan data uji berskala |
| NFR-032 | Jumlah workspace | Asumsi: melayani 2.000 workspace aktif | Uji kueri daftar workspace pada data uji |
| NFR-033 | Volume transaksi per workspace | Asumsi: melayani 50.000 baris transaksi per workspace dengan waktu muat daftar ≤ 2 detik pada tampilan 25 baris per halaman | Uji dengan data 50.000 baris |
| NFR-034 | Beban serentak | Asumsi: 50 pengguna aktif serentak dengan NFR-002 tetap terpenuhi | Uji beban serentak |
| NFR-035 | Jumlah model dalam pool AI | Asumsi: mendukung 200 baris model lintas penyedia tanpa penurunan waktu muat halaman di luar NFR-002 | Uji halaman kelola pool dengan 200 model |
| NFR-036 | Pertumbuhan penyimpanan | Asumsi: pertumbuhan berkas unggahan dan basis data ≤ 2 GB per tahun pada 500 pengguna, dengan pembersihan berkas unggahan sementara setiap 30 hari | Pemantauan ukuran penyimpanan bulanan |
| NFR-037 | Penambahan penyedia AI | Penyedia baru berbasis OpenAI Compatible dapat ditambahkan tanpa mengubah kode aplikasi inti, hanya melalui antarmuka admin | Uji tambah satu penyedia baru |
| NFR-038 | Antrean proses masif | Pemeriksaan kesehatan massal dan ekspor besar dijalankan tanpa memblokir permintaan pengguna lain; Asumsi: waktu tanggap halaman lain tetap ≤ 3 detik selama proses berjalan | Uji bersamaan antara proses massal dan navigasi biasa |

## Kegunaan dan Kompatibilitas

| ID | Kebutuhan | Target terukur | Cara verifikasi |
|---|---|---|---|
| NFR-039 | Dukungan peramban | Berfungsi pada 2 versi terakhir Chrome, Safari, Firefox, dan Edge | Matriks uji lintas peramban |
| NFR-040 | Keramahan seluler | Seluruh alur utama (catat, cari, ekspor, kelola workspace) selesai dalam ≤ 3 ketukan dari dashboard pada lebar 360 piksel | Uji tugas pada perangkat seluler |
| NFR-041 | Bahasa antarmuka | 100% teks antarmuka pengguna berbahasa Indonesia | Pemeriksaan tampilan seluruh halaman |
| NFR-042 | Pesan galat | Setiap galat menampilkan penjelasan sebab dan langkah lanjutan dalam ≤ 1 kalimat; 0 pesan galat berisi kode teknis mentah | Uji daftar galat yang mungkin muncul |
| NFR-043 | Konsistensi desain | Satu set token warna, tipografi, dan jarak dipakai pada 100% halaman; tanpa pola tata letak bawaan yang seragam dan tanpa elemen dekoratif tanpa fungsi | Pemeriksaan halaman terhadap panduan desain |

## 11. Arsitektur Sistem & Tech Stack

## Gambaran Umum

Aplikasi berjalan sebagai satu aplikasi web monolitik di shared hosting cPanel. Semua logika sisi server dieksekusi oleh PHP Native, data disimpan pada satu berkas SQLite di luar direktori publik, dan antarmuka dirender di peramban dengan Tailwind melalui CDN ditambah CSS kustom serta JavaScript tanpa kerangka kerja. Pemanggilan model AI dilakukan dari sisi server ke titik akhir yang kompatibel dengan OpenAI.

## Komponen

| Komponen | Tanggung jawab | Batas |
|---|---|---|
| Modul Autentikasi | Registrasi manual, login, logout, verifikasi OTP, login Google, pengelolaan sesi | Tidak menangani data transaksi |
| Modul Dashboard Pengguna | Pengelolaan akun (ubah nama, ubah kata sandi), daftar workspace milik sendiri dan workspace kolaborasi | Tidak menangani konfigurasi model AI |
| Modul Workspace | Pembuatan, pembacaan, pembaruan, penghapusan workspace milik sendiri, undangan anggota, keluarnya anggota kolaborasi | Tidak menangani verifikasi akun |
| Modul Transaksi | Pencatatan pemasukan dan pengeluaran melalui input manual dan hasil ekstraksi AI | Tidak menyimpan kunci API |
| Modul AI Orchestrator | Pemilihan model dari pool sesuai modalitas, pengurutan prioritas, percobaan ulang, pencatatan status kesehatan | Tidak merender antarmuka |
| Modul Pencarian | Pencarian filter manual dan pencarian bahasa alami yang diterjemahkan menjadi parameter filter | Tidak mengubah data |
| Modul Log Aktivitas | Pencatatan waktu, pelaku, jenis aksi, dan objek yang diubah pada workspace | Hanya menambah data, tidak mengubah atau menghapus |
| Modul Ekspor | Penghasilan berkas CSV dan Excel dari data workspace | Tidak memuat data workspace pengguna lain |
| Modul Panel Admin | Pengelolaan pengguna, verifikasi langsung, penghapusan pengguna dengan peringatan, pengelolaan pool AI, pemeriksaan kesehatan massal | Tidak dapat mengubah isi log aktivitas |
| Modul Notifikasi Email | Pengiriman kode OTP melalui SMTP Brevo | Tidak menyimpan kata sandi |

## Lapisan

| Lapisan | Isi | Aturan ketergantungan |
|---|---|---|
| Lapisan Tampilan | Halaman HTML, komponen antarmuka, Tailwind CDN, CSS kustom, JavaScript peramban | Hanya berbicara dengan lapisan Permintaan melalui permintaan HTTP |
| Lapisan Permintaan | Titik masuk PHP, pemetaan rute, validasi masukan, token anti-CSRF, pemeriksaan peran | Tidak memuat aturan bisnis |
| Lapisan Layanan | Autentikasi, workspace, transaksi, pencarian, ekspor, log aktivitas, orkestrasi AI | Satu-satunya lapisan yang boleh memanggil Penyedia AI |
| Lapisan Integrasi | Klien OAuth Google, klien SMTP Brevo, klien titik akhir OpenAI Compatible | Menyembunyikan detail protokol dari lapisan Layanan |
| Lapisan Data | Berkas SQLite, berkas unggahan sementara, berkas sesi, berkas cadangan | Hanya diakses oleh lapisan Layanan melalui pernyataan siap pakai |

## Tech Stack dan Alasan Pemilihan

| Teknologi | Peran | Alasan pemilihan |
|---|---|---|
| PHP Native | Bahasa sisi server, routing, dan seluruh logika layanan | Tersedia pada setiap paket shared hosting cPanel tanpa konfigurasi tambahan dan tanpa proses latar yang berdiri sendiri |
| SQLite | Basis data tunggal untuk seluruh entitas | Berbasis berkas sehingga tidak memerlukan layanan basis data terpisah, cukup untuk target NFR-031 sampai NFR-033, dan mudah dicadangkan |
| CDN Tailwind | Utilitas tata letak dan warna | Mempercepat penyusunan antarmuka responsif tanpa tahap pembangunan aset |
| CSS Kustom | Override token warna, tipografi, dan komponen khas | Mencegah tampilan seragam bawaan dan mempertahankan desain anti AI Slop |
| JavaScript peramban | Interaksi form, perekaman suara, pratinjau foto, pemanggilan tak sinkron | Menjaga pengalaman seluler tetap responsif dengan aset ringan sesuai NFR-009 |
| OAuth Google | Masuk dan daftar tanpa kata sandi | Mengurangi friksi pendaftaran dan mengalihkan verifikasi identitas email ke penyedia tepercaya |
| SMTP Brevo | Pengiriman kode OTP verifikasi akun | Menyediakan pengiriman surel transaksional yang dapat diandalkan tanpa mengelola server surel sendiri |
| Titik Akhir AI OpenAI Compatible | Sumber model teks, suara, dan gambar | Satu bentuk antarmuka pemanggilan untuk banyak penyedia sehingga penambahan penyedia tidak mengubah kode inti sesuai NFR-037 |

Asumsi: versi minimum PHP dan ekstensi yang tersedia pada paket shared hosting (PDO SQLite, cURL, OpenSSL) memadai untuk menjalankan seluruh modul di atas.

## Alur Data

Alur 1 — Pendaftaran manual sampai akun terverifikasi:
Formulir pendaftaran di lapisan Tampilan dikirim ke lapisan Permintaan, divalidasi (termasuk pencocokan kata sandi dan pengulangan), lalu lapisan Layanan menyimpan akun berstatus belum terverifikasi dan meminta Modul Notifikasi Email mengirim kode OTP melalui SMTP Brevo. Pengguna memasukkan kode pada halaman verifikasi. Lapisan Layanan memeriksa kecocokan kode, masa berlaku 10 menit, dan batas 5 percobaan, kemudian mengubah status akun menjadi terverifikasi.

Alur 2 — Pendaftaran dan masuk melalui Google:
Permintaan diarahkan ke OAuth Google. Setelah pengguna menyetujui, Google mengembalikan identitas ke lapisan Integrasi. Lapisan Layanan mencari akun berdasarkan email; bila belum ada, akun dibuat dengan status terverifikasi. Sesi pengguna lalu dibuka dan diarahkan ke dashboard.

Alur 3 — Pencatatan melalui suara:
Peramban merekam audio dan mengirimkannya ke lapisan Permintaan. Lapisan Layanan meminta Modul AI Orchestrator memilih model berkapasitas masukan audio dan keluaran teks dari pool sesuai urutan prioritas global. Hasil transkripsi dikembalikan, dipetakan ke field transaksi (tanggal, nominal, deskripsi), lalu ditampilkan pada form untuk dikonfirmasi pengguna sebelum disimpan.

Alur 4 — Pencatatan melalui foto struk:
Peramban mengirim berkas gambar ke lapisan Permintaan. Lapisan Layanan meminta Modul AI Orchestrator memilih model berkapasitas masukan gambar dan keluaran teks. Hasil ekstraksi dipetakan ke field transaksi dan ditampilkan pada form untuk dikonfirmasi sebelum disimpan.

Alur 5 — Pencarian bahasa alami:
Kueri teks atau suara dikirim ke lapisan Permintaan. Modul AI Orchestrator memilih model teks ke teks yang mengubah kueri menjadi parameter filter terstruktur (rentang waktu, jenis transaksi, kata kunci deskripsi, rentang nominal). Modul Pencarian menerjemahkan parameter tersebut menjadi kueri basis data yang sama dengan yang dipakai filter manual, lalu mengembalikan hasil dan menampilkan filter aktif yang setara.

Alur 6 — Kolaborasi dan log aktivitas:
Pemilik workspace mengundang pengguna terdaftar dan terverifikasi. Setiap aksi tambah, ubah, atau hapus pada transaksi dan keanggotaan workspace melewati Modul Log Aktivitas yang menyimpan waktu, pelaku, jenis aksi, dan objek terkait. Seluruh anggota kolaborasi dapat membaca log tersebut.

Alur 7 — Ekspor data:
Permintaan ekspor divalidasi terhadap keanggotaan workspace. Lapisan Layanan membaca data workspace dan menghasilkan berkas CSV atau Excel yang dikirim ke peramban sebagai unduhan.

Alur 8 — Failover dan pemantauan pool AI:
Setiap permintaan AI melewati Modul AI Orchestrator yang mencoba model pada urutan prioritas teratas. Bila pemanggilan gagal atau melewati batas waktu 3 detik, sistem berpindah ke model berikutnya hingga maksimum 3 percobaan dan mencatat hasilnya. Pemeriksaan kesehatan massal di panel admin mengirim permintaan uji ke seluruh model, memperbarui status sehat atau bermasalah, lalu menampilkan ringkasan jumlah penyedia, jumlah model, jumlah model sehat, dan jumlah model bermasalah.

## Penyebaran

| Aspek | Rancangan |
|---|---|
| Lingkungan | Akun shared hosting cPanel dengan PHP Native; tanpa layanan basis data terpisah |
| Letak berkas publik | Direktori publik hanya memuat berkas yang dapat diakses peramban |
| Letak basis data dan kunci API | Di luar direktori publik dengan pembatasan izin baca-tulis oleh proses PHP |
| Berkas unggahan sementara | Disimpan sementara untuk ekstraksi AI dan dibersihkan menurut jadwal pada NFR-036 |
| Cadangan | Salinan basis data harian sesuai NFR-016 |
| Konfigurasi rahasia | Kunci API penyedia AI, kredensial OAuth Google, dan kredensial SMTP Brevo disimpan pada berkas konfigurasi sisi server |

## 15. Metrik Keberhasilan

| ID | KPI | Definisi | Target | Cara pengukuran | Frekuensi |
|---|---|---|---|---|---|
| KPI-01 | Tingkat aktivasi akun | Persentase akun baru terverifikasi yang mencatat minimal 1 transaksi dalam 24 jam sejak verifikasi | Asumsi: ≥ 60% | Perbandingan tabel akun terverifikasi dengan tabel transaksi memakai cap waktu | Bulanan |
| KPI-02 | Retensi pengguna hari ke-7 | Persentase pengguna yang membuka aplikasi dan mencatat transaksi pada hari ke-7 setelah pendaftaran | Asumsi: ≥ 30% | Kueri kohort berdasarkan cap waktu aktivitas | Bulanan |
| KPI-03 | Retensi pengguna hari ke-30 | Persentase pengguna yang mencatat transaksi pada hari ke-30 setelah pendaftaran | Asumsi: ≥ 20% | Kueri kohort berdasarkan cap waktu aktivitas | Bulanan |
| KPI-04 | Waktu pencatatan input manual | Median durasi dari form dibuka sampai transaksi tersimpan | ≤ 20 detik | Pencatatan waktu buka form dan waktu simpan pada log aplikasi | Mingguan |
| KPI-05 | Waktu pencatatan input suara atau foto | Median durasi dari pengambilan suara atau foto sampai transaksi tersimpan | ≤ 30 detik | Pencatatan waktu unggah dan waktu simpan pada log aplikasi | Mingguan |
| KPI-06 | Akurasi ekstraksi struk | Persentase field wajib (tanggal, nominal, deskripsi) yang benar tanpa koreksi manual pada uji foto struk | ≥ 80% | Uji teradwal terhadap kumpulan foto struk contoh, dinilai manual | Bulanan |
| KPI-07 | Kesesuaian pencarian bahasa alami | Persentase kueri bahasa alami yang menghasilkan himpunan hasil identik dengan filter manual yang setara | Asumsi: ≥ 85% | Uji teradwal terhadap daftar kueri contoh, dibandingkan dengan hasil filter manual | Bulanan |
| KPI-08 | Keberhasilan permintaan AI | Persentase permintaan AI yang selesai tanpa galat yang terlihat pengguna setelah mekanisme fallback dijalankan | Asumsi: ≥ 95% | Log permintaan AI per model dan per status | Mingguan |
| KPI-09 | Kesehatan pool model | Persentase model aktif dan sehat dari seluruh model terdaftar | Asumsi: ≥ 90% | Ringkasan pemeriksaan kesehatan massal pada panel admin | Mingguan |
| KPI-10 | Adopsi kolaborasi | Persentase workspace yang memiliki minimal 2 anggota aktif | Asumsi: ≥ 25% | Kueri tabel keanggotaan workspace | Bulanan |
| KPI-11 | Kelengkapan jejak aktivitas | Persentase aksi ubah, tambah, dan hapus pada workspace yang memiliki entri log lengkap dengan waktu dan pelaku | 100% | Perbandingan jumlah aksi pada uji terkendali dengan jumlah entri log | Bulanan |

## Aturan Pelaporan

| Aspek | Ketentuan |
|---|---|
| Sumber data | Seluruh KPI dihitung dari basis data aplikasi dan log aplikasi; tidak memakai data yang diisi manual kecuali KPI-06 dan KPI-07 |
| Pengesampingan | KPI yang belum dapat dihitung karena volume data belum memadai ditandai "belum memadai", bukan diisi angka perkiraan |
| Ambang tindak lanjut | KPI yang berada di bawah target selama 2 periode pengukuran berturut-turut wajib disertai catatan penyebab dan rencana perbaikan |
| Pemilik | Pemilik produk menetapkan target dan meninjau hasil; pelaksana teknis menyediakan perhitungan |
| Penyimpanan hasil | Hasil tiap periode disimpan sebagai catatan berkala agar tren antarperiode dapat dibandingkan |
