<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function history(string $noHp)
    {
        $customer = DB::table('pelanggan')->where('no_hp', $noHp)->first();
        abort_unless($customer, 404, 'Pelanggan tidak ditemukan.');
        $bookings = DB::table('booking')->where('id_pelanggan', $customer->id_pelanggan)->orderByDesc('tanggal_booking')->get();
        return ['pelanggan' => $customer, 'riwayat' => $bookings, 'frekuensi_sewa' => $bookings->count(), 'total_transaksi' => (float) $bookings->where('status_booking','Lunas')->sum('total_biaya'), 'jumlah_pembatalan' => $bookings->where('status_booking','Dibatalkan')->count()];
    }
}
