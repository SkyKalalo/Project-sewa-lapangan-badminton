# Pemeriksaan Proyek Sistem Penyewaan Lapangan Badminton GOR Smash

Tanggal pemeriksaan: 13 November 2026

## Status verifikasi lokal

Pada 13 November 2026, proyek disalin ke folder lokal biasa di komputer pengguna agar tidak terpengaruh pembatasan area kerja terisolasi. Hasil verifikasi:

- `php artisan migrate:fresh --seed --env=testing`: berhasil; seluruh migrasi dan seed data berjalan.
- `vendor/bin/phpunit`: lulus, 11 tes dan 38 assertion.
- `php artisan route:list --path=api`: 16 route API terdaftar.
- Pemeriksaan sintaks PHP pada controller, middleware, service, dan command: tidak ada error.

Tes yang sekarang tersedia mencakup skema basis data, tarif lintas jam, DP 50%, benturan dan slot bersebelahan, unggah DP ganda, verifikasi DP, pelunasan, RBAC Admin/Pemilik, pembatalan kedaluwarsa, pindah jadwal multi-slot, dan pengabaian booking dibatalkan pada jadwal. Ini belum menggantikan pengujian bisnis menyeluruh; test pembatalan tepat 24 jam, laporan, dan beban serentak masih perlu ditambahkan sebelum produksi.

### Bug yang ditemukan oleh tes dan sudah diperbaiki

1. Pelanggan dapat mengunggah bukti DP dua kali untuk satu booking. Sistem kini menolak unggahan kedua bila DP masih menunggu verifikasi atau sudah terverifikasi.
2. Verifikasi DP mencari primary key generik `id`, sedangkan ERD memakai `id_pembayaran`. Sistem kini memakai kolom yang benar.
3. Pindah jadwal hanya memindahkan detail booking pertama. Sistem kini memindahkan seluruh detail jam pada booking sekaligus dan mencatat audit log.

## 1. Dokumen sumber

Dokumen kebutuhan dan desain yang digunakan sebagai referensi berada di luar folder kode pada lokasi unduhan pengguna:

- `SRS_Tahap_2_GOR_Smash (1).docx`: 16 kebutuhan fungsional, aturan tarif, DP, pembatalan, dan peran.
- `Sistem_Penyewaan_Lapangan_Badminton_GOR_Smash_revisi_final.json`: use case dan skenario.
- `spesifikasi_arsitektur_gor_smash.json`: arsitektur multi-tier dan ERD PostgreSQL.

Rancangan yang dipakai saat pembangunan ada di `docs/superpowers/specs/2026-11-13-gor-smash-backend-design.md`, sedangkan rencana pelaksanaannya ada di `docs/superpowers/plans/2026-11-13-gor-smash-backend.md`.

## 2. Isi proyek

Folder `api` adalah Laravel 13 REST API. Bagian utama:

- `database/migrations`: skema basis data.
- `app/Services/BookingService.php`: pembuatan booking, tarif per jam, dan pencegahan benturan.
- `app/Http/Controllers/Api`: endpoint pelanggan, Admin, Pemilik, pembayaran, jadwal, dan riwayat.
- `app/Console/Commands/ExpireBookings.php`: pembatalan booking lewat batas DP.
- `routes/api.php`: seluruh route API.
- `database/seeders/DatabaseSeeder.php`: data lapangan, tarif, serta akun awal.
- `docker-compose.yml`, `Dockerfile`, dan `docker/nginx.conf`: kerangka deployment.

## 3. Cakupan SRS

| Kebutuhan | Status | Catatan |
|---|---|---|
| Jadwal lapangan | Ada | Menampilkan slot booking aktif per tanggal. |
| Booking mandiri/admin | Sebagian | Mandiri tersedia; route booking khusus Admin belum dipisahkan. |
| Cegah benturan | Ada | Dilakukan dalam transaksi database untuk booking dan pindah jadwal. |
| Tarif, total, DP 50% | Ada | Tarif dihitung tiap jam; DP dikembalikan saat booking dibuat. |
| Upload/verifikasi DP | Ada | JPG/PNG maksimal 5 MB; verifikasi Admin tersedia. |
| Kedaluwarsa dua jam | Ada | Command dan jadwal tiap menit tersedia. |
| Batal/pindah jadwal | Ada | DP hangus kurang dari 24 jam; pindah satu kali. |
| Pelunasan tunai | Ada | Hanya menerima nominal sisa tagihan yang tepat. |
| Riwayat pelanggan | Ada | Terbatas untuk Admin. |
| Laporan/monitoring/audit | Sebagian | Pendapatan, status, dan log tersedia; jam sepi baru hitung booking per lapangan, belum benar-benar menghitung slot kosong per jam. |

