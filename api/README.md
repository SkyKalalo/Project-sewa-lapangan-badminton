# GOR Smash API

Backend REST API Laravel untuk Sistem Penyewaan Lapangan Badminton GOR Smash.

Antarmuka web tersedia di halaman utama aplikasi (`/`) dan menggunakan endpoint API yang sama secara langsung. Jalankan `php artisan serve`, lalu buka `http://127.0.0.1:8000`.

## Menjalankan secara lokal

1. Instal PHP 8.3+, Composer, dan PostgreSQL 14+.
2. Salin `.env.example` menjadi `.env`, kemudian isi `DB_CONNECTION=pgsql`, nama database, pengguna, dan sandi PostgreSQL.
3. Jalankan `composer install`, `php artisan key:generate`, dan `php artisan migrate`.
4. Jalankan API dengan `php artisan serve`.
5. Jalankan pembatalan DP otomatis dengan `php artisan schedule:work`.
6. Setelah pembaruan fitur, jalankan `php artisan migrate` untuk membuat tabel permohonan booking.

## Endpoint utama

- `GET /api/jadwal?tanggal=YYYY-MM-DD`
- `POST /api/booking`
- `GET /api/booking/{kode}?no_hp=...`
- `POST /api/booking/{kode}/bukti-dp`
- `POST /api/booking/{kode}/permohonan` untuk pengajuan batal/pindah jadwal pelanggan.
- `POST /api/login`
- `GET /api/admin/booking` dan `POST /api/admin/booking` untuk operasional admin.
- `GET /api/admin/permohonan` dan `PATCH /api/admin/permohonan/{id}/proses` untuk memproses pengajuan pelanggan.
- Endpoint `/api/admin/*` memerlukan token Admin.
- Endpoint `/api/pemilik/*` memerlukan token Pemilik.

Gunakan header `Authorization: Bearer <token>` untuk endpoint internal.

## Data awal dan produksi

Jalankan `php artisan db:seed` untuk membuat tiga lapangan, tarif normal/malam/akhir pekan, serta akun `admin` dan `pemilik`. Ganti seluruh nilai sandi `SEED_*` sebelum menjalankannya.

Untuk server, salin `.env.production.example` menjadi `.env`, ganti seluruh nilai `GANTI_*`, lalu jalankan `docker compose up -d --build`, `docker compose exec app php artisan migrate --force`, dan `docker compose exec app php artisan db:seed --force`. Jalankan `php artisan schedule:work` sebagai service terpisah atau cron setiap menit. Cadangkan basis data setiap hari dengan `scripts/backup-postgres.sh` dan simpan hasilnya di lokasi terpisah.
