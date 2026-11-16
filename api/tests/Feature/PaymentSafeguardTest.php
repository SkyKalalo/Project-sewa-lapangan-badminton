<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentSafeguardTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_upload_more_than_one_pending_dp_proof_for_a_booking(): void
    {
        Storage::fake('public');
        $customerId = DB::table('pelanggan')->insertGetId(['nama' => 'Budi', 'no_hp' => '08123456789', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('booking')->insert(['kode_booking' => 'BK-TEST-001', 'tanggal_booking' => now(), 'waktu_dibuat' => now(), 'status_booking' => 'Menunggu DP', 'total_biaya' => 120000, 'id_pelanggan' => $customerId, 'batas_waktu_dp' => now()->addHour()]);

        $this->post('/api/booking/BK-TEST-001/bukti-dp', ['no_hp' => '08123456789', 'bukti' => UploadedFile::fake()->create('dp.png', 100, 'image/png')])->assertCreated();

        $this->post('/api/booking/BK-TEST-001/bukti-dp', ['no_hp' => '08123456789', 'bukti' => UploadedFile::fake()->create('dp-kedua.png', 100, 'image/png')])
            ->assertUnprocessable();

        $this->assertDatabaseCount('pembayaran', 1);
    }
}
