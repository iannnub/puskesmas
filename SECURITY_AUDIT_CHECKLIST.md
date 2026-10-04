# SECURITY AUDIT CHECKLIST: SIVO PUSKESMAS
**Prosedur Audit Keamanan Sistem Informasi Inventori Obat Terpadu**
**Target Sistem:** SIVO Puskesmas
**Versi Dokumen:** 1.0.0
**Tanggal:** 2026-10-03
**Status:** Wajib Audit

---

## 1. PENDAHULUAN
Dokumen ini menetapkan standar audit keamanan untuk aplikasi SIVO Puskesmas guna memastikan data farmasi dan klinis terlindungi dari serangan siber. Antigravity wajib melakukan pengecekan per poin di bawah ini dengan metode *hands-on code review* dan *penetration testing*.

---

## 2. CHECKLIST AUDIT KEAMANAN

### 2.1 Autentikasi & Sesi (Authentication)
- [ ] **Session Fixation:** Apakah fungsi `session_regenerate_id(true)` dipanggil tepat setelah user berhasil login?
- [ ] **Brute Force Protection:** Apakah ada batasan jumlah login gagal (misal 5 kali) per IP atau per user? Apakah akun terkunci sementara (misal 15 menit)?
- [ ] **Cookie Security:** Apakah flag `HttpOnly` dan `Secure` (jika menggunakan HTTPS) sudah aktif pada set-cookie header?
- [ ] **Logout Invalidation:** Apakah sesi benar-benar musnah setelah logout dan cache browser dicegah melalui `Cache-Control: no-store, no-cache, must-revalidate`?

### 2.2 Otorisasi (Authorization & RBAC)
- [ ] **Broken Access Control:** Apakah setiap skrip PHP (di backend) melakukan validasi ulang *session role* pengguna sebelum menjalankan query database? (Jangan hanya menyembunyikan menu di CSS).
- [ ] **IDOR Prevention:** Jika user mengakses `resep/edit.php?id=123`, apakah sistem memverifikasi bahwa user tersebut memang memiliki wewenang atau hak atas data resep ID 123 tersebut?
- [ ] **Horizontal Privilege Escalation:** Apakah seorang user di Poli Umum bisa mengakses data transaksi di Poli Gigi atau Rawat Inap dengan sekadar mengganti URL ID?

### 2.3 Injection & Input Validation (Data Integrity)
- [ ] **SQL Injection (High Priority):** Cari semua query SQL dalam skrip PHP. **Wajib:** Tidak ada concatenasi variabel langsung di string SQL. Semua query wajib menggunakan `prepare()` dan `execute()` dengan *parameter binding*.
- [ ] **XSS (Stored/Reflected):** Apakah semua input (nama pasien, nama obat, catatan) yang akan ditampilkan kembali ke browser sudah dibersihkan melalui `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`?
- [ ] **Input Sanitization:** Apakah input kuantitas/harga dipaksa menjadi integer/float (`(int)$qty`, `(float)$harga`) sebelum diproses?
- [ ] **File Upload:** Jika aplikasi mendukung upload gambar/laporan, apakah validasi ekstensi (whitelist), MIME type, dan rename file (untuk mencegah execution shell script) sudah dilakukan?

### 2.4 Transaksi & Integrity (Race Condition)
- [ ] **Transaction Atomicity:** Apakah operasi mutasi stok yang melibatkan lebih dari 1 tabel (inventori, log, header transaksi) sudah dibungkus dalam `beginTransaction()` dan `commit()`?
- [ ] **Row Locking:** Apakah query update stok kritis menggunakan `SELECT ... FOR UPDATE` untuk mencegah race condition saat dua user bertransaksi bersamaan?
- [ ] **Constraint Check:** Apakah database sudah memiliki `CHECK` constraint (misal: `stok >= 0`) sebagai lapis pertahanan terakhir?

### 2.5 Server & Infrastructure Security
- [ ] **PDO Configuration:** Apakah `PDO::ATTR_EMULATE_PREPARES` sudah diset ke `false` untuk memaksa penggunaan *true prepared statements* oleh database engine?
- [ ] **Error Reporting:** Apakah aplikasi di mode produksi telah mematikan `display_errors` agar path sistem, struktur database, dan logika query tidak bocor saat terjadi error?
- [ ] **Sensitive Files:** Pastikan file konfigurasi (seperti `config.php` yang memuat database credentials) tidak dapat diakses secara publik dari browser.

---

## 3. PROSEDUR PENGUJIAN PENETRASI (PENTEST)
1. **Fuzzing Input:** Coba masukkan karakter `'`, `"`, `--`, `;` pada setiap input form untuk melihat reaksi sistem.
2. **URL Manipulation:** Coba ganti parameter ID pada URL untuk mencoba mengakses data milik role lain atau data milik user lain.
3. **Session Interception:** Coba gunakan tool seperti Burp Suite untuk menangkap request dan melihat apakah bisa dimanipulasi parameter harga atau kuantitas obat sebelum dikirim ke server.
4. **Logout Simulation:** Login, logout, lalu tekan tombol "Back" pada browser untuk melihat apakah data masih dapat diakses (seharusnya tidak).

---

## 4. HASIL AUDIT (Wajib Diisi Antigravity)

| Audit Item | Status (Pass/Fail) | Catatan / Temuan / Lokasi File |
| :--- | :---: | :--- |
| SQL Injection Protection | **FAIL** | Ditemukan raw variable concatenation pada `dashboard.php:32` (`WHERE id_user = " . $_SESSION['user_id']`). Mayoritas query lain sudah menggunakan prepared statement, namun `PDO::ATTR_EMULATE_PREPARES` masih `true` di `config.php:33`. |
| XSS Prevention | **PASS (Mayoritas)** | Seluruh field master dan laporan menggunakan `htmlspecialchars()`. Ditemukan over-sanitization password pada `auth/proses_login.php:10` yang merusak karakter khusus saat login. |
| RBAC Backend Validation | **FAIL** | Terjadi inkonsistensi session key antara `auth/proses_login.php` (`role_id`, `unit_stok_id`) dan `laporan/bulanan_poli.php:6` (`id_role`, `id_unit`) yang menyebabkan bypass otorisasi role non-apotek. Modul resep juga memblokir Apotek (`Role 5`) dan Super Admin (`Role 1`). |
| Race Condition Protection | **FAIL** | Operasi mutasi stok pada `stok/proses_terima.php`, `stok/proses_transfer.php`, dan `request/proses_setujui_gudang.php` belum menggunakan `SELECT ... FOR UPDATE`. Belum ada SQL CHECK constraint `stok_akhir >= 0` pada skema database awal. |
| Session Security | **FAIL** | `session_regenerate_id(true)` lolos di login, namun cookie `PHPSESSID` belum memiliki flag `HttpOnly`, `Secure`, dan `SameSite`. Tidak ada idle timeout dan header `Cache-Control: no-store` saat logout. |

*Tanda Tangan QA/Antigravity:* **Antigravity Automated QA & Security Audit Team**  
*Tanggal:* **2026-10-03**  
*Laporan Rinci:* Lihat file [`SECURITY_AUDIT_RESULTS.md`](file:///d:/iann%20kuliah/project/puskesmas/SECURITY_AUDIT_RESULTS.md)

