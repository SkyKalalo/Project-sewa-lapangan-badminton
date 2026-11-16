<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function userToken(string $role): string
    {
        $id = DB::table('pengguna')->insertGetId(['nama' => $role, 'username' => strtolower($role), 'email' => strtolower($role).'@test.local', 'password_hash' => password_hash('rahasia', PASSWORD_BCRYPT), 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
        $plain = "token-{$role}";
        DB::table('api_tokens')->insert(['id_pengguna' => $id, 'token' => hash('sha256', $plain), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        return $plain;
    }

    private function pendingBooking(): array
    {
        $customerId = DB::table('pelanggan')->insertGetId(['nama' => 'Deni', 'no_hp' => '08999999999', 'created_at' => now(), 'updated_at' => now()]);
        $bookingId = DB::table('booking')->insertGetId(['kode_booking' => 'BK-PAY-001', 'tanggal_booking' => now(), 'waktu_dibuat' => now(), 'status_booking' => 'Menunggu DP', 'total_biaya' => 120000, 'id_pelanggan' => $customerId, 'batas_waktu_dp' => now()->addHour()]);
        $paymentId = DB::table('pembayaran')->insertGetId(['id_booking' => $bookingId, 'jenis_pembayaran' => 'DP', 'nominal' => 60000, 'status_verifikasi' => 'Menunggu Verifikasi', 'metode_pembayaran' => 'Transfer Bank', 'tanggal_pembayaran' => now()]);
        return [$bookingId, $paymentId];
    }

    public function test_admin_verifies_dp_then_accepts_only_exact_cash_settlement(): void
    {
        [, $paymentId] = $this->pendingBooking();
        $token = $this->userToken('Admin');

        $this->withToken($token)->patchJson("/api/admin/pembayaran/{$paymentId}/verifikasi", ['status' => 'Terverifikasi'])->assertOk();
        $this->assertDatabaseHas('booking', ['kode_booking' => 'BK-PAY-001', 'status_booking' => 'DP Terverifikasi']);
        $this->withToken($token)->postJson('/api/admin/booking/BK-PAY-001/pelunasan', ['nominal' => 50000])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/admin/booking/BK-PAY-001/pelunasan', ['nominal' => 60000])->assertOk()->assertJsonPath('status_booking', 'Lunas');
    }

    public function test_owner_cannot_use_admin_payment_endpoint(): void
    {
        $this->withToken($this->userToken('Pemilik'))->getJson('/api/admin/pembayaran')->assertForbidden();
    }

    public function test_expiry_command_cancels_unpaid_booking_and_writes_audit_log(): void
    {
        $customerId = DB::table('pelanggan')->insertGetId(['nama' => 'Eka', 'no_hp' => '08888888888', 'created_at' => now(), 'updated_at' => now()]);
        $bookingId = DB::table('booking')->insertGetId(['kode_booking' => 'BK-EXP-001', 'tanggal_booking' => now()->subHours(3), 'waktu_dibuat' => now()->subHours(3), 'status_booking' => 'Menunggu DP', 'total_biaya' => 60000, 'id_pelanggan' => $customerId, 'batas_waktu_dp' => now()->subHour()]);

        $this->artisan('bookings:expire')->assertExitCode(0);

        $this->assertDatabaseHas('booking', ['id_booking' => $bookingId, 'status_booking' => 'Dibatalkan']);
        $this->assertDatabaseHas('log_aktivitas', ['id_booking' => $bookingId, 'aktivitas' => 'Batalkan Booking Kedaluwarsa']);
    }
}
