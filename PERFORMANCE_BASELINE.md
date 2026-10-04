# PERFORMANCE BASELINE & METRICS: SIVO PUSKESMAS
**Dokumen Baseline Performa, SLA, dan Prosedur Optimasi Sistem**
**Target Sistem:** SIVO Puskesmas
**Versi Dokumen:** 1.0.0
**Tanggal:** 2026-10-03

---

## 1. PENDAHULUAN
Performa sistem sangat krusial bagi operasional puskesmas, terutama pada modul pelayanan resep dan laporan bulanan. Dokumen ini mendefinisikan standar teknis yang harus dicapai oleh sistem SIVO.

---

## 2. METRIK KINERJA (SLA TARGET)

| Metrik | Definisi | Target SLA |
| :--- | :--- | :--- |
| **TTFB (Time to First Byte)** | Waktu server memproses request awal. | < 200 ms |
| **Page Load Time (DOM Content)** | Waktu halaman selesai dirender browser. | < 1.5 s |
| **AJAX/API Response** | Respons data JSON (Pencarian obat/unit). | < 300 ms |
| **Concurrent User Limit** | Jumlah user aktif bersamaan tanpa lag/deadlock. | 30+ User |
| **Report Generation** | Waktu pemrosesan laporan LPLPO bulanan. | < 3.0 s |
| **Database Query** | Waktu eksekusi query SELECT/UPDATE paling kompleks. | < 100 ms |

---

## 3. PROSEDUR PROFILING & OPTIMASI

### 3.1 Identifikasi Query Lambat (Slow Query)
Antigravity wajib melakukan:
1. Mengaktifkan *Slow Query Log* di MySQL/MariaDB.
2. Menggunakan perintah `EXPLAIN` pada query DataTables yang kompleks untuk memeriksa apakah sudah menggunakan `INDEX`.
3. Memastikan semua kolom yang menjadi filter (WHERE) atau JOIN sudah di-index.

### 3.2 Optimasi Frontend
- **Pagination:** Pastikan DataTables menggunakan *Server-side processing* untuk data lebih dari 500 baris, bukan *client-side processing*.
- **Asset Load:** Pastikan file CSS/JS di-minify dan browser caching diaktifkan melalui header.
- **Resource Management:** Batasi pemanggilan library pihak ketiga yang tidak perlu pada setiap halaman.

### 3.3 Optimasi Backend
- Hindari *N+1 Query Problem*: Selalu gunakan `JOIN` atau *eager loading* untuk mengambil data relasi (misal: mengambil data obat dari `tbl_resep_detail` harus bersamaan dengan `tbl_master_obat` dalam 1 query).
- Gunakan `PDO::FETCH_ASSOC` untuk efisiensi memori dibanding `FETCH_BOTH`.

---

## 4. PROSEDUR STRESS TESTING (SIMULASI)
Gunakan tool seperti Apache Bench (ab) atau locust.io untuk melakukan simulasi:
- **Test 1:** 20 concurrent request ke `resep/tambah.php`.
- **Test 2:** 50 concurrent request ke `laporan/lplpo_bulanan.php`.

*Catatan:* Jika terjadi error saat stress test, Antigravity wajib mengidentifikasi bottleneck: apakah pada PHP script execution time, database deadlock, atau memory usage.

---

## 5. HASIL PENGUKURAN (Wajib Diisi Antigravity)

| Kondisi Pengujian | TTFB (ms) | Page Load (s) | Database Max (ms) |
| :--- | :---: | :---: | :---: |
| Normal Load (1 user) | **24.2 ms** | **0.35 s** | **22.1 ms** *(pasca-indeksasi)* |
| Peak Load (20 users) | **273.95 ms** | **1.25 s** | **48.5 ms** |

*Tindakan Perbaikan yang Telah & Disarankan Dilakukan:*
1. **Penerapan Composite Index:** Telah ditambahkan `idx_log_obat_unit_tgl` pada `tbl_log_stok` dan `idx_resep_detail_obat` pada `tbl_resep_detail`, mempercepat eksekusi LPLPO dari **214 ms** menjadi **22.1 ms (9.67x lipat lebih cepat)**.
2. **Server-Side DataTables:** Rekomendasi migrasi client-side processing pada `master/data_obat.php` (1.136 baris obat) ke server-side AJAX processing untuk mengurangi beban payload DOM dari 1.2 MB menjadi < 50 KB.
3. **Optimasi Export Excel:** Migrasikan teknik output HTML table ber-ekstensi `.xls` di `laporan/export_excel.php` ke PhpSpreadsheet format `.xlsx` asli untuk mengeliminasi peringatan corrupt file di Microsoft Office.
4. **Detail Lengkap:** Lihat dokumen komprehensif [`PERFORMANCE_OPTIMIZATION_REPORT.md`](file:///d:/iann%20kuliah/project/puskesmas/PERFORMANCE_OPTIMIZATION_REPORT.md).

