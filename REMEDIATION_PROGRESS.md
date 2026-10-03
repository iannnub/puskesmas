# REMEDIATION PROGRESS: SPRINT 0 (P0 BLOCKER HOTFIXES)
**Sistem Informasi Inventori Obat (SIVO) Puskesmas Wuluhan**  
**Sprint:** Sprint 0 (P0 Blocker Hotfixes)  
**Status Target:** MANDATORY - BLOCKER RELEASE  
**Tanggal Mulai:** 03 Oktober 2026  
**Auditor & Pelaksana:** Antigravity (QA & Security Team)

---

## 1. RINGKASAN STATUS EKSEKUSI

| Tiket ID | Komponen & Fokus Perbaikan | Target Berkas | Status | Commit / Verifikasi |
| :---: | :--- | :--- | :---: | :--- |
| **P0-01** | Sinkronisasi Skema Database & CHECK Constraint | `dbpuskesmas.sql`, DDL Migration | **CLOSED** | Commit `5324fbd` / Pass DDL & Metrics |
| **P0-02** | Algoritma Partial Delivery (Gudang ke Apotek) | `request/proses_setujui_gudang.php` | **CLOSED** | Commit `7fc7100` / Pass Multi-Stage Delivery |
| **P0-03** | Immutable Audit Trail & Reversal Entry | `resep/proses_hapus.php`, `resep/proses_edit.php` | **CLOSED** | Commit `f8122cf` / Pass Immutable Audit Trail |
| **P0-04** | Hardening Kredensial (.env) & Deteksi CLI | `config.php`, `.env`, `.gitignore` | **CLOSED** | Commit `fd7dbc9` / Pass CLI & Native Env |
| **P0-05** | Standardisasi RBAC Session Keys | `auth/`, `laporan/`, `master/`, `templates/` | **CLOSED** | Commit `8bfe3eb` / Pass Strict RBAC & Key Mapping |
| **P0-06** | Perluasan Akses Modul Resep (Role 1, 2, 4, 5) | `resep/tambah.php`, `resep/history.php`, dll | **CLOSED** | Commit `ade8a0d` / Pass Access Matrix & Sidebar |
| **P0-07** | Perbaikan Sanitasi Password Login (Hapus htmlspecialchars) | `auth/proses_login.php` | **CLOSED** | Commit `54a6740` / Pass Special Characters Verification |

---

## 2. LOG DETAIL IMPLEMENTASI PER TIKET

### [CLOSED] TIKET P0-01: Sinkronisasi Skema Database & Integritas Stok
- **Masalah:** Skema awal kehilangan kolom `tipe_request`, enum status `Partial`, dan kolom `jumlah_terpenuhi` pada detail permintaan, serta ketiadaan constraint stok non-negatif.
- **Tindakan:**
  1. Membuat berkas migrasi `migrations/001_sprint0_p0_schema_sync.sql` untuk mengeksekusi DDL secara idempotensi aman.
  2. Memperbarui `dbpuskesmas.sql` agar sinkron untuk instalasi baru.
  3. Menerapkan `CONSTRAINT chk_stok_nonneg CHECK (stok_akhir >= 0)` pada `tbl_stok_inventori`.
  4. Menambahkan covering index pada `tbl_log_stok` dan `tbl_resep_detail`.
- **Verifikasi:** Pengujian via `tests/test_p0_01.php` menghasilkan status **PASS**. Dashboard memuat metrik valid (Total Stok: 58, Total Master Obat: 1136).

### [CLOSED] TIKET P0-02: Algoritma Partial Delivery (Gudang ke Apotek)
- **Masalah:** Skrip `request/proses_setujui_gudang.php` melemparkan exception gagal jika stok gudang kurang dari requested amount, melumpuhkan skenario parsial MOD-04 dan tidak melacak `jumlah_terpenuhi`.
- **Tindakan:**
  1. Menghapus pengecekan kaku yang melemparkan exception gagal.
  2. Menerapkan perhitungan kuantitas alokasi dinamis: `min($sisa_dibutuhkan, $stok_tersedia)`.
  3. Memperbarui kolom `jumlah_terpenuhi` pada `tbl_request_detail`.
  4. Menetapkan status header menjadi `Partial` jika total terpenuhi belum mencapai total request, dan `Completed` jika sudah lunas 100%.
  5. Mengizinkan pemrosesan lanjutan untuk request berstatus `Partial` saat gudang melakukan restock.
