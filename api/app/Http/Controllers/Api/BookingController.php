<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function recent()
    {
        $summary = \DB::table('detail_booking')->selectRaw('id_booking, MIN(id_lapangan) as id_lapangan, MIN(tanggal_main) as tanggal_main, MIN(jam_mulai) as jam_mulai, MAX(jam_selesai) as jam_selesai')->groupBy('id_booking');
        return \DB::table('booking as b')->join('pelanggan as c', 'c.id_pelanggan', '=', 'b.id_pelanggan')->leftJoinSub($summary, 'd', 'd.id_booking', '=', 'b.id_booking')->select('b.kode_booking', 'b.status_booking', 'c.nama', 'd.id_lapangan', 'd.tanggal_main', 'd.jam_mulai', 'd.jam_selesai')->orderByDesc('b.id_booking')->limit(12)->get();
    }

    public function adminStore(Request $request, BookingService $bookings)
    {
        $data = $request->validate(['nama_pelanggan' => 'required|string|max:100', 'no_hp' => 'required|string|max:20', 'id_lapangan' => 'required|integer', 'tanggal_main' => 'required|date', 'jam_mulai' => 'required|date_format:H:i', 'durasi' => 'required|integer|min:1', 'dp_terverifikasi' => 'boolean']);
        $result = $bookings->create($data, $request->attributes->get('pengguna')->id_pengguna);
        if (! empty($data['dp_terverifikasi'])) {
            \DB::transaction(function () use ($result, $request) {
                $booking = \DB::table('booking')->where('id_booking', $result['id_booking'])->first();
                \DB::table('pembayaran')->insert(['id_booking' => $booking->id_booking, 'jenis_pembayaran' => 'DP', 'nominal' => $booking->total_biaya / 2, 'status_verifikasi' => 'Terverifikasi', 'metode_pembayaran' => 'Transfer Manual', 'diverifikasi_oleh' => $request->attributes->get('pengguna')->id_pengguna, 'tanggal_pembayaran' => now()]);
                \DB::table('booking')->where('id_booking', $booking->id_booking)->update(['status_booking' => 'DP Terverifikasi']);
            });
            $result['status_booking'] = 'DP Terverifikasi';
        }
        return response()->json($result, 201);
    }

    public function adminIndex()
    {
        $detailSummary = \DB::table('detail_booking')->selectRaw('id_booking, MIN(id_lapangan) as id_lapangan, MIN(tanggal_main) as tanggal_main, MIN(jam_mulai) as jam_mulai, MAX(jam_selesai) as jam_selesai, SUM(durasi) as durasi')->groupBy('id_booking');
        return \DB::table('booking as b')->join('pelanggan as c', 'c.id_pelanggan', '=', 'b.id_pelanggan')
            ->leftJoinSub($detailSummary, 'd', 'd.id_booking', '=', 'b.id_booking')
            ->select('b.*', 'c.nama', 'c.no_hp', 'd.id_lapangan', 'd.tanggal_main', 'd.jam_mulai', 'd.jam_selesai', 'd.durasi')
            ->orderByDesc('b.id_booking')->get();
    }

    public function store(Request $request, BookingService $bookings) { $data=$request->validate(['nama_pelanggan'=>'required|string|max:100','no_hp'=>'required|string|max:20','id_lapangan'=>'required|integer','tanggal_main'=>'required|date','jam_mulai'=>'required|date_format:H:i','durasi'=>'required|integer|min:1']); return response()->json($bookings->create($data),201); }
    public function show(string $kode, Request $request)
    {
        $booking = \DB::table('booking')->join('pelanggan', 'pelanggan.id_pelanggan', '=', 'booking.id_pelanggan')
            ->where('kode_booking', $kode)->where('no_hp', $request->query('no_hp'))
            ->select('booking.*', 'pelanggan.nama', 'pelanggan.no_hp')->first();
        abort_unless($booking, 404);
        $details = \DB::table('detail_booking')->where('id_booking', $booking->id_booking)->orderBy('jam_mulai')->get();
        $detail = $details->first();
        return response()->json(array_merge((array) $booking, $detail ? [
            'id_lapangan' => $detail->id_lapangan,
            'tanggal_main' => $detail->tanggal_main,
            'jam_mulai' => $detail->jam_mulai,
            'jam_selesai' => $details->last()->jam_selesai,
            'durasi' => $details->sum('durasi'),
        ] : []));
    }
}
