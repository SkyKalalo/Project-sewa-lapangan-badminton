<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingRequestController extends Controller
{
    public function store(string $kode, Request $request)
    {
        $data = $request->validate([
            'no_hp' => 'required|string|max:20',
            'jenis' => 'required|in:Pembatalan,Pindah Jadwal',
            'alasan' => 'nullable|string|max:1000',
            'id_lapangan_tujuan' => 'nullable|integer',
            'tanggal_tujuan' => 'nullable|date',
            'jam_tujuan' => 'nullable|date_format:H:i',
        ]);
        $data['no_hp'] = preg_replace('/\D+/', '', (string) $data['no_hp']);
        $booking = DB::table('booking')->join('pelanggan', 'pelanggan.id_pelanggan', '=', 'booking.id_pelanggan')
            ->where('kode_booking', $kode)->where('pelanggan.no_hp', $data['no_hp'])->select('booking.*')->first();
        abort_unless($booking, 404);
        abort_unless($booking->status_booking !== 'Dibatalkan', 422, 'Booking sudah dibatalkan.');
        abort_unless(! DB::table('permohonan_booking')->where('id_booking', $booking->id_booking)->where('status', 'Menunggu')->exists(), 422, 'Masih ada permohonan yang menunggu diproses.');
        $id = DB::table('permohonan_booking')->insertGetId([
            'id_booking' => $booking->id_booking,
            'jenis' => $data['jenis'],
            'alasan' => $data['alasan'] ?? null,
            'id_lapangan_tujuan' => $data['id_lapangan_tujuan'] ?? null,
            'tanggal_tujuan' => $data['tanggal_tujuan'] ?? null,
            'jam_tujuan' => $data['jam_tujuan'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_permohonan');
        return response()->json(['id_permohonan' => $id, 'status' => 'Menunggu'], 201);
    }

    public function index()
    {
        return DB::table('permohonan_booking as p')->join('booking as b', 'b.id_booking', '=', 'p.id_booking')
            ->join('pelanggan as c', 'c.id_pelanggan', '=', 'b.id_pelanggan')->where('p.status', 'Menunggu')
            ->select('p.*', 'b.kode_booking', 'b.status_booking', 'c.nama', 'c.no_hp')->orderBy('p.created_at')->get();
    }

    public function process(int $id, Request $request)
    {
        $data = $request->validate(['status' => 'required|in:Disetujui,Ditolak', 'catatan_admin' => 'nullable|string|max:1000']);
        $item = DB::table('permohonan_booking as p')->join('booking as b', 'b.id_booking', '=', 'p.id_booking')->where('p.id_permohonan', $id)->select('p.*', 'b.kode_booking')->first();
        abort_unless($item && $item->status === 'Menunggu', 422, 'Permohonan sudah diproses.');
        if ($data['status'] === 'Disetujui') {
            $internal = Request::create('', 'POST', array_filter([
                'id_lapangan' => $item->id_lapangan_tujuan,
                'tanggal_main' => $item->tanggal_tujuan,
                'jam_mulai' => $item->jam_tujuan ? substr($item->jam_tujuan, 0, 5) : null,
            ]));
            $internal->attributes->set('pengguna', $request->attributes->get('pengguna'));
            if ($item->jenis === 'Pembatalan') app(AdminBookingController::class)->cancel($item->kode_booking, $internal);
            else app(AdminBookingController::class)->reschedule($item->kode_booking, $internal);
        }
        DB::table('permohonan_booking')->where('id_permohonan', $id)->update(['status' => $data['status'], 'catatan_admin' => $data['catatan_admin'] ?? null, 'diproses_oleh' => $request->attributes->get('pengguna')->id_pengguna, 'updated_at' => now()]);
        return ['status' => $data['status']];
    }
}
