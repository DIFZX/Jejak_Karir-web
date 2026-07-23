# JejakKarier

Aplikasi pencatat riwayat lamaran kerja berbasis PHP dan MySQL, dilengkapi
landing page publik dan dashboard pribadi.

## Menjalankan

1. Aktifkan **Apache** dan **MySQL** pada XAMPP.
2. Buka `http://localhost/web%20lamar%20kerja/`.
3. Salin `.env.example` menjadi `.env`.
4. Isi koneksi database dan, bila diperlukan, akun awal di `.env`.
5. Database serta tabel aplikasi dibuat otomatis saat aplikasi dijalankan.

Rute utama:

- `/` — landing page publik
- `/register.php` — pendaftaran pengguna baru
- `/login.php` — login
- `/dashboard.php` — dashboard yang dilindungi sesi

Jangan commit file `.env`. File tersebut sudah tercantum dalam `.gitignore`.
`.env.example` hanya berisi placeholder dan aman dijadikan dokumentasi konfigurasi.
File `database.sql` juga tersedia untuk impor manual lewat phpMyAdmin.

## Fitur

- Tambah, tampil, edit, dan hapus riwayat lamaran
- Tanggal dan waktu tercatat otomatis saat penyimpanan
- Pencarian dan filter status
- Ringkasan progres dan pagination
- Ekspor hasil (termasuk filter aktif) menjadi PDF
- Tampilan responsif untuk desktop dan ponsel
- Login akun dengan password yang tersimpan secara aman
- Pengingat follow-up, jadwal interview, dan deadline
- Tahapan rekrutmen beserta riwayat perubahan status
- Prioritas lamaran
- Dashboard grafik enam bulan dan kanal lamaran
- Ekspor PDF dan CSV
- Data terpisah untuk setiap akun
- Kelola username, password, dan tambah akun
- Mode gelap
- Landing page profesional dengan animasi, fitur, panduan, tentang, FAQ, dan kontak
- Pendaftaran akun mandiri
