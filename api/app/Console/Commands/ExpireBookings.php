<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';
    protected $description = 'Batalkan booking yang melewati batas DP';

    public function handle(): int
    {
        $bookings = DB::table('booking')->where('status_booking', 'Menunggu DP')->where('batas_waktu_dp', '<', now())->get();
        foreach ($bookings as $booking) {
            DB::transaction(function () use ($booking) {
                DB::table('booking')->where('id_booking', $booking->id_booking)->update(['status_booking' => 'Dibatalkan']);
                DB::table('log_aktivitas')->insert(['id_booking' => $booking->id_booking, 'aktivitas' => 'Batalkan Booking Kedaluwarsa', 'detail_aktivitas' => 'Pembatalan otomatis oleh sistem', 'waktu_aktivitas' => now()]);
            });
        }
        $this->info("{$bookings->count()} booking kedaluwarsa dibatalkan.");
        return self::SUCCESS;
    }
}
