# Rancangan Backend Sistem Penyewaan Lapangan Badminton GOR Smash

## Tujuan

Menyediakan REST API untuk pelanggan, admin, dan pemilik GOR Smash. API menangani jadwal lapangan, booking, pembayaran DP dan pelunasan, pembatalan atau pindah jadwal, pelaporan, serta audit aktivitas. Sistem mengikuti SRS Tahap 2, ERD, dan spesifikasi arsitektur yang disediakan.

## Sasaran

- Mencegah booking aktif yang bertabrakan pada lapangan, tanggal, dan waktu yang sama.
- Menghitung tarif per jam sesuai hari dan rentang waktu, dengan DP sebesar 50%.
- Mengelola siklus booking: `Menunggu DP`, `DP Terverifikasi`, `Lunas`, dan `Dibatalkan`.
- Menyediakan akses berbasis peran untuk Admin dan Pemilik; pelanggan menggunakan kode booking dan nomor HP untuk tindakan atas booking miliknya.
- Menyimpan transaksi dan perubahan penting ke audit log.
- Membatalkan booking yang belum memiliki DP terverifikasi setelah dua jam melalui worker terjadwal.

## Bukan Sasaran Tahap Ini

- Integrasi payment gateway atau rekening bank nyata.
- Konfigurasi Cloudflare, Nginx, Redis, maupun penyimpanan S3/R2 produksi.
- Antarmuka web/PWA.

## Pendekatan yang Dipertimbangkan

1. Laravel + PostgreSQL (dipilih): menyediakan migrasi, validasi, autentikasi, otorisasi, file upload, queue/scheduler, dan testing yang terintegrasi.
2. Node.js + Express + PostgreSQL: lebih bebas, tetapi membutuhkan penyusunan autentikasi, validasi, scheduler, dan struktur transaksi lebih banyak dari awal.
3. Go + PostgreSQL: kinerja tinggi, tetapi membutuhkan waktu pengembangan lebih besar untuk kebutuhan CRUD dan administrasi ini.

Laravel dipilih karena kebutuhan utama bersifat transaksi dan administrasi, sehingga fitur bawaan framework mempercepat implementasi yang tetap aman dan teruji.

## Teknologi dan Struktur

- Laravel sebagai REST API dengan PHP 8.3+.
- PostgreSQL 14+ sebagai basis data utama.
- Laravel Sanctum untuk token API Admin dan Pemilik.
- Laravel scheduler untuk worker pembatalan kedaluwarsa, dijalankan tiap menit.
- Penyimpanan lokal Laravel untuk bukti transfer pada pengembangan; desain respons menyimpan URL sehingga dapat dipindahkan ke object storage di produksi.
- Transaksi database dan penguncian baris saat membuat booking, memverifikasi DP, melunasi, membatalkan, atau memindah jadwal.

## Model Data

Migrasi akan menerapkan delapan tabel ERD: `pengguna`, `pelanggan`, `lapangan`, `tarif`, `booking`, `detail_booking`, `pembayaran`, dan `log_aktivitas`. Kolom, status, foreign key, constraint, serta indeks mengikuti spesifikasi arsitektur yang diberikan.

Tambahan penegakan di aplikasi:

- Hanya booking aktif (`Menunggu DP`, `DP Terverifikasi`, `Lunas`) yang mengunci slot.
- Benturan didefinisikan sebagai `mulai_baru < selesai_lama && selesai_baru > mulai_lama`.
- Tarif dipilih per jam berdasarkan hari dan rentang jam. Bila sebuah booking melintasi rentang tarif, total dihitung per jam.
- File DP menerima JPG/PNG maksimal 5 MB.

## API Utama

### Publik atau pelanggan

- `GET /api/jadwal?tanggal=...` — jadwal dan slot yang tersedia.
- `POST /api/booking/ketersediaan` — cek benturan dan estimasi biaya.
- `POST /api/booking` — membuat pelanggan (bila belum ada) dan booking mandiri.
- `GET /api/booking/{kode}?no_hp=...` — detail booking, DP, dan status.
- `POST /api/booking/{kode}/bukti-dp` — unggah bukti transfer DP.

