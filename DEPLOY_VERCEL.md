# Deploy JejakKarier ke Vercel

## 1. Amankan kredensial

Password database yang pernah dibagikan harus dirotasi dari dashboard Supabase
sebelum aplikasi dibuka untuk publik. Simpan connection string baru hanya di
Environment Variables Vercel dan `.env` lokal.

## 2. Impor repository

1. Buka Vercel lalu pilih **Add New > Project**.
2. Impor repository GitHub `DIFZX/Jejak_Karir-web`.
3. Gunakan branch `main`.
4. Pilih **Framework Preset: Other**.
5. Biarkan **Root Directory** pada root repository.
6. Jangan isi Build Command atau Output Directory.

`vercel.json` akan menjalankan `api/index.php` menggunakan PHP Community
Runtime dan menempatkan Function di region Seoul (`icn1`), dekat dengan
database Supabase.

## 3. Environment Variables

Tambahkan variabel berikut untuk lingkungan **Production**:

```text
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
DB_DRIVER=pgsql
DB_AUTO_MIGRATE=false
SESSION_DRIVER=database
SUPABASE_DB_URL=<Session Pooler connection string yang baru>
```

Gunakan Session Pooler port `5432`, bukan direct connection IPv6. Jangan
menambahkan `INITIAL_USERNAME` atau `INITIAL_PASSWORD` karena akun sudah ada di
Supabase.

Untuk Preview Deployment, gunakan project/database Supabase terpisah agar data
produksi tidak berubah saat menguji branch.

## 4. Deploy dan verifikasi

Setelah Environment Variables disimpan, pilih **Deploy** atau **Redeploy**.
Periksa:

1. Landing page dapat dibuka.
2. Login akun lama berhasil.
3. Dashboard menampilkan lima data hasil migrasi.
4. Pencarian dan filter tanggal bekerja.
5. Tambah, edit, lalu hapus satu data uji.
6. Ekspor PDF dan CSV berhasil.
7. Logout kembali ke landing page.

Jika Environment Variables ditambahkan setelah deployment pertama, lakukan
Redeploy agar nilai tersebut tersedia pada Function.
