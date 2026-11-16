<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_omits_cancelled_booking_slots(): void
    {
        $customer = DB::table('pelanggan')->insertGetId(['nama' => 'Joko', 'no_hp' => '08666666666', 'created_at' => now(), 'updated_at' => now()]);
        $court = DB::table('lapangan')->insertGetId(['nomor_lapangan' => 'Lapangan 1', 'status' => 'tersedia']);
        $rate = DB::table('tarif')->insertGetId(['jenis_periode' => 'Normal', 'tarif_per_jam' => 60000, 'hari_berlaku' => 'Setiap Hari', 'jam_mulai' => '06:00:00', 'jam_selesai' => '22:00:00']);
        $booking = DB::table('booking')->insertGetId(['kode_booking' => 'BK-CANCEL-001', 'tanggal_booking' => now(), 'waktu_dibuat' => now(), 'status_booking' => 'Dibatalkan', 'total_biaya' => 60000, 'id_pelanggan' => $customer, 'batas_waktu_dp' => now()->addHour()]);
        DB::table('detail_booking')->insert(['id_booking' => $booking, 'id_lapangan' => $court, 'id_tarif' => $rate, 'tanggal_main' => '2026-11-20', 'jam_mulai' => '10:00:00', 'jam_selesai' => '11:00:00', 'durasi' => 1, 'tarif_saat_booking' => 60000]);

        $this->getJson('/api/jadwal?tanggal=2026-11-20')->assertOk()->assertJsonMissing(['jam_mulai' => '10:00:00']);
    }
}
