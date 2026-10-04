# TEST SCENARIOS MATRIX SPECIFICATION: SIVO PUSKESMAS
**Matriks Uji Fungsional, Integrasi End-to-End, Concurrency, dan Edge Cases**
**Target Sistem:** SIVO Puskesmas
**Versi Dokumen:** 1.0.0
**Tanggal:** 2026-10-03

---

## 1. STRUKTUR MATRIKS PENGUJIAN

Matriks ini mencakup 7 domain pengujian utama:
1. **MOD-01: Autentikasi, Sesi, dan RBAC**
2. **MOD-02: Manajemen Master Data Farmasi & Unit**
3. **MOD-03: Rantai Pasok Gudang Induk & Pengadaan**
4. **MOD-04: Distribusi Bertingkat & Partial Delivery**
5. **MOD-05: Pelayanan Resep Klinis & Retur Farmasi**
6. **MOD-06: Stok Opname & Penyesuaian Selisih Fisik**
7. **MOD-07: Pelaporan, Kartu Stok, dan Rekonsiliasi LPLPO**

---

## 2. MATRIKS DETAIL SKENARIO UJI

### 2.1 MOD-01: Autentikasi & Role-Based Access Control (RBAC)

| Test ID | Skenario Pengujian | Peran Pengguna | Input & Langkah Uji | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-AUTH-01** | Login kredensial valid | Semua Role | Masukkan username dan password benar. | Berhasil login, sesi terbentuk, diarahkan ke dashboard sesuai role masing-masing, cookie session di-regenerate. | Wajib Pass |
| **TC-AUTH-02** | Login kredensial salah | Publik | Masukkan password salah 3 kali berturut-turut. | Muncul notifikasi error jelas, password hash tidak bocor, sesi tidak terbentuk. | Wajib Pass |
| **TC-AUTH-03** | Bypass URL langsung ke Master Data | Sub-Unit Depan (Poli Umum) | Buka URL langsung `master/obat.php` dan `master/user.php`. | Ditolak oleh server (HTTP 403 / Redirect), pesan: "Akses Ditolak: Anda tidak memiliki wewenang untuk modul ini". | Wajib Pass |
| **TC-AUTH-04** | Akses Form Approve Permintaan | Sub-Unit Belakang (UGD) | Buka URL langsung `request/approval.php?id=12`. | Akses ditolak. Modul approval hanya untuk Admin Gudang dan Super Admin. | Wajib Pass |
| **TC-AUTH-05** | Akses Laporan LPLPO Tingkat Puskesmas | Sub-Unit Depan | Buka URL `laporan/lplpo_puskesmas.php`. | Akses ditolak atau sistem hanya mengizinkan melihat mutasi unitnya sendiri. | Wajib Pass |
| **TC-AUTH-06** | Session Timeout & Invalidation | Semua Role | Diamkan sesi aktif selama batas timeout (misal 30 menit), lalu klik aksi navigasi. | Sesi otomatis musnah, dialihkan ke halaman login dengan notifikasi sesi kedaluwarsa. | Wajib Pass |
| **TC-AUTH-07** | Logout Cleanup | Semua Role | Klik tombol logout. | `session_destroy()`, session cookie dihapus, tombol back browser tidak dapat mengakses halaman sebelumnya (cache-control header). | Wajib Pass |

---

### 2.2 MOD-02: Manajemen Master Data Farmasi & Unit

| Test ID | Skenario Pengujian | Peran Pengguna | Input & Langkah Uji | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-MST-01** | Tambah Master Obat dengan Kode Unik | Admin Gudang | Input obat baru dengan kode yang belum pernah ada. | Data tersimpan di `tbl_master_obat`. Terbentuk row inisialisasi stok 0 di `tbl_stok_inventori` untuk gudang dan unit. | Wajib Pass |
| **TC-MST-02** | Cegah Duplikasi Kode Obat | Admin Gudang | Input obat baru dengan kode obat yang sudah ada. | Sistem menolak dengan validasi: "Kode obat sudah terdaftar". Tidak terjadi duplicate key crash. | Wajib Pass |
| **TC-MST-03** | Hapus Obat yang Memiliki Histori Mutasi | Admin Gudang | Hapus obat yang sudah tercatat di `tbl_log_stok` atau `tbl_resep`. | Ditolak oleh foreign key constraint (`RESTRICT`). Muncul pesan: "Obat tidak dapat dihapus karena memiliki histori transaksi aktif". | Wajib Pass |
| **TC-MST-04** | Edit Master Obat (Satuan & Kategori) | Admin Gudang | Ubah kategori dan satuan obat. | Data terupdate, histori transaksi sebelumnya tetap mempertahankan integritas data log. | Wajib Pass |