### Admin

- `POST /api/login`, `POST /api/logout`.
- `POST /api/admin/booking` — booking dari WhatsApp/telepon.
- `GET /api/admin/pembayaran?status=menunggu` — daftar DP untuk diverifikasi.
- `PATCH /api/admin/pembayaran/{id}/verifikasi` — terima atau tolak DP; penerimaan mengubah booking ke `DP Terverifikasi`.
- `POST /api/admin/booking/{kode}/pelunasan` — mencatat tunai tepat sebesar sisa tagihan dan mengubah status ke `Lunas`.
- `POST /api/admin/booking/{kode}/batal` — membatalkan dan menentukan DP hangus jika kurang dari 24 jam.
- `POST /api/admin/booking/{kode}/pindah-jadwal` — memindahkan satu kali setelah validasi waktu dan ketersediaan.
- `GET /api/admin/pelanggan/{no_hp}/riwayat` — riwayat dan ringkasan pelanggan.

### Pemilik

- `GET /api/pemilik/laporan?mulai=...&selesai=...` — pendapatan dan daftar/ringkasan booking.
- `GET /api/pemilik/laporan/jam-sepi?mulai=...&selesai=...` — slot kosong per lapangan dan jam.
- `GET /api/pemilik/monitoring-booking` — status DP dan booking tertunda.
- `GET /api/pemilik/aktivitas-admin` — audit aktivitas.

## Otorisasi

- Middleware memvalidasi token pada seluruh endpoint internal.
- Admin hanya mengakses operasi booking, pembayaran, dan riwayat pelanggan.
- Pemilik hanya mengakses laporan, monitoring, dan audit log.
- Aksi pelanggan diverifikasi menggunakan kombinasi kode booking dan nomor HP agar data pelanggan lain tidak terbuka.

## Alur Kritis

### Pembuatan booking

API memvalidasi input, memperoleh/menambah pelanggan, mengunci data lapangan terkait, memeriksa benturan, menentukan tarif dan total biaya, lalu menyimpan header booking dan detail slot dalam satu transaksi. Booking dibuat dengan status `Menunggu DP` dan batas waktu dua jam.

### Pembayaran

Unggah bukti membuat pembayaran DP berstatus `Menunggu Verifikasi` senilai 50% dari total. Verifikasi oleh Admin mengubahnya menjadi `Terverifikasi` dan mengubah status booking menjadi `DP Terverifikasi`. Pelunasan tunai hanya dapat dicatat untuk booking tersebut dan nominalnya harus sama dengan sisa tagihan.

### Pembatalan, pindah jadwal, dan kedaluwarsa

Pembatalan kurang dari 24 jam menandai `dp_hangus=true`; selain itu bernilai false. Pindah jadwal hanya untuk booking aktif, lebih dari 24 jam sebelum bermain, dan maksimal satu kali. Worker setiap menit membatalkan booking `Menunggu DP` yang melewati batas dan membuat log sistem.

## Pengujian

Pengujian fitur akan dibuat sebelum implementasi untuk minimal:

- cek benturan dan slot berdampingan yang tidak bentrok;
- perhitungan tarif normal, malam, akhir pekan, dan lintas tarif;
- DP 50%, kedaluwarsa dua jam, verifikasi DP, dan pelunasan;
- batas satu kali reschedule serta DP hangus;
- RBAC Admin/Pemilik dan perlindungan data booking pelanggan;
- pencatatan audit dan laporan dasar.

## Keputusan yang Diambil

- Ketika tepat 24 jam sebelum waktu bermain, pembatalan diperlakukan tidak hangus dan reschedule ditolak karena SRS menyatakan reschedule harus lebih dari 24 jam.
- Pendapatan laporan dihitung dari pembayaran terverifikasi, bukan dari total booking yang belum dibayar.
- Tarif lintas periode dihitung per jam, sesuai catatan SRS yang meminta perlakuan eksplisit untuk kondisi tersebut.

