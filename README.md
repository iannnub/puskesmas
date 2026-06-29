# SIVO Puskesmas - Sistem Informasi Inventori Obat Terpadu

> **Deskripsi Pendek untuk GitHub (Maksimal 350 karakter):**
> SIVO Puskesmas adalah Sistem Informasi Inventori Obat Terpadu untuk mengelola stok, riwayat penggunaan, & permintaan obat antar unit. Repositori ini mencakup backend PHP (InfinityFree) & frontend SEO (Vercel) dengan integrasi Open Graph meta tags.

## Struktur Repository
Proyek ini berisi kesatuan aplikasi SIVO Puskesmas, yang mencakup:
- **Aplikasi Utama (PHP):** Mengelola sistem inventori, *database* stok, pelaporan, dan *dashboard* interaktif. (Dikhususkan untuk di-hosting di layanan ber-PHP seperti InfinityFree).
- **SEO Frontend (Vercel):** Memanfaatkan file `index.html` dan `og-image.png` di direktori terluar (root) agar saat tautan repositori ini disebarkan di sosial media (WhatsApp, FB, IG, X), *preview* gambar dan judulnya muncul dengan elegan.

## Cara Konfigurasi Vercel (Khusus Tampilan Sosmed)
Karena seluruh file ini berada dalam satu folder:
1. Hubungkan repository GitHub ini ke proyek baru di **Vercel**.
2. Vercel akan otomatis hanya melirik dan membaca `index.html` (karena Vercel tidak bisa membaca `.php`).
3. Vercel akan menghasilkan *URL* yang aman untuk dibagikan di sosial media, yang nantinya akan mengarahkan (*redirect*) pengunjung ke server aplikasi utama InfinityFree.