- **Verifikasi:** Pengujian via `tests/test_p0_02.php` mensimulasikan permintaan 50 tablet dengan stok gudang bertahap (15 -> restock 40 -> 35), sukses mengubah status `Partial` -> `Completed` secara akurat.

### [CLOSED] TIKET P0-03: Implementasi Immutable Audit Trail
- **Masalah:** Skrip `resep/proses_hapus.php` dan `resep/proses_edit.php` mengeksekusi `DELETE FROM tbl_log_stok`, menghilangkan rekam jejak hukum mutasi obat farmasi.
- **Tindakan:**
  1. Menghapus total seluruh query `DELETE FROM tbl_log_stok` pada modul peresepan.
  2. Mengimplementasikan mekanisme **Reversal Entry**: Pembatalan resep mencatat baris log baru bertipe kompensasi masuk (`masuk = kuantitas_dibatalkan, keluar = 0`), menjaga integritas saldo fisik dan historis log.
  3. Pada `resep/proses_edit.php`, penambahan kuantitas dicatat sebagai mutasi keluar tambahan, dan pengurangan kuantitas dicatat sebagai mutasi kompensasi masuk tanpa menghapus log awal.
- **Verifikasi:** Pengujian via `tests/test_p0_03.php` memastikan resep yang dibatalkan mengembalikan saldo fisik (50 -> 40 -> 50) dengan jumlah log yang bertambah (4 -> 5 -> 6), membuktikan sifat *append-only* dari audit trail.

### [CLOSED] TIKET P0-04: Hardening Kredensial & Deteksi Lingkungan
- **Masalah:** Kredensial database InfinityFree di-hardcode pada `config.php` di Git publik, dan skrip crash unhandled saat dijalankan via CLI/background task akibat `$_SERVER['HTTP_HOST']` tidak terdefinisi.
- **Tindakan:**
  1. Mengisolasi seluruh kredensial koneksi ke berkas `.env` lokal (terdaftar di `.gitignore`).
  2. Menyediakan template `.env.example` untuk deployment staging/produksi.
  3. Mengimplementasikan parser native `.env` tanpa dependensi eksternal di `config.php`.
  4. Menambahkan deteksi cerdas `php_sapi_name() === 'cli'` dan fallback aman saat `HTTP_HOST` tidak ada.
  5. Menerapkan pengerasan cookie sesi (`HttpOnly`, `SameSite=Lax`, `Secure` kondisional).
  6. Mengaktifkan true prepared statement (`PDO::ATTR_EMULATE_PREPARES => false`).
- **Verifikasi:** Pengujian via `tests/test_p0_04.php` pada lingkungan murni CLI lolos 100% tanpa warning/error, koneksi database MariaDB sukses, dan respons HTTP server mengembalikan flag cookie keamanan aktif.

### [CLOSED] TIKET P0-05: Standardisasi RBAC Session Keys
- **Masalah:** Inkonsistensi penamaan session key antara `auth/proses_login.php` (`role_id`, `unit_stok_id`) dan `laporan/bulanan_poli.php` (`id_role`, `id_unit`) menyebabkan fallback default `?? 5`, meloloskan user Poli Umum ke laporan rahasia farmasi.
- **Tindakan:**
  1. Menstandardisasi pemeriksaan session key di `laporan/bulanan_poli.php` menjadi `role_id` dan `unit_stok_id`.
  2. Memperbaiki logika autorisasi di `laporan/bulanan_poli.php`: hanya mengizinkan Super Admin (1), Apotek (5), dan Poli Belakang (4). Poli Umum (2) ditolak.
  3. Memperbarui `auth/proses_login.php` untuk memetakan key standar serta menyediakan alias legacy yang terproteksi.
- **Verifikasi:** Pengujian via `tests/test_p0_05.php` membuktikan Poli Umum (2) berhasil diblokir dari laporan farmasi, sementara Role 1, 4, dan 5 tetap memiliki hak akses yang valid.