---

### 2.3 MOD-03: Rantai Pasok Gudang Induk (Penerimaan Obat)

| Test ID | Skenario Pengujian | Peran Pengguna | Input & Langkah Uji | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-STK-01** | Input Penerimaan Obat Gudang | Admin Gudang | Input faktur baru, pilih obat, batch, exp date, qty 500. | Stok gudang bertambah 500, log mutasi masuk tercatat dengan stok awal, mutasi, dan stok akhir yang valid. | Wajib Pass |
| **TC-STK-02** | Input Penerimaan dengan Batch & Expired Lewat | Admin Gudang | Input obat dengan tanggal kedaluwarsa sebelum tanggal hari ini. | Sistem menolak dengan validasi error: "Tanggal kedaluwarsa tidak boleh kurang dari tanggal saat ini". | Wajib Pass |
| **TC-STK-03** | Input Penerimaan dengan Qty Negatif atau Nol | Admin Gudang | Input kuantitas 0 atau -50. | Validasi form frontend dan validasi server memblokir input dengan error kuantitas harus lebih dari 0. | Wajib Pass |
| **TC-STK-04** | Rollback saat Error Input Multi-Item | Admin Gudang | Input 3 item obat, item ketiga dipaksa error database (data korup). | Transaksi di-rollback total. Item 1 dan 2 tidak ikut bertambah secara parsial ke tabel inventori. | Wajib Pass |

---

### 2.4 MOD-04: Distribusi Bertingkat & Partial Delivery

| Test ID | Skenario Pengujian | Peran Pengguna | Input & Langkah Uji | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-DST-01** | Pengajuan Permintaan Normal | Sub-Unit (Poli Gigi) | Buat request obat Amoxicillin 100 tablet ke Gudang. | Tersimpan di `tbl_request_obat` status `Menunggu Verifikasi`. Stok gudang belum terpotong. | Wajib Pass |
| **TC-DST-02** | Approval Penuh oleh Gudang | Admin Gudang | Setujui request 100 tablet (disetujui = 100). | Status berubah menjadi `Disetujui/Dikirim`. Stok gudang berkurang 100, stok unit belum bertambah sebelum dikonfirmasi. | Wajib Pass |
| **TC-DST-03** | Partial Delivery Approval | Admin Gudang | Stok gudang hanya 60. Gudang menyetujui 60 dari 100 yang diminta. | Status menjadi `Dikirim Sebagian`. Qty disetujui: 60, backlog sisa: 40. Log mutasi gudang keluar 60. | Wajib Pass |
| **TC-DST-04** | Konfirmasi Penerimaan oleh Sub-Unit | Sub-Unit (Poli Gigi) | Klik konfirmasi terima untuk pengiriman 60 tablet. | Stok Poli Gigi bertambah 60. `jumlah_terpenuhi` menjadi 60. Log masuk Poli Gigi tercatat rapi. | Wajib Pass |
| **TC-DST-05** | Pemenuhan Sisa Backlog (Second Batch) | Admin Gudang | Gudang menerima stok baru, lalu memenuhi sisa 40 tablet untuk request yang sama. | Qty disetujui sisa terkirim 40. Setelah dikonfirmasi, total `jumlah_terpenuhi = 100`, status berubah final `Selesai`. | Wajib Pass |
| **TC-DST-06** | Penolakan Permintaan Obat | Admin Gudang | Tolak pengajuan dari sub-unit dengan alasan logis. | Status menjadi `Ditolak`. Tidak ada stok gudang maupun unit yang terpotong. | Wajib Pass |

