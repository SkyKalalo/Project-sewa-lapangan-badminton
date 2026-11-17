<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOperationsController extends Controller
{
    public function toggleCourt(int $id)
    {
        $court = DB::table('lapangan')->where('id_lapangan', $id)->first();
        abort_unless($court, 404);
        $status = $court->status === 'tersedia' ? 'maintenance' : 'tersedia';
        DB::table('lapangan')->where('id_lapangan', $id)->update(['status' => $status]);
        return ['id_lapangan' => $id, 'status' => $status];
    }

    public function expireBookings()
    {
        $bookings = DB::table('booking')->where('status_booking', 'Menunggu DP')->where('batas_waktu_dp', '<', now())->get();
        foreach ($bookings as $booking) {
            DB::transaction(function () use ($booking) {
                DB::table('booking')->where('id_booking', $booking->id_booking)->update(['status_booking' => 'Dibatalkan']);
                DB::table('log_aktivitas')->insert(['id_booking' => $booking->id_booking, 'aktivitas' => 'Batalkan Booking Kedaluwarsa', 'detail_aktivitas' => 'Pembatalan otomatis oleh admin', 'waktu_aktivitas' => now()]);
            });
        }
        return ['dibatalkan' => $bookings->count()];
    }
}
