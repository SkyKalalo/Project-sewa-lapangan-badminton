# Hosting Demo Publik

Project ini dapat dipublikasikan sebagai demo gratis memakai Render Blueprint.
Blueprint membuat satu web service Docker dan satu database PostgreSQL gratis.

## Yang dibutuhkan

- Repository project di GitHub.
- Akun Render yang dapat mengakses repository tersebut.

## Deploy

1. Push perubahan project ke branch yang ingin dipakai.
2. Di Render, pilih **New > Blueprint** dan hubungkan repository ini.
3. Pilih branch tersebut. Render membaca `render.yaml` di root repository.
4. Pastikan web service dan database berada di region Singapore, lalu buat Blueprint.
5. Tunggu build dan deploy selesai. Buka URL `onrender.com` pada halaman service.
6. Di halaman Environment service, lihat nilai `SEED_ADMIN_PASSWORD` dan `SEED_OWNER_PASSWORD` untuk akun demo internal. Username-nya `admin` dan `pemilik`.

Saat container mulai, aplikasi menjalankan migrasi, seed akun demo, membuat symlink storage, dan memulai scheduler pembatalan booking kedaluwarsa.

## Batasan demo gratis

- Web service gratis tidur setelah 15 menit tanpa trafik dan dapat butuh sekitar satu menit untuk bangun kembali.
- Database gratis Render kedaluwarsa setelah 30 hari; ekspor data sebelum tenggat bila masih diperlukan.
- Filesystem web service tidak persisten. Bukti transfer unggahan dapat hilang ketika service restart, sleep, atau deploy ulang.
- Banner pada situs meminta pengunjung memakai data fiktif. Jangan gunakan untuk booking atau pembayaran nyata.
- Render dapat memulai ulang service kapan saja. Gunakan paket dan penyimpanan persisten sebelum menjadikan aplikasi layanan operasional.
