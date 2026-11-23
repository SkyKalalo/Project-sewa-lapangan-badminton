<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function recent()
    {
        $perCourt = \DB::table('detail_booking')->selectRaw('id_booking, id_lapangan, MIN(tanggal_main) as tanggal_main, MIN(jam_mulai) as jam_mulai, MAX(jam_selesai) as jam_selesai, SUM(durasi) as durasi')->groupBy('id_booking', 'id_lapangan');
        $summary = \DB::query()->fromSub($perCourt, 'pc')->selectRaw('id_booking, MIN(id_lapangan) as id_lapangan, MIN(tanggal_main) as tanggal_main, MIN(jam_mulai) as jam_mulai, MAX(jam_selesai) as jam_selesai, MAX(durasi) as durasi')->groupBy('id_booking');
        $rows = \DB::table('booking as b')->join('pelanggan as c', 'c.id_pelanggan', '=', 'b.id_pelanggan')->leftJoinSub($summary, 'd', 'd.id_booking', '=', 'b.id_booking')->select('b.id_booking', 'b.kode_booking', 'b.status_booking', 'c.nama', 'd.id_lapangan', 'd.tanggal_main', 'd.jam_mulai', 'd.jam_selesai')->orderByDesc('b.id_booking')->limit(12)->get();
        $courts = \DB::table('detail_booking')->whereIn('id_booking', $rows->pluck('id_booking'))->get(['id_booking', 'id_lapangan'])->groupBy('id_booking');
        return $rows->map(function ($row) use ($courts) { $row->id_lapangans = ($courts[$row->id_booking] ?? collect())->pluck('id_lapangan')->unique()->values(); return $row; });
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
        $perCourt = \DB::table('detail_booking')->selectRaw('id_booking, id_lapangan, MIN(tanggal_main) as tanggal_main, MIN(jam_mulai) as jam_mulai, MAX(jam_selesai) as jam_selesai, SUM(durasi) as durasi')->groupBy('id_booking', 'id_lapangan');
        $detailSummary = \DB::query()->fromSub($perCourt, 'pc')->selectRaw('id_booking, MIN(id_lapangan) as id_lapangan, MIN(tanggal_main) as tanggal_main, MIN(jam_mulai) as jam_mulai, MAX(jam_selesai) as jam_selesai, MAX(durasi) as durasi')->groupBy('id_booking');
        $rows = \DB::table('booking as b')->join('pelanggan as c', 'c.id_pelanggan', '=', 'b.id_pelanggan')
            ->leftJoinSub($detailSummary, 'd', 'd.id_booking', '=', 'b.id_booking')
            ->select('b.*', 'c.nama', 'c.no_hp', 'c.is_member', 'd.id_lapangan', 'd.tanggal_main', 'd.jam_mulai', 'd.jam_selesai', 'd.durasi')
            ->orderByDesc('b.id_booking')->get();
        $courts = \DB::table('detail_booking')->whereIn('id_booking', $rows->pluck('id_booking'))->get(['id_booking', 'id_lapangan'])->groupBy('id_booking');
        return $rows->map(function ($row) use ($courts) { $row->id_lapangans = ($courts[$row->id_booking] ?? collect())->pluck('id_lapangan')->unique()->values(); return $row; });
    }

    public function store(Request $request, BookingService $bookings)
    {
        $data = $request->validate([
            'nama_pelanggan' => 'required|string|max:100', 'no_hp' => 'required|string|max:20',
            'id_lapangan' => 'required_without:id_lapangans|integer',
            'id_lapangans' => 'required_without:id_lapangan|array|min:1|max:4',
            'id_lapangans.*' => 'required|integer|distinct',
            'tanggal_main' => 'required|date', 'jam_mulai' => 'required|date_format:H:i', 'durasi' => 'required|integer|min:1|max:4',
        ]);
        return response()->json($bookings->create($data), 201);
    }
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
            'durasi' => $details->groupBy('id_lapangan')->map(fn ($rows) => $rows->sum('durasi'))->max(),
            'id_lapangans' => $details->pluck('id_lapangan')->unique()->values(),
        ] : []));
    }
}
