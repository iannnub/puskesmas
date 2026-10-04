# BUG TRACKING & DEFECT REPORTING SPECIFICATION: SIVO PUSKESMAS
**Standar Pelaporan Cacat Perangkat Lunak, Klasifikasi Keparahan, dan Alur Perbaikan Sistem**
**Target Sistem:** SIVO Puskesmas
**Versi Dokumen:** 1.0.0
**Tanggal:** 2026-10-03

---

## 1. STRUKTUR & KLASIFIKASI KEPARAHAN (SEVERITY MATRIX)

Setiap cacat (bug) yang ditemukan selama proses pengujian wajib diklasifikasikan ke dalam 4 tingkatan keparahan berikut:

| Severity Level | Kriteria & Dampak Terhadap Sistem SIVO Puskesmas | Waktu Respons Maksimal | Tindakan Wajib |
| :--- | :--- | :--- | :--- |
| **P0 - Blocker** | Terjadinya selisih stok obat tanpa log audit, race condition saat potong stok simultan, SQL Injection, Bypass autentikasi/otorisasi yang mengizinkan akses ke data farmasi tanpa hak, crash database transaksi. | < 2 Jam | Blokir rilis, hotfix langsung ke branch utama. |
| **P1 - Critical** | Kegagalan fungsi partial delivery obat, form resep tidak bisa submit untuk kategori rawat inap, perhitungan LPLPO bulanan salah matematis, XSS tersimpan pada nama obat/pasien, CSRF pada endpoint delete. | < 8 Jam | Perbaikan prioritas sebelum pengujian subsistem lain. |
| **P2 - Major** | Fitur export Excel rusak atau format angka terpotong, filter tanggal kartu stok tidak akurat pada pergantian tahun, pagination DataTables macet pada data > 500 baris, pesan validasi form ambigu. | < 24 Jam | Dijadwalkan dalam siklus sprint aktif. |
| **P3 - Minor / Trivial** | Typo penulisan istilah medis/satuan, misalignment tombol aksi pada resolusi mobile, teks placeholder membingungkan, gaya CSS tidak seragam antar sub-modul. | < 48 Jam | Diselesaikan saat refactoring tampilan/antarmuka. |

---

## 2. KATEGORI CACAT (BUG CATEGORIES)
1. **SEC (Security Vulnerability):** Kerentanan injeksi, manipulasi sesi, otorisasi IDOR, bypass hak akses.
2. **DATA (Data Integrity & Inventory Logic):** Anomali stok negatif, data yatim (*orphan row*), ketidaksesuaian log vs inventori fisik.
3. **CONC (Concurrency & Race Condition):** Masalah transaksi saat beberapa user melakukan mutasi stok barang yang sama dalam detik bersamaan.
4. **PERF (Performance & Latency):** Query lambat, memory limit exhaustion, browser hang akibat loop JavaScript.
5. **FUNC (Functional Logic):** Fitur tidak berjalan sesuai aturan alur bisnis puskesmas.
6. **UIUX (Interface & Feedback):** Ketidaksesuaian dialog notifikasi, layout rusak, modal tidak tertutup otomatis.
7. **SLOP (Content & UX Copy Slop):** Teks konfirmasi menggunakan kata terlarang, filler AI bertele-tele, kalimat tidak jelas.

---

## 3. TEMPLATE LAPORAN BUG STANDARD (Wajib Diisi oleh Antigravity)

Salin template Markdown berikut untuk setiap temuan bug:

