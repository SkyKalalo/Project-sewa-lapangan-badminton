<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('lapangan')->insert(['nomor_lapangan' => 'Lapangan 1', 'status' => 'tersedia']);
        DB::table('tarif')->insert([
            ['jenis_periode' => 'Normal', 'tarif_per_jam' => 60000, 'hari_berlaku' => 'Senin-Jumat', 'jam_mulai' => '06:00:00', 'jam_selesai' => '17:00:00'],
            ['jenis_periode' => 'Malam', 'tarif_per_jam' => 90000, 'hari_berlaku' => 'Senin-Jumat', 'jam_mulai' => '17:00:00', 'jam_selesai' => '22:00:00'],
        ]);
    }

    public function test_booking_across_normal_and_evening_rates_returns_correct_total_and_dp(): void
    {
        $response = $this->postJson('/api/booking', ['nama_pelanggan' => 'Ani', 'no_hp' => '08111111111', 'id_lapangan' => 1, 'tanggal_main' => '2026-11-16', 'jam_mulai' => '16:00', 'durasi' => 2]);

        $response->assertCreated()->assertJsonPath('total_biaya', 150000)->assertJsonPath('nominal_dp', 75000);
        $this->assertDatabaseCount('detail_booking', 2);
    }

    public function test_active_booking_blocks_overlapping_slot_but_allows_adjacent_slot(): void
    {
        $first = ['nama_pelanggan' => 'Ani', 'no_hp' => '08111111111', 'id_lapangan' => 1, 'tanggal_main' => '2026-11-16', 'jam_mulai' => '10:00', 'durasi' => 1];
        $this->postJson('/api/booking', $first)->assertCreated();
        $this->postJson('/api/booking', array_merge($first, ['nama_pelanggan' => 'Budi', 'no_hp' => '08222222222', 'jam_mulai' => '10:30']))->assertUnprocessable();
        $this->postJson('/api/booking', array_merge($first, ['nama_pelanggan' => 'Cici', 'no_hp' => '08333333333', 'jam_mulai' => '11:00']))->assertCreated();
    }
}