---

### 2.5 MOD-05: Pelayanan Resep Klinis & Retur Obat

| Test ID | Skenario Pengujian | Peran Pengguna | Input & Langkah Uji | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-RSP-01** | Input Resep Rawat Jalan Normal | Apotek | Pasien umum, 3 item obat dengan stok fisik mencukupi. | Resep tersimpan, stok apotek terpotong real-time, log keluar per item tercipta dengan referensi nomor resep. | Wajib Pass |
| **TC-RSP-02** | Validasi Stok Defisit saat Resep Dibuat | Apotek | Stok Salbutamol di Apotek = 2. Pasien minta 5. | Sistem menolak menyimpan dengan pesan stok tidak cukup. Stok tidak boleh minus. Resep tidak boleh tercipta. | Wajib Pass |
| **TC-RSP-03** | Pembatalan / Void Resep | Apotek / Super Admin | Lakukan pembatalan transaksi resep yang salah resep. | Status resep menjadi `Dibatalkan`. Seluruh stok obat dikembalikan (restorasi) ke unit apotek, kartu stok mencatat log koreksi masuk. | Wajib Pass |
| **TC-RSP-04** | Indikator Sasaran Mutu Farmasi | Apotek | Catat waktu resep diterima vs waktu resep diserahkan. | Durasi waktu tunggu terhitung akurat dalam menit/detik. Terbaca pada agregasi laporan sasaran mutu klinis. | Wajib Pass |

---

### 2.6 MOD-06: Concurrency & Race Condition Scenarios

| Test ID | Skenario Pengujian | Peran Pengguna | Input & Langkah Uji | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-RACE-01** | Rebutan Stok Terakhir oleh 2 Sub-Unit | Poli Umum & Poli Gigi | Stok Antasida di Gudang tersisa 50. Kedua unit mengajukan dan diapprove bersamaan masing-masing 50 dalam milidetik yang sama. | Salah satu transaksi berhasil mengambil 50, transaksi kedua gagal dengan error: "Stok gudang tidak mencukupi saat proses penguncian (Row Lock)". Stok gudang tidak boleh menjadi -50. | Wajib Pass |
| **TC-RACE-02** | Rebutan Resep Bersamaan di Apotek | Apoteker 1 & Apoteker 2 | Stok CTM tersisa 10. Dua petugas membuka form resep terpisah, masing-masing submit resep 10 tablet pada detik bersamaan. | Mekanisme `SELECT ... FOR UPDATE` mengantrikan transaksi. Transaksi 1 sukses, transaksi 2 ditolak karena stok sisa 0. | Wajib Pass |
| **TC-RACE-03** | Rekonsiliasi Kartu Stok saat Transaksi Simultan | Sistem | Lakukan 20 transaksi mutasi simultan melalui script automated test (Apache Bench / cURL script). | Nilai akhir pada `tbl_stok_inventori` sama persis dengan `SUM(masuk - keluar)` pada `tbl_log_stok`. Tidak ada dirty read. | Wajib Pass |

---

### 2.7 MOD-07: Pelaporan, Kartu Stok, dan Rekonsiliasi LPLPO

| Test ID | Skenario Pengujian | Peran Pengguna | Input & Langkah Uji | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-REP-01** | Integritas Matematika LPLPO | Admin Gudang | Buka laporan LPLPO bulan berjalan. | Kolom: `Stok Awal + Penerimaan - Pengeluaran - Rusak/Expired = Stok Akhir`. Formula konsisten tanpa selisih 1 digit pun. | Wajib Pass |
| **TC-REP-02** | Export Excel LPLPO | Admin Gudang | Klik tombol "Export Excel". | File terunduh valid format `.xlsx`, formula sel tetap terbaca angka (bukan string error), format tanggal sesuai standar ISO. | Wajib Pass |
| **TC-REP-03** | Filter Kartu Stok Lintas Tahun | Admin / Apotek | Filter kartu stok dari tanggal 01-12-2025 sampai 31-01-2026. | Data berurutan kronologis, saldo berjalan dihitung berkesinambungan tanpa reset saat pergantian tahun. | Wajib Pass |