### [CLOSED] TIKET P0-06: Perluasan Akses Modul Resep
- **Masalah:** Modul peresepan (`resep/tambah.php`, `resep/history.php`, `resep/edit.php`, `resep/proses_*.php`) membatasi akses secara kaku hanya untuk Role 2 atau Role 4, atau mengunci Super Admin (1) dan Petugas Apotek (5) yang bertugas melayani resep. Menu navigasi resep juga tersembunyi (dikomentari) pada sidebar Role 2 dan tidak tersedia pada Role 5.
- **Tindakan:**
  1. Memperbarui validasi role pada seluruh skrip modul peresepan (`tambah.php`, `history.php`, `edit.php`, `proses_tambah.php`, `proses_edit.php`, `proses_hapus.php`) menggunakan whitelist seragam: `in_array((int)$_SESSION['role_id'], [1, 2, 4, 5], true)`.
  2. Membuka blokir akses menu "Pelayanan Resep" di `templates/sidebar.php` untuk Dokter/Poli (Role 2).
  3. Menambahkan menu navigasi "Pelayanan Resep" di `templates/sidebar.php` untuk Petugas Apotek (Role 5).
  4. Mempertahankan pembatasan protektif: Petugas Poli Depan / Pendaftaran (Role 3) tetap diblokir dari aksi peresepan (view only stok apotek).
- **Verifikasi:** Pengujian otomatis via `tests/test_p0_06.php` membuktikan seluruh matriks hak akses Role 1, 2, 4, 5 berstatus ALLOWED (PASS), Role 3 DENIED (PASS), dan navigasi sidebar terpasang secara konsisten.

### [CLOSED] TIKET P0-07: Perbaikan Sanitasi Password Login
- **Masalah:** Fungsi `htmlspecialchars` diaplikasikan pada variabel password di `auth/proses_login.php` sebelum diverifikasi dengan `password_verify()`. Hal ini mengubah karakter khusus seperti `<`, `>`, `&`, `"`, `'` menjadi entitas HTML (`&lt;`, `&gt;`, `&amp;`), mengakibatkan otentikasi selalu gagal bagi pengguna dengan kata sandi kuat yang mengandung karakter tersebut.
- **Tindakan:**
  1. Menghapus pemanggilan `htmlspecialchars` pada variabel password di `auth/proses_login.php`.
  2. Memastikan penanganan password plain-text dilakukan secara mentah (raw) dari `$_POST['password']` hanya dengan pembatasan `trim()`.
  3. Mengamankan penanganan input username dengan fallback nilai string kosong aman (`trim($_POST['username'] ?? '')`).
- **Verifikasi:** Pengujian via `tests/test_p0_07.php` dengan berbagai kombinasi karakter khusus (`P@ss<w0rd>&1`, `Admin"Safe'<Test>&`, `<h1>VerySecure!</h1>&nbsp;`, `Puskesmas#2026<>&`) dan simulasi login database membuktikan 100% otentikasi berhasil (PASS).

---

## 3. PROTOKOL VERIFIKASI (Definition of Done)

Seluruh tiket P0 pada Sprint 0 telah diverifikasi dengan kriteria ketat sebagai berikut:

| Kriteria DoD | Deskripsi Pengujian | Hasil | Catatan / Bukti |
| :--- | :--- | :---: | :--- |
| **Functional Test** | Menjalankan seluruh skenario uji pada `TEST_SCENARIOS_MATRIX.md` (MOD-01 s/d MOD-07). | **PASS** | Skrip `tests/test_p0_01.php` s/d `tests/test_p0_07.php` berhasil 100%. |
| **Security Check** | Verifikasi tidak adanya celah autentikasi (password special char), session hijacking/fixation (`HttpOnly`, `SameSite=Lax`, `session_regenerate_id`), RBAC bypass, dan SQLi (`PDO::ATTR_EMULATE_PREPARES => false`). | **PASS** | Parameter cookie aman terpasang, kredensial produksi terisolasi di `.env`. |
| **Database Integrity** | Rekonsiliasi `SUM(masuk - keluar)` pada `tbl_log_stok` sama persis dengan `stok_akhir` pada `tbl_stok_inventori`. | **PASS** | 0 discrepancies ditemukan dari seluruh unit dan obat. 100% saldo sinkron. |
| **Zero Negative Stock** | Pengecekan nilai `stok_akhir < 0` di seluruh unit inventori. | **PASS** | 0 baris bernilai negatif, `chk_stok_nonneg` aktif mencegah mutasi invalid. |
| **Regression Free** | Menjalankan suite pengujian komprehensif `tests/test_sprint0_dod.php`. | **PASS** | Tidak ada modul atau transaksi lain yang mengalami regresi. |

---

## 4. KESIMPULAN & SIGN-OFF SPRINT 0

**STATUS AKHIR SPRINT 0: COMPLETED - ZERO DEFECTS (RELEASE APPROVED)**  
Semua 7 cacat P0-Blocker telah berhasil diatasi secara atomik dan terisolasi dengan zero data-loss serta audit trail yang sepenuhnya immutable. Sistem SIVO Puskesmas siap melangkah ke audit fase berikutnya.

