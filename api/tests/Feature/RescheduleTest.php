<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_rescheduling_a_multi_hour_booking_moves_every_hourly_detail_once(): void
    {
        $adminId = DB::table('pengguna')->insertGetId(['nama' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local', 'password_hash' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
        $token = 'admin-token';
        DB::table('api_tokens')->insert(['id_pengguna' => $adminId, 'token' => hash('sha256', $token), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $customerId = DB::table('pelanggan')->insertGetId(['nama' => 'Fani', 'no_hp' => '08777777777', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('lapangan')->insert([['nomor_lapangan' => 'Lapangan 1', 'status' => 'tersedia'], ['nomor_lapangan' => 'Lapangan 2', 'status' => 'tersedia']]);
        $rateId = DB::table('tarif')->insertGetId(['jenis_periode' => 'Normal', 'tarif_per_jam' => 60000, 'hari_berlaku' => 'Setiap Hari', 'jam_mulai' => '06:00:00', 'jam_selesai' => '22:00:00']);
        $bookingId = DB::table('booking')->insertGetId(['kode_booking' => 'BK-MOVE-001', 'tanggal_booking' => now(), 'waktu_dibuat' => now(), 'status_booking' => 'DP Terverifikasi', 'total_biaya' => 120000, 'id_pelanggan' => $customerId, 'batas_waktu_dp' => now()->addHour()]);
        foreach ([['08:00:00','09:00:00'], ['09:00:00','10:00:00']] as [$start,$end]) DB::table('detail_booking')->insert(['id_booking'=>$bookingId,'id_lapangan'=>1,'id_tarif'=>$rateId,'tanggal_main'=>'2026-11-20','jam_mulai'=>$start,'jam_selesai'=>$end,'durasi'=>1,'tarif_saat_booking'=>60000]);

        $this->withToken($token)->postJson('/api/admin/booking/BK-MOVE-001/pindah-jadwal', ['id_lapangan' => 2, 'tanggal_main' => '2026-11-25', 'jam_mulai' => '10:00'])->assertOk();

        $details = DB::table('detail_booking')->where('id_booking', $bookingId)->orderBy('jam_mulai')->get();
        $this->assertCount(2, $details);
        $this->assertSame('2026-11-25', $details[0]->tanggal_main);
        $this->assertSame('10:00:00', $details[0]->jam_mulai);
        $this->assertSame('11:00:00', $details[1]->jam_mulai);
        $this->assertSame(2, $details[0]->id_lapangan);
        $this->assertSame(2, $details[1]->id_lapangan);
    }
}
