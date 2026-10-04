# QA REQUIREMENTS SPECIFICATION: SIVO PUSKESMAS
**Dokumen Standar Kebutuhan Quality Assurance, Lingkup Audit, dan Kriteria Keberterimaan Sistem**
**Target Sistem:** SIVO Puskesmas (Sistem Informasi Inventori Obat Terpadu) Puskesmas Wuluhan, Jember
**Versi Dokumen:** 1.0.0
**Tanggal:** 2026-10-03
**Status:** Approved for Execution

---

## 1. PENDAHULUAN & RUANG LINGKUP

### 1.1 Maksud dan Tujuan
Dokumen ini menetapkan spesifikasi persyaratan pengujian sistem SIVO Puskesmas. QA Engineer atau Agent eksekutor (Antigravity) wajib menggunakan dokumen ini sebagai tolok ukur pengujian menyeluruh guna memastikan keandalan mutasi inventori obat, integritas data klinis resep, pencegahan celah keamanan, dan stabilitas operasional pada faskes tingkat pertama.

### 1.2 Batasan Sistem (Scope of Assessment)
Audit dan testing mencakup seluruh subsistem berikut:
1. **Modul Autentikasi dan Otorisasi (RBAC):** 5 role wewenang (Super Admin, Admin Gudang, Apotek, Sub-Unit Depan, Sub-Unit Belakang).
2. **Modul Master Data:** Data obat, kategori, satuan, sub-unit pelayanan, dan user credentials.
3. **Modul Distribusi Stok (Two-Tier Distribution):** Penerimaan obat gudang induk, pengajuan permintaan (request) sub-unit, verifikasi bertingkat, distribusi bertahap (partial delivery), dan konfirmasi penerimaan unit.
4. **Modul Pelayanan Resep & Farmasi Klinis:** Resep rawat jalan, rawat inap, retur obat, dan tracking indikator sasaran mutu pelayanan (waktu tunggu, telaah resep).
5. **Modul Penyesuaian & Opname Stok:** Stok opname periodik, rekonsiliasi selisih fisik vs sistem, dan pencatatan berita acara.
6. **Modul Kartu Stok & Audit Trail:** Pencatatan buku besar log stok obat per unit yang bersifat immutable (append-only).
7. **Modul Pelaporan & Ekspor:** LPLPO bulanan, mutasi sub-unit, kunjungan pasien, indikator mutu, dalam format HTML Print/PDF dan Microsoft Excel.

---

## 2. ARSITEKTUR LINGKUNGAN PENGUJIAN

### 2.1 Spesifikasi Lingkungan Uji (Test Environment)
- **Runtime:** PHP Native 8.0 sampai 8.2+
- **Database Engine:** MySQL 8.0 / MariaDB 10.4+ (InnoDB Engine mandatory)
- **Database Driver:** PHP Data Objects (PDO) dengan konfigurasi:
  - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
  - `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`
  - `PDO::ATTR_EMULATE_PREPARES => false`
- **Web Server:** Apache 2.4+ / Nginx dengan mod_rewrite aktif
- **Frontend Stack:** Bootstrap 5 / Tailwind CSS, jQuery 3.6+, DataTables 1.13+, Select2, SweetAlert2
- **Kondisi Data Awal:** Minimal memuat 100 master obat aktif, 10 sub-unit pelayanan, 1.000 data historis log stok, dan 50 data resep aktif.

---

## 3. PERSYARATAN FUNGSIONAL KRITIS (CRITICAL USER JOURNEYS)

Setiap skenario di bawah ini berstatus **P0 (Blocker)**. Kegagalan pada alur ini membatalkan rilis versi:

### 3.1 CUJ-01: Penerimaan Obat Masuk Gudang Induk
1. Input nomor faktur/batch, tanggal kedaluwarsa, produsen/sumber anggaran, harga beli, dan kuantitas masuk.
2. Sistem wajib mengunci baris stok terkait dengan mekanisme transaksi database (`BEGIN TRANSACTION`).
3. Kuantitas pada `tbl_stok_inventori` bertambah secara akurat.
4. Record baru tercipta pada `tbl_log_stok` dengan tipe `masuk`, mencatat stok sebelum, jumlah mutasi, dan stok akhir.
5. `stok_akhir` wajib sama persis dengan `stok_awal + jumlah_masuk`.
6. Seluruh proses dieksekusi secara atomik (`COMMIT`), jika terjadi kegagalan sistem melakukan `ROLLBACK` tanpa menyisakan data yatim (*orphaned records*).

### 3.2 CUJ-02: Distribusi Berjenjang & Partial Delivery
1. Sub-unit mengajukan permintaan 100 tablet Paracetamol ke Gudang Induk. Status awal: `Menunggu Konfirmasi`.
2. Gudang menyetujui alokasi 60 tablet (pengiriman parsial karena kuota terbatas).
3. Status berubah menjadi `Dikirim Sebagian` (*partial delivery*), dengan `jumlah_disetujui = 60`, `jumlah_terpenuhi = 0`.
4. Sub-unit memeriksa fisik barang dan menekan tombol konfirmasi penerimaan.
5. Eksekusi simultan di dalam 1 transaksi database:
   - Stok Gudang Induk berkurang 60.
   - Record log keluar di Gudang Induk terbentuk.
   - Stok Sub-Unit bertambah 60.
   - Record log masuk di Sub-Unit terbentuk.
   - Kolom `jumlah_terpenuhi` bertambah menjadi 60.
   - Sisa backlog 40 tablet tetap tercatat aktif untuk pemenuhan tahap berikutnya.

