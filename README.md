# JejakKarier

Aplikasi pencatat riwayat lamaran kerja berbasis PHP dengan dukungan Supabase
PostgreSQL dan MySQL, dilengkapi landing page publik dan dashboard pribadi.

## Menjalankan

1. Aktifkan **Apache** pada XAMPP.
2. Buka `http://localhost/web%20lamar%20kerja/`.
3. Salin `.env.example` menjadi `.env`.
4. Pilih `DB_DRIVER=pgsql` untuk Supabase atau `DB_DRIVER=mysql` untuk MySQL.
5. Isi koneksi database dan, bila diperlukan, akun awal di `.env`.

## Supabase

Gunakan **Session Pooler** port `5432` dari halaman **Connect** di Supabase.
Jangan memakai publishable key sebagai pengganti koneksi database backend.

Untuk memindahkan data MySQL lokal ke Supabase:

```powershell
C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe `
  -d extension=php_pdo_pgsql.dll scripts\migrate_to_supabase.php
```

Skema PostgreSQL juga tersedia di `supabase_schema.sql`. Skrip migrasi menjaga
ID, password hash, kepemilikan data, tanggal, dan riwayat status.

Supabase menggunakan autentikasi SCRAM yang memerlukan PostgreSQL client modern.
Pada instalasi komputer ini, proyek JejakKarier dijalankan dengan Laragon PHP
8.1 melalui konfigurasi `apache/jejak-karier-php81.conf`; proyek XAMPP lain
tetap memakai runtime bawaannya. Peluncur CGI lokal dikompilasi dari
`apache/php81-cgi-launcher.cs` ke `.runtime/php81-cgi-launcher.exe`.

```powershell
New-Item -ItemType Directory -Path .runtime -Force
C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe `
  /nologo /target:exe `
  /out:.runtime\php81-cgi-launcher.exe apache\php81-cgi-launcher.cs
```

Aktifkan `extension=pdo_pgsql` di:

```text
C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.ini
```

Kemudian sertakan konfigurasi proyek dari `C:\xampp\apache\conf\httpd.conf`:

```apache
IncludeOptional "C:/xampp/htdocs/web lamar kerja/apache/jejak-karier-php81.conf"
```

Validasi konfigurasi dengan `httpd.exe -t`, lalu restart Apache.

## Deploy ke Vercel

Konfigurasi Vercel tersedia melalui:

- `vercel.json` untuk PHP Community Runtime, routing, region, dan security header
- `api/index.php` sebagai router seluruh halaman aplikasi
- `includes/session.php` untuk session login berbasis database
- `.vercelignore` untuk mencegah file lokal dan pengembangan ikut diunggah

Panduan impor GitHub, Environment Variables, dan verifikasi deployment tersedia
di `DEPLOY_VERCEL.md`.

Rute utama:

- `/` — landing page publik
- `/register.php` — pendaftaran pengguna baru
- `/login.php` — login
- `/dashboard.php` — dashboard yang dilindungi sesi

Jangan commit file `.env`, dump database, atau backup. Semuanya sudah dilindungi
oleh `.gitignore` dan aturan Apache di `.htaccess`. `.env.example` hanya berisi
placeholder dan aman dijadikan dokumentasi konfigurasi. File `database.sql`
tersedia untuk impor manual MySQL lewat phpMyAdmin.

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
