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

    /**
     * Remove only clearly labelled test/demo bookings. Master data, staff,
     * courts, tariffs, and payment methods are intentionally preserved.
     */
    public function resetDemo(Request $request)
    {
        abort_unless($request->input('confirmation') === 'RESET-DEMO', 422, 'Ketik RESET-DEMO untuk mengonfirmasi pembersihan data demo.');

        return DB::transaction(function () use ($request) {
            $demoCustomers = DB::table('pelanggan')
                ->where(function ($q) {
                    foreach (['PENGUJIAN', 'TEST', 'DEMO', 'DUMMY', 'UJI'] as $prefix) {
                        $q->orWhere('nama', 'like', $prefix . '%');
                    }
                })
                ->pluck('id_pelanggan');

            $demoBookings = DB::table('booking')
                ->whereIn('id_pelanggan', $demoCustomers)
                ->orWhere(function ($q) {
                    $q->where('kode_booking', 'like', '%DEMO%')
                        ->orWhere('kode_booking', 'like', '%TEST%')
                        ->orWhere('kode_booking', 'like', '%UJI%');
                })
                ->pluck('id_booking');

            $deleted = [
                'permohonan' => DB::table('permohonan_booking')->whereIn('id_booking', $demoBookings)->delete(),
                'pembayaran' => DB::table('pembayaran')->whereIn('id_booking', $demoBookings)->delete(),
                'detail_booking' => DB::table('detail_booking')->whereIn('id_booking', $demoBookings)->delete(),
                'log_aktivitas' => DB::table('log_aktivitas')->whereIn('id_booking', $demoBookings)->delete(),
                'booking' => DB::table('booking')->whereIn('id_booking', $demoBookings)->delete(),
                'pelanggan' => DB::table('pelanggan')->whereIn('id_pelanggan', $demoCustomers)->whereNotExists(function ($q) {
                    $q->selectRaw('1')->from('booking')->whereColumn('booking.id_pelanggan', 'pelanggan.id_pelanggan');
                })->delete(),
            ];

            DB::table('log_aktivitas')->insert([
                'id_pengguna' => $request->attributes->get('pengguna')->id_pengguna,
                'aktivitas' => 'Bersihkan data demo',
                'detail_aktivitas' => 'Data booking berlabel PENGUJIAN/TEST/DEMO/DUMMY/UJI dihapus.',
                'waktu_aktivitas' => now(),
            ]);

            return ['status' => 'ok', 'dihapus' => $deleted];
        });
    }
}