### 3.3 CUJ-03: Pelayanan Resep Klinis & Pengurangan Stok Real-Time
1. Petugas Apotek / Poli menginput resep dengan multiple item obat.
2. Sistem mengecek ketersediaan fisik stok pada unit yang bersangkutan:
   - Jika stok salah satu item kurang, transaksi langsung ditolak (*aborted*) dengan notifikasi detail item yang defisit.
   - Tidak boleh terjadi pemotongan stok sebagian jika ada item lain yang gagal.
3. Jika stok mencukupi:
   - Resep tersimpan di `tbl_resep` dan `tbl_detail_resep`.
   - Stok unit terpotong secara otomatis per item obat.
   - Terbit log mutasi keluar pada `tbl_log_stok` yang mereferensikan ID resep.
   - Waktu input dan waktu penyerahan terekam presisi untuk audit indikator sasaran mutu pelayanan farmasi.

### 3.4 CUJ-04: Pembatalan Resep dan Restorasi Stok
1. Resep yang salah input dibatalkan oleh user berwenang (Super Admin / Apoteker Penanggung Jawab).
2. Sistem membatalkan transaksi resep.
3. Kuantitas obat yang sempat terpotong wajib dikembalikan secara utuh ke `tbl_stok_inventori` unit bersangkutan.
4. Sistem mencatat log transaksi tipe `retur_resep` atau `koreksi_masuk` pada `tbl_log_stok` untuk mencegah selisih audit fisik.

---

## 4. PERSYARATAN INTEGRITAS & KONSISTENSI DATA

1. **Aturan Mutasi Absolut:**
   Stok akhir pada tabel inventori harus selalu memenuhi formula matematika:
   `Stok Akhir = Stok Awal + Total Masuk - Total Keluar`
2. **Immutable Audit Trail:**
   Data pada `tbl_log_stok` dilarang keras dapat diedit (`UPDATE`) atau dihapus (`DELETE`) melalui antarmuka aplikasi. Hanya operasi penambahan data (`INSERT`) yang diizinkan.
3. **Foreign Key Integrity:**
   Setiap transaksi detail wajib terikat kuat ke header melalui constraint `ON DELETE RESTRICT` atau `ON DELETE CASCADE` yang terdefinisi eksplisit tanpa ada *hanging records*.
4. **Mekanisme Pencegahan Race Condition:**
   Operasi perubahan stok wajib menggunakan query penguncian baris (`SELECT ... FOR UPDATE`) dalam scope `PDO::beginTransaction()`.

---

## 5. PERSYARATAN NON-FUNGSIONAL (SLA TARGETS)

### 5.1 Kinerja (Performance SLA)
- Waktu respons halaman rata-rata (TTFB + Render): < 1.5 detik.
- Waktu respons pencarian data obat via AJAX (Select2/DataTables): < 300 ms.
- Waktu kalkulasi dan render laporan LPLPO tahunan: < 3.0 detik.
- Ekspor laporan ke Microsoft Excel (.xlsx/.csv) untuk 5.000 baris data: < 5.0 detik.
- Kapasitas beban simultan: Stabil menangani minimal 30 koneksi aktif bersamaan tanpa connection timeout atau deadlock database.

### 5.2 Keamanan Sistem (Security Requirements)
- Perlindungan penuh terhadap ancaman OWASP Top 10 (Injection, Broken Authentication, Sensitive Data Exposure, Broken Access Control, XSS, CSRF).
- Tidak ada query database yang menggunakan konkatenasi string secara langsung. 100% dynamic input wajib menggunakan prepared statements dengan PDO parameter binding.
- Penegakan otorisasi di tingkat server (backend script), bukan hanya pembatasan tampilan menu HTML/CSS frontend.
- Seluruh output teks yang berasal dari input pengguna wajib melalui fungsi sanitasi `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')`.
- Session fixation prevention dengan `session_regenerate_id(true)` saat proses autentikasi berhasil.

### 5.3 Kualitas Konten & Antarmuka (No AI Slop Compliance)
- Seluruh teks antarmuka, dialog konfirmasi SweetAlert2, pesan kesalahan, dan dokumen operasional wajib memenuhi standar ketat anti-slop.
- Bebas dari kata-kata klise terlarang, filler phrases, dan nada dramatis artifisial.
- Pesan sistem harus langsung pada pokok masalah, teknis, definitif, dan menyertakan panduan solusi yang dapat ditindaklanjuti.

---

## 6. DEFINITION OF DONE (DOD)
Sebuah fitur atau modul dinyatakan selesai dan siap produksi jika:
1. Lulus 100% skenario pada dokumen `TEST_SCENARIOS_MATRIX.md`.
2. Nol temuan bug berkategori Blocker atau Critical pada `BUG_TRACKING_TEMPLATE.md`.
3. Memenuhi seluruh parameter kepatuhan pada `SECURITY_AUDIT_CHECKLIST.md`.
4. Lolos uji ambang batas metrik pada `PERFORMANCE_BASELINE.md`.
5. Nilai audit pada `SLOP_CONTENT_AUDIT.md` berada di bawah batas ambang maksimal 5%.
