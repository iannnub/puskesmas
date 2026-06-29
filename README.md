# 🏥 SIVO Puskesmas | Sistem Informasi Inventori Obat Terpadu

> **Deskripsi Pendek:**
> SIVO Puskesmas adalah Sistem Informasi Inventori Obat Terpadu untuk mengelola stok, riwayat penggunaan, & permintaan obat antar unit. Repositori ini mencakup backend PHP (InfinityFree) & frontend SEO (Vercel) dengan integrasi Open Graph meta tags.

Welcome to the repository of **SIVO Puskesmas**! Proyek ini adalah aplikasi web berkinerja tinggi dan komprehensif yang dibangun untuk membantu fasilitas kesehatan (Puskesmas Wuluhan) dalam mengelola inventaris obat, memantau riwayat keluar-masuk, dan memproses permintaan antar unit/gudang secara terpusat dan *real-time*.

🌐 **Live Demo (SEO Frontend):** [https://sivo-wuluhan.vercel.app](https://sivo-wuluhan.vercel.app) *(Sesuaikan dengan URL Vercel Anda)*  
🌐 **Live App (Backend):** [https://sivo-wuluhan.page.gd/](https://sivo-wuluhan.page.gd/)

---

## ✨ Features
- 🛡️ **Keamanan Kelas Menengah:** Dibangun dengan *PDO Prepared Statements* untuk mencegah injeksi SQL, dan sistem *Role-Based Access Control (RBAC)* untuk memisahkan wewenang Admin dan Petugas Unit.
- 📦 **Manajemen Stok Real-Time:** Kelola penerimaan, pengeluaran, mutasi/transfer, dan *Stock Opname* dengan catatan riwayat yang terekam permanen.
- 📄 **Sistem Pelaporan Otomatis:** Ekspor Laporan Bulanan, Harian, Kartu Stok, dan Sasaran Mutu dengan format PDF maupun Excel.
- 🎨 **Premium UI/UX (SB Admin 2):** *Dashboard* administratif yang responsif, bersih, dan profesional menggunakan Bootstrap 4.
- 🔍 **SEO & Social Media Ready (Vercel):** Memiliki `index.html` dan `og-image.png` statis di *root* repositori yang memberikan tampilan *Glassmorphism* modern dan konfigurasi **Open Graph & Twitter Cards** yang sempurna saat tautan dibagikan di WhatsApp, Facebook, IG, dll.

---

## 🛠️ Tech Stack
- **Backend Language:** PHP Native 
- **Database:** MySQL (MariaDB)
- **Database Driver:** PHP Data Objects (PDO)
- **Frontend / Styling:** HTML5, CSS3, Bootstrap 4 (SB Admin 2 Template), jQuery
- **Reporting Engine:** TCPDF (PDF) & PhpSpreadsheet (Excel)
- **Deployment (Frontend/SEO):** Vercel
- **Deployment (App Server):** InfinityFree / cPanel

---

## 💻 Local Development
Ingin menjalankan proyek ini secara lokal di komputer Anda? Ikuti langkah-langkah berikut:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/iannnub/puskesmas.git
   cd puskesmas
   ```
2. **Siapkan Database:**
   - Buka XAMPP, nyalakan **Apache** dan **MySQL**.
   - Buka phpMyAdmin (`http://localhost/phpmyadmin`).
   - Buat database baru bernama `dbpuskesmas`.
   - Lakukan **Import** menggunakan file `dbpuskesmas.sql` yang tersedia di dalam repositori ini.
3. **Akses Aplikasi:**
   - Buka *browser* dan ketik: `http://localhost/puskesmas/`

*(Catatan: File `config.php` telah dirancang cerdas untuk mendeteksi secara otomatis apakah ia berjalan di `localhost` atau di _server_ produksi InfinityFree).*

---

## 📂 Project Structure
- `index.html` : **(Vercel Frontend)** *Landing page* statis dengan *Glassmorphism* & *Meta Tags* untuk optimasi *preview* tautan sosial media.
- `og-image.png` : Aset visual ilustrasi AI untuk *thumbnail* saat tautan *website* dibagikan.
- `config.php` : File konfigurasi *database* (Otomatis beralih antara konfigurasi Lokal dan InfinityFree).
- `auth/` : Modul sistem Autentikasi (Login, Logout, & Enkripsi *Password*).
- `master/` : Modul *Create, Read, Update, Delete* (CRUD) untuk Data Obat, Dokter, Unit, Poli, dan Pelayanan.
- `stok/` & `request/` : Modul inti yang menangani *logic* pengurangan stok, pemindahan antar gudang, dan persetujuan barang.
- `laporan/` : Kumpulan *script* untuk membangun visualisasi cetak PDF dan Excel.
- `templates/` : *Component* antarmuka UI seperti *Sidebar*, *Header*, dan pengecekan sesi.

---

## 🚀 Deployment

Proyek ini dirancang secara *hybrid* (*Monorepo*) untuk di-_deploy_ di dua layanan berbeda guna mendapatkan performa maksimal:

### 1. Vercel (Khusus Tampilan *Link Share*)
- Push repositori ini ke akun GitHub Anda.
- Pergi ke Vercel, *login* menggunakan GitHub.
- Tambahkan proyek baru, *import* repositori `puskesmas` ini. Vercel otomatis hanya akan membaca `index.html`.
- Bagikan *URL* Vercel tersebut ke Sosmed Anda untuk *preview* tautan yang elegan!

### 2. InfinityFree (Khusus *Backend Server*)
- *Zip/Compress* seluruh isi folder proyek ini.
- *Upload* lalu *Extract* seluruh file tersebut ke dalam folder `htdocs` di *File Manager* InfinityFree.
- Buat *Database* MySQL di InfinityFree, lalu *Import* file `dbpuskesmas.sql` via phpMyAdmin.
- Ubah kredensial di `config.php` sesuai dengan informasi dari cPanel InfinityFree.

---

## 👨‍💻 About Me
**Septian Putra Rachman Hakim (iannnub)**  
Saya adalah seorang *Software Engineer* dan spesialis IT yang berdedikasi membangun infrastruktur perangkat lunak berkualitas tinggi. Saya memiliki antusiasme mendalam terhadap pemecahan masalah sistematis (*Systematic Problem Solving*), pengembangan web berkinerja tinggi, serta integrasi solusi *Artificial Intelligence*.

🔗 **Connect with me:**
- [GitHub](https://github.com/iannnub)
- [LinkedIn](https://linkedin.com/) *(Tambahkan URL LinkedIn Anda)*
