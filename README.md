# SIVO Puskesmas: Sistem Informasi Inventori Obat

SIVO (Sistem Informasi Inventori Obat) adalah aplikasi web untuk mencatat inventori, riwayat distribusi, dan permintaan obat antar-unit di Puskesmas Wuluhan, Kabupaten Jember.

- **Frontend (Vercel):** [https://sivo-wuluhan.vercel.app](https://sivo-wuluhan.vercel.app)
- **Aplikasi Web (Backend):** [https://sivo-wuluhan.page.gd/](https://sivo-wuluhan.page.gd/)

---

## Fitur Utama

- **Manajemen Stok:** Pencatatan penerimaan obat gudang, mutasi transfer antar-unit, dan stok opname berkala.
- **Permintaan Obat Antar-Unit:** Alur permintaan dari poli ke apotek serta apotek ke gudang dengan verifikasi kuantitas.
- **Pelayanan Resep:** Pencatatan penggunaan obat per pasien beserta riwayat peresepan tiap poli.
- **Pelaporan:** Ekspor laporan mutasi bulanan (LPLPO), rekapitulasi harian, dan kartu stok ke format PDF dan Excel.
- **Akses Berbasis Peran (RBAC):** Pemisahan wewenang akses untuk Super Admin, Petugas Gudang, Apoteker, dan Petugas Poli.

---

## Tech Stack

- **Backend:** PHP Native (PDO)
- **Database:** MariaDB / MySQL
- **Frontend:** Bootstrap 4 (SB Admin 2), jQuery, DataTables
- **Ekspor Dokumen:** TCPDF (PDF) dan PhpSpreadsheet (Excel)
- **Hosting:** Apache / cPanel / InfinityFree, Vercel (landing page)

---

## Instalasi Lokal

Langkah instalasi sistem pada lingkungan lokal:

1. **Clone repositori:**
   ```bash
   git clone https://github.com/iannnub/puskesmas.git
   cd puskesmas
   ```

2. **Konfigurasi Database:**
   - Nyalakan service **Apache** dan **MySQL** (misal via XAMPP).
   - Buat database baru bernama `dbpuskesmas` melalui phpMyAdmin atau MySQL CLI:
     ```sql
     CREATE DATABASE dbpuskesmas;
     ```
   - Import skema dan data awal dari file `dbpuskesmas.sql`.

3. **Pengaturan Koneksi:**
   - Buka `config.php` dan sesuaikan parameter host, user, password, serta nama database.

4. **Akses Aplikasi:**
   - Buka browser dan arahkan ke: `http://localhost/puskesmas/`

---

## Struktur Direktori

- `index.html`: Landing page statis untuk hosting frontend.
- `config.php`: Konfigurasi koneksi database PDO dan konstanta URL dasar (`BASE_URL`).
- `auth/`: Skrip login, logout, dan verifikasi sesi pengguna.
- `master/`: Modul pengelolaan data master obat, dokter, unit, dan poliklinik.
- `stok/`: Modul pencatatan penerimaan, mutasi transfer stok, dan stok opname.
- `request/`: Modul pengajuan dan verifikasi persetujuan permintaan obat antar-unit.
- `resep/`: Modul pencatatan peresepan pasien dan pengurangan stok pelayanan.
- `laporan/`: Skrip agregasi data LPLPO dan generator cetak PDF/Excel.
- `templates/`: Komponen layout bersama (sidebar, header topbar, footer).

---

## Deployment

Proyek ini menggunakan dua target deployment:

### 1. Vercel (Halaman Web Statis)
- Import repositori GitHub ke dashboard Vercel.
- Vercel akan menyajikan `index.html` dan aset terkait.

### 2. Server PHP / cPanel / Apache (Aplikasi Utama)
- Salin seluruh berkas proyek ke direktori web server (misal: `public_html` atau `htdocs`).
- Buat database MySQL pada server target dan import `dbpuskesmas.sql`.
- Sesuaikan konfigurasi database pada `config.php`.

---

## Pengembang

- **Septian Putra Rachman Hakim (iannnub)**
- Universitas Muhammadiyah Jember
- GitHub: [https://github.com/iannnub](https://github.com/iannnub)