```markdown
### [BUG-ID] : [Judul Ringkas, Jelas, dan Berisi Komponen yang Rusak]

- **ID Laporan:** BUG-SIVO-001
- **Kategori:** [SEC / DATA / CONC / PERF / FUNC / UIUX / SLOP]
- **Tingkat Keparahan:** [P0 - Blocker / P1 - Critical / P2 - Major / P3 - Minor]
- **Modul Terkait:** [Master Obat / Gudang Induk / Resep / LPLPO / RBAC / dll]
- **Role Pengguna yang Terdampak:** [Super Admin / Admin Gudang / Apotek / Sub-Unit Depan / Sub-Unit Belakang]
- **Lokasi File & Baris Kode:** `lokasi/file/skrip.php:baris_angka`

#### 1. Deskripsi Masalah
Jelaskan secara presisi anomali yang terjadi. Hindari spekulasi, fokus pada fakta kegagalan sistem dan dampak operasionalnya pada puskesmas.

#### 2. Kondisi Awal (Pre-conditions)
- Akun login: (misal: user role `apotek`, ID Unit `3`)
- Kondisi database: (misal: Obat Paracetamol 500mg memiliki `stok_akhir = 5` pada `tbl_stok_inventori`)

#### 3. Langkah Rekonstruksi (Reproduction Steps)
1. Buka halaman URL `http://localhost/sivo/resep/tambah.php`.
2. Masukkan nama pasien "Budi Santoso", nomor RM "RM-10293".
3. Tambahkan item obat Paracetamol dengan kuantitas 10 (melebihi stok yang ada yaitu 5).
4. Klik tombol "Simpan Resep".

#### 4. Perilaku yang Diharapkan (Expected Behavior)
Sistem menolak penyimpanan dengan peringatan: "Stok Paracetamol tidak mencukupi (Tersedia: 5, Diminta: 10)". Tidak ada row baru pada `tbl_resep` dan stok tidak boleh berubah.

#### 5. Perilaku Aktual (Actual Behavior)
Form berhasil tersimpan. `tbl_resep` mencatat kuantitas 10, dan nilai stok pada `tbl_stok_inventori` berubah menjadi `-5` (stok minus). Kartu stok tidak mencatat log mutasi karena transaksi gagal separuh jalan tanpa proteksi rollback.

#### 6. Bukti Log / Error Trace / Query Database
Query yang dieksekusi:
```sql
UPDATE tbl_stok_inventori SET stok_akhir = stok_akhir - 10 WHERE id_obat = 12 AND id_unit = 3;
```
Error trace / database response:
```
Tidak ada constraint check pada database engine, stok dibiarkan bernilai negatif.
```

#### 7. Rekomendasi Solusi Teknis (Remediation Proposal)
1. Tambahkan constraint database: `CHECK (stok_akhir >= 0)`.
2. Tambahkan verifikasi backend sebelum update stok:
```php
$stmt = $pdo->prepare("SELECT stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE");
$stmt->execute([$id_obat, $id_unit]);
$stok = $stmt->fetchColumn();
if ($stok < $qty_diminta) {
    throw new Exception("Stok tidak mencukupi untuk obat: " . $nama_obat);
}
```
3. Bungkus seluruh mutasi dalam blok `try { $pdo->beginTransaction(); ... $pdo->commit(); } catch { $pdo->rollBack(); }`.

#### 8. Verifikasi Ulang (Verification Checklist)
- [ ] Bug berhasil direproduksi.
- [ ] Patch perbaikan diterapkan.
- [ ] Pengujian ulang dengan nilai ekstrim (stok 0, stok minus).
- [ ] Log mutasi kartu stok diverifikasi konsisten.
- [ ] Status ditutup oleh QA.
```

---

## 4. WORKFLOW RESOLUSI CACAT

1. **Discovery (Penemuan):** Antigravity menjalankan skenario uji dan menemukan anomali.
2. **Triage & Logging:** Antigravity mengisi format di atas dan menetapkan severity (P0-P3).
3. **Fixing (Perbaikan):** Penulisan patch kode program yang mematuhi standar keamanan dan performa.
4. **Regression Testing:** Verifikasi bahwa perbaikan tidak memicu efek samping (*side-effects*) pada modul lain.
5. **Closure:** Tiket ditutup jika dan hanya jika bukti log pengujian ulang menunjukkan hasil valid.
