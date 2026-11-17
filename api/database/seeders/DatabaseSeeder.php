<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('pengguna')->upsert([
            ['nama'=>'Admin GOR','username'=>'admin','email'=>'admin@gorsmash.local','password_hash'=>password_hash(env('SEED_ADMIN_PASSWORD','GantiPassword123!'), PASSWORD_BCRYPT),'role'=>'Admin','created_at'=>now(),'updated_at'=>now()],
            ['nama'=>'Pemilik GOR','username'=>'pemilik','email'=>'pemilik@gorsmash.local','password_hash'=>password_hash(env('SEED_OWNER_PASSWORD','GantiPassword123!'), PASSWORD_BCRYPT),'role'=>'Pemilik','created_at'=>now(),'updated_at'=>now()],
        ], ['username'], ['nama','email','password_hash','role','updated_at']);
        foreach (['Lapangan 1','Lapangan 2','Lapangan 3','Lapangan 4'] as $name) DB::table('lapangan')->upsert(['nomor_lapangan'=>$name,'status'=>'tersedia'], ['nomor_lapangan'], ['status']);
        DB::table('tarif')->upsert([
            ['jenis_periode'=>'Normal','tarif_per_jam'=>60000,'hari_berlaku'=>'Senin-Jumat','jam_mulai'=>'06:00','jam_selesai'=>'17:00'],
            ['jenis_periode'=>'Malam','tarif_per_jam'=>90000,'hari_berlaku'=>'Senin-Jumat','jam_mulai'=>'17:00','jam_selesai'=>'22:00'],
            ['jenis_periode'=>'Akhir Pekan','tarif_per_jam'=>90000,'hari_berlaku'=>'Sabtu-Minggu','jam_mulai'=>'06:00','jam_selesai'=>'22:00'],
        ], ['jenis_periode'], ['tarif_per_jam','hari_berlaku','jam_mulai','jam_selesai']);
        foreach ([
            ['jenis' => 'Transfer Bank', 'nama' => 'BCA', 'nomor' => '1234567890', 'atas_nama' => 'GOR Smash Arena', 'qr_url' => null],
            ['jenis' => 'E-Wallet', 'nama' => 'DANA', 'nomor' => '081200000000', 'atas_nama' => 'GOR Smash Arena', 'qr_url' => null],
        ] as $method) {
            DB::table('metode_pembayaran')->updateOrInsert(['jenis' => $method['jenis'], 'nama' => $method['nama']], $method + ['created_at' => now(), 'updated_at' => now()]);
        }
    }
}
