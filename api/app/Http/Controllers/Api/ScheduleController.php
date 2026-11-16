<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->validate(['tanggal' => 'required|date'])['tanggal'];
        $activeSlots = DB::table('detail_booking as d')
            ->join('booking as b', 'b.id_booking', '=', 'd.id_booking')
            ->whereIn('b.status_booking', ['Menunggu DP', 'DP Terverifikasi', 'Lunas'])
            ->select('d.id_lapangan', 'd.tanggal_main', 'd.jam_mulai', 'd.jam_selesai', 'b.kode_booking', 'b.status_booking');

        return DB::table('lapangan as l')
            ->leftJoinSub($activeSlots, 'slot', function ($join) use ($date) {
                $join->on('slot.id_lapangan', '=', 'l.id_lapangan')->where('slot.tanggal_main', '=', $date);
            })
            ->where('l.status', 'tersedia')
            ->select('l.id_lapangan', 'l.nomor_lapangan', 'l.status', 'slot.jam_mulai', 'slot.jam_selesai', 'slot.kode_booking', 'slot.status_booking')
            ->orderBy('l.id_lapangan')->orderBy('slot.jam_mulai')->get();
    }
}