## 4. Kelebihan

- Entitas utama dan relasi basis data mengikuti ERD.
- Aturan bisnis paling penting—benturan jadwal, DP 50%, batas dua jam, pelunasan, DP hangus, serta reschedule satu kali—sudah memiliki implementasi awal.
- Pembayaran dan perubahan booking dicatat ke `log_aktivitas`.
- Token internal tidak disimpan dalam bentuk teks; yang tersimpan adalah hash SHA-256 token.
- Konfigurasi produksi mempertimbangkan PostgreSQL, Redis, object storage, reverse proxy, dan backup.

## 5. Kekurangan dan risiko sebelum produksi

### Kritis

1. Cakupan pengujian otomatis masih sangat rendah. Tes lokal sudah berjalan dan lulus, tetapi baru 3 tes/10 assertion; test alur booking, pembayaran, reschedule, RBAC, dan laporan belum tersedia.
2. Berkas Docker belum siap dijalankan apa adanya: container Nginx tidak berbagi kode aplikasi dari container `app`, sehingga `public/index.php` tidak tersedia di Nginx. Perlu volume kode bersama atau image Nginx yang menyertakan `public` sebelum deployment.
3. Docker Compose belum memiliki service scheduler khusus. `php artisan schedule:work` harus dijalankan oleh service terpisah, Supervisor, atau cron.
4. Tidak ada konfigurasi TLS/sertifikat Let’s Encrypt yang lengkap. Nginx saat ini hanya HTTP.

### Penting

1. Constraint database untuk enum status, nominal positif, rentang waktu valid, serta batas pindah jadwal belum ditegakkan penuh oleh PostgreSQL; beberapa hanya ada di kode aplikasi.
2. Kode booking memakai hitungan jumlah booking. Pada permintaan serentak, kode bisa duplikat; gunakan sequence PostgreSQL atau UUID/ULID.
3. Endpoint jadwal hanya mengembalikan slot terisi, bukan daftar semua slot kosong seperti yang diminta SRS.
4. Analisis jam sepi belum membuat matriks jam × lapangan dan belum menghitung slot kosong sebenarnya.
5. Endpoint pembayaran dapat menerima unggahan DP lebih dari sekali; perlu pembatasan satu pembayaran DP aktif per booking.
6. Token internal memiliki masa hidup satu hari, tetapi belum ada logout/revoke dan rotasi token.
7. Tidak ada notifikasi WhatsApp/email, payment gateway, atau proses pengembalian DP—fitur ini memang belum diwajibkan secara detail oleh SRS.

### Operasional

1. Kredensial contoh wajib diganti sebelum seed dan tidak boleh disimpan dalam Git.
2. Bucket MinIO/S3 harus dibuat dan diuji sebelum bukti transfer dipindahkan dari penyimpanan lokal.
3. Backup perlu diuji dengan proses restore, bukan hanya dibuat.
4. Logging, monitoring uptime, alert kegagalan scheduler, dan pengelolaan rahasia belum tersedia.

## 6. Checklist penerimaan sebelum produksi

- [ ] Jalankan `composer install`, `php artisan migrate:fresh --seed`, dan seluruh test pada komputer/server lokal.
- [ ] Uji booking berbarengan untuk slot yang sama.
- [ ] Uji tarif normal, malam, akhir pekan, dan booking melintasi pukul 17.00.
- [ ] Uji upload file salah/lebih dari 5 MB, DP ditolak, DP terlambat, pelunasan kurang/lebih, batal tepat 24 jam, dan reschedule kedua.
- [ ] Perbaiki image/volume Nginx, tambahkan scheduler service, dan aktifkan HTTPS.
- [ ] Gunakan PostgreSQL produksi serta migrasi fresh pada staging.
- [ ] Ganti seluruh sandi dan rahasia contoh.
- [ ] Uji backup dan restore PostgreSQL.
- [ ] Lakukan uji keamanan endpoint dan pembatasan akses Admin/Pemilik.

## 7. Urutan rekomendasi

1. Pindahkan `api` ke folder lokal biasa lalu jalankan test dan perbaiki seluruh kegagalan.
2. Lengkapi constraint PostgreSQL, kode booking yang aman terhadap konkurensi, jadwal slot kosong, dan analisis jam sepi.
3. Perbaiki deployment Docker, scheduler, TLS, dan object storage.
4. Deploy lebih dahulu ke staging; lakukan uji penerimaan bersama pemilik GOR.
5. Baru deploy ke produksi setelah checklist dinyatakan selesai.
